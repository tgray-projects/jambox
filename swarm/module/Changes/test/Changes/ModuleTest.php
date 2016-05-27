<?php
/**
 * Tests for the review module.
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ChangesTest;

use ModuleTest\TestControllerCase;
use P4\File\File;
use P4\Spec\Change;
use P4\Spec\Depot;
use P4\Spec\User;

class ModuleTest extends TestControllerCase
{
    public function setUp()
    {
        parent::setUp();

        // add module-test namespace
        \Zend\Loader\AutoloaderFactory::factory(
            array(
                'Zend\Loader\StandardAutoloader' => array(
                    'namespaces' => array(
                        'ChangesTest' => BASE_PATH . '/module/Changes/test/Changes',
                    )
                )
            )
        );
    }

    public function testGitChangeGitUserRetry()
    {
        // ensure changes owned by the git fusion user are not processed instantly but
        // instead are pushed into the future until owned by another user (up to a limit)

        $services  = $this->getApplication()->getServiceManager();
        $queue     = $services->get('queue');
        $events    = $queue->getEventManager();
        $p4        = $this->p4;
        $queuePath = $queue->getConfig();
        $queuePath = $queuePath['path'];

        // create the git-fusion-user so we can credit the change to them
        $user = new User($this->p4);
        $user->setId('git-fusion-user')->setEmail('foo@bar.com')->setFullName('Git-Fusion User')->save();

        // create a change as per git's usage
        $shelf = new Change($p4);
        $shelf->setDescription(
            "Test git review!\n"
            . "With a two line description\n"
            . "\n"
            . "Imported from Git\n"
            . " Author: Bob Bobertson <bbobertson@perforce.com> 1381432565 -0700\n"
            . " Committer: Git Fusion Machinery <nobody@example.com> 1381432572 +0000\n"
            . " sha1: 6a96f259deb6d8567a4d85dce09ae2e707ca7286\n"
            . " push-state: complete\n"
            . " review-status: create\n"
            . " review-id: 1\n"
            . " review-repo: Talkhouse\n"
        )->save();
        $file = new File($p4);
        $file->setFilespec('//depot/foo');
        $file->setLocalContents('some file contents');
        $file->add(1);
        $p4->run('shelve', array('-c', 1, '//...'));
        $p4->run('revert', array('//...'));

        // swap it to the git-fusion-user post shelf (as the shelving will fail otherwise)
        $shelf->setUser('git-fusion-user')->save();

        // add a listener before the event is handled to ensure the retry count from disk is correct
        $retry = false;
        $events->attach(
            'task.shelve',
            function ($event) use (&$retry) {
                $data  = (array) $event->getParam('data');
                $retry = isset($data['retries']) ? $data['retries'] : false;
            },
            301
        );

        // add a listener that comes after the event should be cancelled to confirm that happened
        $aborted = true;
        $events->attach(
            'task.shelve',
            function ($event) use (&$aborted) {
                $aborted = false;
            },
            299
        );

        // verify attempts 1-20 work out correctly and have a roughly appropriate delay
        for ($i = 0; $i < 20; $i++) {
            // push into queue and process
            $this->assertSame(0, $queue->getTaskCount());
            $data = null;
            if ($i) {
                $data = array('retries' => $i);
            }
            $queue->addTask('shelve', $shelf->getId(), $data);
            $this->processQueue();

            // verify the task wasn't fully processed
            $this->assertTrue($aborted, "iteration $i event should have aborted!");
            $this->assertSame($i ?: false, $retry, "iteration $i retry count isn't expected value");

            // queue should now contain 1 task, but we can't grab it yet
            $this->assertSame(1, $queue->getTaskCount(), "iteration $i task count");
            $this->assertFalse($queue->grabTask(), "iteration $i grab attempt");

            // verify the correct retry count
            $file = current(glob($queuePath . "/*"));
            $task = $queue->parseTaskFile($file);
            $this->assertSame(array('retries' => $i + 1), $task['data'], "iteration $i data");

            // verify the time is in the correct neighborhood (we allow a seconds slop to allow for clock rollover)
            $delay    = (int) ltrim(substr(basename($file), 0, -7), 0) - (int) microtime(true);
            $expected = min(pow(2, $i + 1), 60);
            $this->assertTrue($delay >= ($expected - 1), "iteration $i delay is too small");
            $this->assertTrue($delay < ($expected + 1),  "iteration $i delay is too big");

            unlink($file);
        }

        // verify attempt 21 results in event being processed
        // push into queue and process
        $queue->addTask('shelve', $shelf->getId(), array('retries' => 20));
        $this->processQueue();

        // verify the task was fully processed
        $this->assertFalse($aborted, "iteration 20 event should not have aborted!");
        $this->assertSame(20, $retry, "iteration 20 retry count isn't expected value");

        // queue should now contain 0 tasks
        $this->assertSame(0, $queue->getTaskCount(), "iteration 20 task count");
    }

    public function testGitChangeGitFusionDepot()
    {
        // verify changes owned by git-fusion-user are not delayed
        //  if they are against the .git-fusion depot

        $services  = $this->getApplication()->getServiceManager();
        $queue     = $services->get('queue');
        $p4        = $this->p4;

        // create the git-fusion-user so we can credit the change to them
        $user = new User($this->p4);
        $user->setId('git-fusion-user')->setEmail('foo@bar.com')->setFullName('Git-Fusion User')->save();

        // create the .git-fusion depot
        $depot = new Depot($this->superP4);
        $depot->setId('.git-fusion')->setType('local')->setMap(DATA_PATH . '/git-fusion/...')->save();
        $this->p4->disconnect()->connect();

        $this->p4->getService('clients')->grab();

        // create a change as per git's usage
        $shelf = new Change($p4);
        $shelf->setDescription("Test git change!")->save();
        $file = new File($p4);
        $file->setFilespec('//.git-fusion/foo');
        $file->setLocalContents('some file contents');
        $file->add(1);
        $p4->run('shelve', array('-c', 1, '//...'));
        $p4->run('revert', array('//...'));

        // swap it to the git-fusion-user post shelf (as the shelving will fail otherwise)
        $shelf->setUser('git-fusion-user')->save();

        // push into queue and process
        $queue->addTask('shelve', $shelf->getId());
        $this->processQueue();

        // verify the task was fully processed
        $this->assertSame(0, $queue->getTaskCount(), "shelf against .git-fusion");


        // now lets try it with a commit
        $shelf->setUser($this->p4->getUser())->save(true);
        $p4->run('unshelve', array('-s', $shelf->getId(), '-c', $shelf->getId(), '-f'));
        $p4->run('shelve', array('-c', $shelf->getId(), '-d'));
        $shelf->submit('test commit');
        $shelf->setUser('git-fusion-user')->save(true);

        // push into queue and process
        $queue->addTask('commit', $shelf->getId());
        $this->processQueue();

        // verify the task was fully processed
        $this->assertSame(0, $queue->getTaskCount(), "commit against .git-fusion");
    }

    protected function processQueue()
    {
        $this->getRequest()->getQuery()->set('debug', 1)->set('retire', 1);
        $this->dispatch('/queue/worker');
    }
}
