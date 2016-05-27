<?php
/**
 * Test methods for the P4 Change class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Connection\Connection;
use P4\Connection\Exception\CommandException;
use P4\Connection\Exception\ConflictException;
use P4\File\File;
use P4\File\Query as FileQuery;
use P4\Log\Logger;
use P4\Model\Fielded\Iterator as FieldedIterator;
use P4\Spec\Client;
use P4\Spec\Change;
use P4\Spec\Definition;
use P4\Spec\Exception\NotFoundException;
use P4\Spec\Exception\UnopenedException;
use P4\Spec\Exception\Exception as SpecException;
use P4\Spec\Job;
use P4\Spec\User;
use Zend\Log\Logger as ZendLogger;
use Zend\Log\Writer\Mock as MockLog;

class ChangeTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $change = new Change;
        $this->assertTrue($change instanceof Change, 'Expected class');
        $originalConnection = $change->getConnection();

        // test setting connection via constructor.
        $p4 = Connection::factory();
        $change = new Change($p4);
        $this->assertSame(
            $p4,
            $change->getConnection(),
            'getConnection() should match connection passed to constructor.'
        );

        // ensure two connections differ.
        $this->assertNotSame(
            $change->getConnection(),
            $originalConnection,
            'Connections should not match.'
        );
    }

    /**
     * Test exists.
     */
    public function testExists()
    {
        // ensure id-exists returns false for non-existant change
        // (we have no changes yet)
        $this->assertFalse(Change::exists(123), 'Given change should not exist.');

        // test that a bogus id does not exist
        $this->assertFalse(Change::exists('*'), 'Bogus change should not exist.');

        // ensure default change exists.
        $this->assertTrue(Change::exists('default'), 'Default change should exist.');

        // create a change and ensure it exists.
        $change = new Change;
        $change->setDescription('this is a test');
        $change->save();

        // new change should have id of 1.
        $this->assertTrue($change->getId() === 1, 'Change number should be one.');

        // change number 1 should exist.
        $this->assertTrue(Change::exists(1), 'Change 1 should exist.');
        $this->assertTrue(Change::exists('1'), 'Change "1" should exist.');
    }

    /**
     * Test fetch.
     */
    public function testFetch()
    {
        // ensure fetch fails for a non-existant change.
        try {
            Change::fetch(1234);
            $this->fail('Fetch should fail for a non-existant change.');
        } catch (NotFoundException $e) {
            $this->assertTrue(true);
        }

        // ensure fetch succeeds for default change.
        try {
            Change::fetch('default');
            $this->assertTrue(true);
        } catch (NotFoundException $e) {
            $this->fail('Fetch should succeed for default change.');
        }

        // ensure fetch succeeds for numbered change.
        $change = new Change;
        $description = "this is a test\n";
        $change->setDescription($description);
        $change->save();
        try {
            $fetched = Change::fetch($change->getId());
            $this->assertTrue(true);
        } catch (NotFoundException $e) {
            $this->fail('Fetch should succeed for a numbered change that exists.');
        }

        // ensure it only takes one command to fetch a change and read
        // basic values from it -- we verify this by peeking at the log
        $original = Logger::hasLogger() ? Logger::getLogger() : null;
        $logger   = new ZendLogger;
        $mock     = new MockLog;
        $logger->addWriter($mock);
        Logger::setLogger($logger);

        $fetched = Change::fetch($change->getId());
        $this->assertSame($description, $fetched->getDescription());
        $this->assertInternalType('int', $fetched->getTime());
        $this->assertSame(1, count($mock->events));

        // restore original logger if there is one.
        Logger::setLogger($original);
    }

    /**
     * Test that there is no difference between a saved change and a fetched change.
     *
     * @todo test get files.
     */
    public function testSavedVsFetched()
    {
        // open a file for add.
        $file = new File;
        $file->setFilespec('//depot/test-file')
              ->add();

        // create a job.
        $job = new Job;
        $job->set('Description', 'fix something')
            ->save();

        // save a change with a file and a job.
        $change = new Change;
        $change->setDescription("a change with a file and a job.\n")
               ->setFiles(array($file->getFilespec()))
               ->setJobs(array($job->getId()))
               ->save();

        $fetched = Change::fetch($change->getId());

        $types = array('Id', 'Date', 'User', 'Status', 'Description', 'JobStatus', 'Jobs');
        foreach ($types as $type) {
            $method = "get$type";
            $this->assertSame($fetched->$method(), $change->$method(), "Expect matching $type.");
        }
    }

    /**
     * Test the fetch all method.
     */
    public function testFetchAll()
    {
        /*
        *         FETCH_MAXIMUM - set to integer value to limit to the
        *                         first 'max' number of entries.
        *     FETCH_BY_FILESPEC - set to a filespec to limit changes to those
        *                         affecting the file(s) matching the filespec.
        *       FETCH_BY_STATUS - set to a valid change status to limit result
        *                         to changes with that status (e.g. 'pending').
        *      FETCH_INTEGRATED - set to true to include changes integrated
        *                         into the specified files.
        *       FETCH_BY_CLIENT - set to a client to limit changes to those
        *                         on the named client.
        *         FETCH_BY_USER - set to a user to limit changes to those
        *                         owned by the named user.
        *           FETCH_AFTER - set to an id _after_ which to start collecting entries
        *                         note: entries seen before 'after' count towards max.
        */

        // create a file and submitted change
        $file1 = new File;
        $file1->setFilespec('//depot/path-a/test-file')->add()->setLocalContents('test')->submit('test-1');

        // create a file and submitted change
        $file2 = new File;
        $file2->setFilespec('//depot/path-b/test-file')->add()->setLocalContents('test')->submit('test-2');

        // create a pending change with 2 files
        $files = new FieldedIterator;
        $file3 = new File;
        $files[] = $file3->setFilespec('//depot/path-c/test-file1')
                         ->add()
                         ->setLocalContents('test');
        $file4 = new File;
        $files[] = $file4->setFilespec('//depot/path-c/test-file2')
                         ->add()
                         ->setLocalContents('test');
        $change = new Change;
        $change->setFiles($files)->setDescription("Has 2 files\n")->save();

        // create a change by another user, in another workspace.
        $user = new User;
        $password = 'AnotherPass';
        $user->setId('alternate')
             ->setEmail('alternate@example.perforce.com')
             ->setFullName('Alternate User')
             ->save();
        $client = new Client;
        $clientId = 'another-test-client';
        $client->setId($clientId)
               ->setRoot(DATA_PATH . "/clients/$clientId")
               ->setView(array('//depot/... //another-test-client/...'))
               ->save();
        $p4 = Connection::factory(
            $this->p4->getPort(),
            $user->getId(),
            $client->getId()
        );
        $change = new Change($p4);
        $change->setDescription("in alternate client\n")->save();

        // and have the alternate user integrate an existing file
        $integFilespec = '//depot/path-a/test-integ';
        $result = $p4->run('integrate', array('-f', $file1->getFilespec(), $integFilespec));

        $change = new Change($p4);
        $change->addFile($integFilespec)->setDescription('Integration')->submit();

        // Testing begins: ensure correct number of changes returned.
        $changes = Change::fetchAll();
        $this->assertEquals(
            5,
            $changes->count(),
            'There should be 5 changes.'
        );

        // ensure that first change matches last submitted change.
        $expected = array(
            'Change'        => 5,
            'Client'        => $clientId,
            'User'          => $user->getId(),
            'Status'        => 'submitted',
            'Description'   => "Integration\n",
            'Type'          => 'public',
            'JobStatus'     => null,
            'Jobs'          => array()
        );
        $actual = $changes->first()->get();
        unset($actual['Date'], $actual['Files']);
        $this->assertEquals(
            $expected,
            $actual,
            'Fetched change should match expected values.'
        );

        // battery of tests against this setup with various options
        $tests = array(
            array(
                'label' => __LINE__ .': defaults',
                'options'   => array(),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                    "Has 2 files\n",
                    "test-2",
                    "test-1"
                ),
            ),

            array(
                'label' => __LINE__ .': fetch maximum 1',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 1,
                ),
                'expected'  => array(
                    "Integration\n",
                ),
            ),
            array(
                'label' => __LINE__ .': fetch maximum 2',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 2,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                ),
            ),
            array(
                'label' => __LINE__ .': fetch maximum 3',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 3,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                    "Has 2 files\n",
                ),
            ),
            array(
                'label' => __LINE__ .': fetch maximum 4',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 4,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                    "Has 2 files\n",
                    "test-2",
                ),
            ),
            array(
                'label' => __LINE__ .': fetch maximum 5',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 5,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                    "Has 2 files\n",
                    "test-2",
                    "test-1"
                ),
            ),
            array(
                'label' => __LINE__ .': fetch maximum 6',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 6,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                    "Has 2 files\n",
                    "test-2",
                    "test-1"
                ),
            ),
            array(
                'label' => __LINE__ .': fetch maximum 0',
                'options'   => array(
                    Change::FETCH_MAXIMUM => 0,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                    "Has 2 files\n",
                    "test-2",
                    "test-1"
                ),
            ),

            array(
                'label' => __LINE__ .': fetch by filespec //depot/.../test-file',
                'options'   => array(
                    Change::FETCH_BY_FILESPEC => '//depot/.../test-file',
                ),
                'expected'  => array(
                    "test-2",
                    "test-1"
                ),
            ),

            array(
                'label' => __LINE__ .': fetch by status submitted',
                'options'   => array(
                    Change::FETCH_BY_STATUS => 'submitted',
                ),
                'expected'  => array(
                    "Integration\n",
                    "test-2",
                    "test-1"
                ),
            ),
            array(
                'label' => __LINE__ .': fetch by status pending',
                'options'   => array(
                    Change::FETCH_BY_STATUS => 'pending',
                ),
                'expected'  => array(
                    "in alternate client\n",
                    "Has 2 files\n",
                ),
            ),

            array(
                'label' => __LINE__ .': fetch by client regular',
                'options'   => array(
                    Change::FETCH_BY_CLIENT => $this->p4->getClient(),
                ),
                'expected'  => array(
                    "Has 2 files\n",
                    "test-2",
                    "test-1"
                ),
            ),
            array(
                'label' => __LINE__ .': fetch by client alternate',
                'options'   => array(
                    Change::FETCH_BY_CLIENT => $clientId,
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                ),
            ),

            array(
                'label' => __LINE__ .': fetch by user regular',
                'options'   => array(
                    Change::FETCH_BY_USER => $this->p4->getUser(),
                ),
                'expected'  => array(
                    "Has 2 files\n",
                    "test-2",
                    "test-1"
                ),
            ),
            array(
                'label' => __LINE__ .': fetch by user alternate',
                'options'   => array(
                    Change::FETCH_BY_USER => $user->getId(),
                ),
                'expected'  => array(
                    "Integration\n",
                    "in alternate client\n",
                ),
            ),

            array(
                'label' => __LINE__ .': fetch without integrated',
                'options'   => array(
                    Change::FETCH_INTEGRATED  => false,
                    Change::FETCH_BY_FILESPEC => $integFilespec,
                ),
                'expected'  => array(
                    "Integration\n",
                ),
            ),
            array(
                'label' => __LINE__ .': fetch with integrated',
                'options'   => array(
                    Change::FETCH_INTEGRATED  => true,
                    Change::FETCH_BY_FILESPEC => $integFilespec,
                ),
                'expected'  => array(
                    "Integration\n",
                    "test-1"
                ),
            ),
            array(
                'label' => __LINE__ .': fetch with after',
                'options'   => array(
                    Change::FETCH_AFTER => 4
                ),
                'expected'  => array(
                    "Has 2 files\n",
                    'test-2',
                    'test-1'
                ),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $changes = Change::fetchAll($test['options']);
            $this->assertEquals(
                count($test['expected']),
                count($changes),
                "$label - Expected change count."
            );
            $descriptions = array();
            foreach ($changes as $change) {
                $descriptions[] = $change->getDescription();
            }
            $this->assertSame(
                $test['expected'],
                $descriptions,
                "$label - Expected change descriptions."
            );
        }

    }

    /**
     * Test getJobs and setJobs.
     */
    public function testAddGetSetJobs()
    {
        // test initial state of jobs in a fresh change object.
        $change = new Change;
        $jobs = $change->getJobs();
        $this->assertTrue(is_array($jobs), 'Expect an array from getJobs.');
        $this->assertSame(0, count($jobs), 'There should be no jobs associated with a fresh change.');

        // create a job
        $job = new Job;
        $job->set('Description', 'This is job #1');
        $job->save();

        // create a change to associate with the job
        $change = new Change;
        $change->setJobs(array($job->getId()));
        $jobs = $change->getJobs();
        $this->assertSame(1, count($jobs), 'Expect one job.');
        $this->assertSame($job->getId(), $jobs[0], 'Expect matching job id.');

        // add another job
        $extraJobId = 'anotherJob';
        $change->addJob($extraJobId);
        $jobs = $change->getJobs();
        $this->assertSame(2, count($jobs), 'Expect two jobs.');
        $this->assertSame($job->getId(), $jobs[0], 'Expect matching job id.');
        $this->assertSame($extraJobId, $jobs[1], 'Expect matching job id for added job.');

        // attempt to save the change with a non-existant job
        try {
            $change->setDescription('A change with jobs.')
                   ->save('save the change');
            $this->fail('Unexpected success saving change with a non-existant job.');
        } catch (CommandException $e) {
            $this->assertRegexp(
                "/Job 'anotherJob' doesn't exist\./",
                $e->getMessage(),
                'Expected error saving change with a non-existant job.'
            );
        } catch (\Exception $e) {
            $this->fail(
                'Unexpected exception saving change with a non-existant job ('
                . get_class($e) .') '. $e->getMessage()
            );
        }

        // create the non-existant job.
        $job2 = new Job;
        $job2->set('Description', 'Was non-existant')
             ->setId($extraJobId)
             ->save();

        // re-attempt saving the change now that the job exists.
        $change->setDescription('Change with jobs.')
               ->save();

        // fetch the changes we have, and verify the jobs.
        $changes = Change::fetchAll();
        $this->assertEquals(1, count($changes), 'Expect one change.');
        $this->assertSame(2, count($changes->first()->getJobs()), 'Expect 2 jobs with fetched change.');
        $this->assertSame(
            array($extraJobId, $job->getId()),
            $changes->first()->getJobs(),
            'Expected jobs in fetched change.'
        );
        $this->assertTrue($changes->first()->isPending(), 'Change should be pending.');
        $this->assertFalse($changes->first()->isSubmitted(), 'Change should not be submitted.');

        // now submit the change, and check the jobs.
        $file = new File;
        $file->setFilespec('//depot/file.txt')->add()->setLocalContents('File content.');
        $change->addFile($file);
        $change->submit();

        $changes = Change::fetchAll();
        $this->assertEquals(1, count($changes), 'Expect one change.');
        $this->assertSame(2, count($changes->first()->getJobs()), 'Expect 2 jobs with fetched & submitted change.');
        $this->assertSame(
            array($extraJobId, $job->getId()),
            $changes->first()->getJobs(),
            'Expected jobs in fetched & submitted change.'
        );
        $this->assertFalse($changes->first()->isPending(), 'Change should not be pending.');
        $this->assertTrue($changes->first()->isSubmitted(), 'Change should be submitted.');

        // create a new change, with non-existant jobId, and submit it.
        $change = new Change;
        $change->setDescription('Try submitting with non-existant job.');
        $thirdJobId = 'yetAnotherJob';
        $change->addJob($thirdJobId);
        $file = new File;
        $file->setFileSpec('//depot/file2.txt')->add()->setLocalContents('File content.');
        $change->addFile($file);
        $this->assertSame(null, $change->getId(), 'Expect id prior to submit.');

        try {
            $change->submit('Attempt 1.');
            $this->fail('Unexpected success submitting change with a non-existant job.');
        } catch (CommandException $e) {
            $this->assertRegexp(
                "/Change 2 created.*Job '$thirdJobId' doesn't exist\./s",
                $e->getMessage(),
                'Expected error message submitting change with a non-existant job.'
            );
        } catch (\Exception $e) {
            $this->fail(
                'Unexpected exception submitting change with a non-existant job ('
                . get_class($e) .') '. $e->getMessage()
            );
        }
        $this->assertSame(2, $change->getId(), 'Expected id after submit.');

        // create the job
        $job3 = new Job;
        $job3->set('Description', 'Was non-existant')
             ->setId($thirdJobId)
             ->save();

        // submit should now succeed.
        $change->submit('Attempt 2.');
        $changes = Change::fetchAll();
        $this->assertEquals(2, count($changes), 'Expect two changes.');
        $this->assertEquals(1, count($changes->first()->getJobs()), 'Expect 1 job with second submitted change.');
        $this->assertSame(
            array($thirdJobId),
            $changes->first()->getJobs(),
            'Expected jobs in second submitted change.'
        );
    }

    /**
     * Test functionality of the setFixStatus() method.
     */
    public function testFixStatus()
    {
        // modify job spec to add custom statuses
        $spec = Definition::fetch('job');
        $fields = $spec->getFields();
        $fields['Status']['options'] = array('a', 'b', 'c', 'd', 'closed');
        $fields['Status']['default'] = 'a';
        $spec->setFields($fields)->save();

        // open a file for add
        $file = new File;
        $file->setFilespec("//depot/foo")->setLocalContents("this is a test")->add();

        // create couple of jobs
        $job1 = new Job;
        $job1->set('Description', 'job 1')
             ->save();
        $job2 = new Job;
        $job2->set('Description', 'job 2')
             ->setStatus('b')
             ->save();

        // save a change with fixStatus set to 'same'
        $change = new Change;
        $change->setDescription("change test 1.\n")
               ->addFile($file)
               ->setJobs(array($job1->getId(), $job2->getId()))
               ->setFixStatus('same')
               ->save();

        // verify job statuses before submit
        $this->assertSame('a', $job1->getStatus());
        $this->assertSame('b', $job2->getStatus());

        // submit a change and verify job statuses after
        $change->submit();

        $this->assertSame('a', Job::fetch($job1->getId())->getStatus());
        $this->assertSame('b', Job::fetch($job2->getId())->getStatus());

        // try again with a new change and fixStatus set to 'd'
        $file = new File;
        $file->setFilespec("//depot/bar")->setLocalContents("this is a test")->add();
        $change = new Change;
        $change->setDescription("change test 2.\n")
               ->addFile($file)
               ->setJobs(array($job1->getId(), $job2->getId()))
               ->setFixStatus('d')
               ->save()
               ->submit();

        // verify job statuses after submit
        $this->assertSame('d', Job::fetch($job1->getId())->getStatus());
        $this->assertSame('d', Job::fetch($job2->getId())->getStatus());

        // try again without using fixStatus
        $file = new File;
        $file->setFilespec("//depot/baz")->setLocalContents("this is a test")->add();
        $change = new Change;
        $change->setDescription("change test 3.\n")
               ->addFile($file)
               ->setJobs(array($job1->getId(), $job2->getId()))
               ->save()
               ->submit();

        // verify job statuses after submit
        $this->assertSame('closed', Job::fetch($job1->getId())->getStatus());
        $this->assertSame('closed', Job::fetch($job2->getId())->getStatus());
    }

    /**
     * Test getFiles and setFiles.
     */
    public function testGetSetFiles()
    {
        // test initial state of files in a fresh change object.
        $change = new Change;
        $files = $change->getFiles();
        $this->assertTrue(is_array($files), 'Expect an array from getFiles.');
        $this->assertSame(0, count($files), 'There should be no files associated with a fresh change.');

        // create two submitted changes, and one pending
        $file1 = new File;
        $filespec1 = '//depot/change1.txt';
        $file1->setFilespec($filespec1)->add()->setLocalContents('content1')->submit('File 1');

        $file2 = new File;
        $filespec2 = '//depot/change2.txt';
        $file2->setFilespec($filespec2)->add()->setLocalContents('content2')->submit('File 2');

        $file3 = new File;
        $filespec3 = '//depot/change3.txt';
        $file3->setFilespec($filespec3)->add()->setLocalContents('content3');
        $this->assertTrue($file3->isOpened(), 'File #3 should be opened.');

        // test that we get the appropriate files back for each change
        $change = Change::fetch(1);
        $this->assertFalse($change->isPending(), 'Change 1 should not be pending.');
        $files = $change->getFiles();
        $this->assertSame(1, count($files), 'There should be one file associated with change 1.');
        $this->assertSame($filespec1.'#1', $files[0], 'Expected filespec for change 1.');

        $change = Change::fetch(2);
        $this->assertFalse($change->isPending(), 'Change 2 should not be pending.');
        $files = $change->getFiles();
        $this->assertSame(1, count($files), 'There should be one file associated with change 2.');
        $this->assertSame($filespec2.'#1', $files[0], 'Expected filespec for change 2.');

        $change = Change::fetch('default');
        $this->assertTrue($change->isPending(), 'Change 3 should be pending.');
        $files = $change->getFiles();
        $this->assertSame(1, count($files), 'There should be one file associated with change 3.');
        $this->assertSame($filespec3, $files[0], 'Expected filespec for change 3.');

        // test that setting a comment on a pending changelist does not influence
        // getFiles handling.
        $change->setDescription('This is the default change.');
        $files = $change->getFiles();
        $this->assertSame(1, count($files), 'There should be one file associated with change 3.');
        $this->assertSame($filespec3, $files[0], 'Expected filespec for change 3.');

        // test that we cannot setFiles on a submitted changelist.
        try {
            $change = Change::fetch(2);
            $change->setFiles(array($filespec3));
            $this->fail('Unexpected success setting files on a submitted changelist.');
        } catch (SpecException $e) {
            $this->assertSame(
                'Cannot set files on a submitted change.',
                $e->getMessage(),
                'Expected error setting files on a submitted changelist'
            );
        } catch (\Exception $e) {
            $this->fail(
                'Unexpected exception setting files on a submitted changelist: ('
                . get_class($e) .') '. $e->getMessage()
            );
        }

        // test that we can setFiles on a pending changelist, and that getFiles
        // returns the same list.
        $change = new Change;
        $change->setId('default');
        $change->setFiles(array($filespec1));
        $files = $change->getFiles();
        $this->assertSame(1, count($files), 'There should be one file associated with change 3 after setFiles.');
        $this->assertSame($filespec1, $files[0], 'Expected filespec for change 3 after setFiles.');

        // test that we can set the files to null to empty the list, and that we get
        // empty list in return.
        $change->setFiles(null);
        $files = $change->getFiles();
        $this->assertSame(0, count($files), 'There should now be no files associated with change 3.');

        // test that fetching a new change object returns the original files.
        $change = Change::fetch('default');
        $files = $change->getFiles();
        $this->assertSame(1, count($files), 'There should be one file associated with change 3.');
        $this->assertSame($filespec3, $files[0], 'Expected filespec for change 3.');

        // test that we can setFiles with an iterator of P4\File objects.
        $files = new FieldedIterator;
        $files[] = $file1;
        $files[] = $file2;
        $files[] = $file3;
        $change->setFiles($files);
        $files = $change->getFiles();
        $this->assertSame(3, count($files), 'There should now be three files associated with change 3.');
        $this->assertSame($filespec1, $files[0], 'Expected filespec for change 3, file 0.');
        $this->assertSame($filespec2, $files[1], 'Expected filespec for change 3, file 1.');
        $this->assertSame($filespec3, $files[2], 'Expected filespec for change 3, file 2.');
    }

    /**
     * Test accessors.
     */
    public function testAccessors()
    {
        // save a basic change.
        $change = new Change;
        $description = "this is a test\n";
        $change->setDescription($description);
        $change->save();

        // open a file for add.
        $file = new File;
        $file->setFilespec('//depot/test-file')
              ->add();

        // create a job.
        $job = new Job;
        $job->set('Description', 'fix something')
            ->save();

        // save a change with a file and a job.
        $change2      = new Change;
        $description2 = "a change with a file and a job.\n";
        $change2->setDescription($description2)
                ->setFiles(array($file->getFilespec()))
                ->setJobs(array($job->getId()))
                ->save();

        // ensure fetched change contains expected data.
        $tests = array(

            // test accessors for a brand new (unpopulated) change object.
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getId',
                'expected'      => null
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getDate',
                'expected'      => null
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getUser',
                'expected'      => $this->p4->getUser()
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getClient',
                'expected'      => $this->p4->getClient()
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getStatus',
                'expected'      => 'pending'
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getDescription',
                'expected'      => null
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getJobStatus',
                'expected'      => null
            ),
            array(
                'label'         => __LINE__ . ': New change object',
                'change'        => new Change,
                'method'        => 'getJobs',
                'expected'      => array()
            ),

            // test accessors for a saved change object.
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getId',
                'expected'      => 1
            ),
            // datetime not tested here - see later test
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getUser',
                'expected'      => $this->p4->getUser()
            ),
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getClient',
                'expected'      => $this->p4->getClient()
            ),
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getStatus',
                'expected'      => 'pending'
            ),
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getDescription',
                'expected'      => $description
            ),
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getJobStatus',
                'expected'      => null
            ),
            array(
                'label'         => __LINE__ . ': Saved change object',
                'change'        => $change,
                'method'        => 'getJobs',
                'expected'      => array()
            ),

            // test accessors for a change object with a file and a job attached.
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getId',
                'expected'      => 2
            ),
            // datetime not tested here - see later test
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getUser',
                'expected'      => $this->p4->getUser()
            ),
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getClient',
                'expected'      => $this->p4->getClient()
            ),
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getStatus',
                'expected'      => 'pending'
            ),
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getDescription',
                'expected'      => $description2
            ),
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getJobStatus',
                'expected'      => null
            ),
            array(
                'label'         => __LINE__ . ': Change w. a file and a job',
                'change'        => $change2,
                'method'        => 'getJobs',
                'expected'      => array($job->getId())
            ),
        );

        // run each test.
        foreach ($tests as $test) {
            $label = $test['label'] .' - '. $test['method'];
            $this->assertSame(
                $test['expected'],
                $test['change']->{$test['method']}(),
                "$label - expected value."
            );
        }
    }

    /**
     * test setDescription.
     */
    public function testSetDescription()
    {
        $tests = array(
            array(
                'label'       => __LINE__ .': null',
                'description' => null,
                'error'       => false,
            ),
            array(
                'label'       => __LINE__ .': empty string',
                'description' => '',
                'error'       => false,
            ),
            array(
                'label'       => __LINE__ .': array',
                'description' => array(),
                'error'       => true,
            ),
            array(
                'label'       => __LINE__ .': integer',
                'description' => 123,
                'error'       => true,
            ),
            array(
                'label'       => __LINE__ .': float',
                'description' => -1.23,
                'error'       => true,
            ),
            array(
                'label'       => __LINE__ .': string',
                'description' => "have a nice day\n",
                'error'       => false,
            ),
        );

        foreach ($tests as $test) {
            $change = new Change;
            $label = $test['label'];
            try {
                $change->setDescription($test['description']);
                if ($test['error']) {
                    $this->fail("$label - Unexpected success.");
                }
            } catch (\InvalidArgumentException $e) {
                if ($test['error']) {
                    $this->assertSame(
                        'Cannot set description. Invalid type given.',
                        $e->getMessage(),
                        "$label - Expected error."
                    );
                } else {
                    $this->fail("$label - Unexpected argument exception: ".  $e->getMessage());
                }
            } catch (\Exception $e) {
                $this->fail("$label - Unexpected exception: (". get_class($e) .') '.  $e->getMessage());
            }

            if (!$test['error']) {
                $this->assertSame(
                    $test['description'],
                    $change->getDescription(),
                    "$label - Expect to get same description as set."
                );
            }
        }
    }

    /**
     * test setFiles.
     *
     * @todo add a test for setting files on a submitted change when submits work.
     */
    public function testSetFiles()
    {
        // create an iterator with files
        $files = new FieldedIterator;
        $file = new File;
        $files[] = $file->setFilespec('//depot/file1.txt');
        $file = new File;
        $files[] = $file->setFilespec('//depot/file2.txt');
        $file = new File;
        $files[] = $file->setFilespec('//depot/file3.txt');

        $filesIteratorInvalid = new FieldedIterator;
        $filesIteratorInvalid[] = $file;
        $filesIteratorInvalid[] = new Client;

        $tests = array(
            array(
                'label'    => __LINE__ .': iterator',
                'files'    => $files,
                'error'    => false,
                'expected' => array(
                    $files[0]->getFilespec(),
                    $files[1]->getFilespec(),
                    $files[2]->getFilespec(),
                ),
            ),
            array(
                'label'    => __LINE__ .': invalid iterator',
                'files'    => $filesIteratorInvalid,
                'error'    => new \InvalidArgumentException('All files must be a string or P4\File'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': null',
                'files'    => null,
                'error'    => false,
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': empty string',
                'files'    => '',
                'error'    => new \InvalidArgumentException('Cannot set files. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': integer',
                'files'    => 123,
                'error'    => new \InvalidArgumentException('Cannot set files. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': float',
                'files'    => -1.23,
                'error'    => new \InvalidArgumentException('Cannot set files. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': string',
                'files'    => "have a nice day\n",
                'error'    => new \InvalidArgumentException('Cannot set files. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': empty array',
                'files'    => array(),
                'error'    => false,
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': array with numerics',
                'files'    => array(1, 2),
                'error'    => new \InvalidArgumentException('All files must be a string or P4\File'),
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': array with strings and numerics',
                'files'    => array('one', 2),
                'error'    => new \InvalidArgumentException('All files must be a string or P4\File'),
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': array with a string',
                'files'    => array('one'),
                'error'    => false,
                'expected' => array('one'),
            ),
            array(
                'label'    => __LINE__ .': array with multiple strings',
                'files'    => array('one', 'two', 'three'),
                'error'    => false,
                'expected' => array('one', 'two', 'three'),
            ),
        );

        foreach ($tests as $test) {
            $change = new Change;
            $label = $test['label'];
            try {
                $change->setFiles($test['files']);
                if ($test['error']) {
                    $this->fail("$label - Unexpected success.");
                }
            } catch (\Exception $e) {
                if ($test['error']) {
                    $this->assertSame(
                        $test['error']->getMessage(),
                        $e->getMessage(),
                        "$label - Expected error."
                    );
                    $this->assertSame(
                        get_class($test['error']),
                        get_class($e),
                        "$label - Expected error class."
                    );
                } else {
                    $this->fail("$label - Unexpected exception: (". get_class($e) .') '.  $e->getMessage());
                }
            }

            if (!$test['error']) {
                $this->assertTrue(
                    is_array($change->getFiles()),
                    "$label - Change getFiles should return array."
                );
                $this->assertSame(
                    count($test['expected']),
                    count($change->getFiles()),
                    "$label - Change should contain same number of files."
                );
            }
        }
    }

    /**
     * test setJobs.
     *
     * @todo add a test for setting jobs on a submitted change when submits work.
     */
    public function testSetJobs()
    {
        // create an iterator with jobs.
        $jobs   = new FieldedIterator;
        $job    = new Job;
        $jobs[] = $job->setId('job000001');
        $job    = new Job;
        $jobs[] = $job->setId('job000002');
        $job    = new Job;
        $jobs[] = $job->setId('job000003');

        $jobsIteratorInvalid = new FieldedIterator;
        $jobsIteratorInvalid[] = $job;
        $jobsIteratorInvalid[] = new Client;

        // define inputs to set jobs.
        $tests = array(
            array(
                'label'    => __LINE__ .': iterator',
                'jobs'     => $jobs,
                'error'    => false,
                'expected' => array('job000001', 'job000002', 'job000003'),
            ),
            array(
                'label'    => __LINE__ .': iterator with invalid element',
                'jobs'     => $jobsIteratorInvalid,
                'error'    => new \InvalidArgumentException('Each job must be a string.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': null',
                'jobs'     => null,
                'error'    => false,
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': empty string',
                'jobs'     => '',
                'error'    => new \InvalidArgumentException('Cannot set jobs. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': integer',
                'jobs'     => 123,
                'error'    => new \InvalidArgumentException('Cannot set jobs. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': float',
                'jobs'     => -1.23,
                'error'    => new \InvalidArgumentException('Cannot set jobs. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': string',
                'jobs'     => "have a nice day\n",
                'error'    => new \InvalidArgumentException('Cannot set jobs. Invalid type given.'),
                'expected' => false,
            ),
            array(
                'label'    => __LINE__ .': empty array',
                'jobs'     => array(),
                'error'    => false,
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': array with numerics',
                'jobs'     => array(1, 2),
                'error'    => new \InvalidArgumentException('Each job must be a string.'),
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': array with strings and numerics',
                'jobs'     => array('one', 2),
                'error'    => new \InvalidArgumentException('Each job must be a string.'),
                'expected' => array(),
            ),
            array(
                'label'    => __LINE__ .': array with a string',
                'jobs'     => array('one'),
                'error'    => false,
                'expected' => array('one'),
            ),
            array(
                'label'    => __LINE__ .': array with multiple strings',
                'jobs'     => array('one', 'two', 'three'),
                'error'    => false,
                'expected' => array('one', 'two', 'three'),
            ),
        );

        foreach ($tests as $test) {
            $change = new Change;
            $label = $test['label'];
            try {
                $change->setJobs($test['jobs']);
                if ($test['error']) {
                    $this->fail("$label - Unexpected success.");
                }
            } catch (\Exception $e) {
                if ($test['error']) {
                    $this->assertSame(
                        $test['error']->getMessage(),
                        $e->getMessage(),
                        "$label - Expected error."
                    );
                    $this->assertSame(
                        get_class($test['error']),
                        get_class($e),
                        "$label - Expected error class."
                    );
                } else {
                    $this->fail("$label - Unexpected exception: (". get_class($e) .') '.  $e->getMessage());
                }
            }

            if (!$test['error']) {
                $this->assertSame(
                    $test['expected'],
                    $change->getJobs(),
                    "$label - Expected jobs."
                );
            }
        }
    }

    /**
     * Test moving files between changelists.
     */
    public function testReopen()
    {
        // open a file for add.
        $file = new File;
        $file->setFilespec("//depot/test-file")
             ->add();

        // put the file in a change.
        $change1 = new Change;
        $change1->setDescription("test 1")
                ->addFile("//depot/test-file")
                ->save();
        $this->assertTrue(
            count($change1->getFiles()) == 1,
            "Change should have one file."
        );
        $this->assertTrue(
            in_array("//depot/test-file", $change1->getFiles()),
            "test-file should be in change."
        );

        // try to put the same file in a different change.
        $change2 = new Change;
        $change2->setDescription("test 2")
                ->addFile("//depot/test-file")
                ->save();
        $this->assertTrue(
            count($change2->getFiles()) == 1,
            "Change should have one file."
        );
        $this->assertTrue(
            in_array("//depot/test-file", $change2->getFiles()),
            "test-file should be in change."
        );

        // try to put same file back in first (now numbered) change.
        $change1->addFile("//depot/test-file")->save();
        $this->assertTrue(
            count($change1->getFiles()) == 1,
            "Change should have one file."
        );
        $this->assertTrue(
            in_array("//depot/test-file", $change1->getFiles()),
            "test-file should be in change."
        );

        // attempting to put a un-opened file in a change should fail.
        $change3 = new Change;
        try {
            $change3->setDescription("test 3")->addFile("//depot/foo")->save();
            $this->fail("Save change with unopened file should throw exception.");
        } catch (UnopenedException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test deleting changes.
     */
    public function testDelete()
    {
        // delete an unidentified change.
        $change = new Change;
        try {
            $change->delete();
            $this->fail("Delete change without id should fail.");
        } catch (SpecException $e) {
            $this->assertTrue(true);
        }

        // delete the default change.
        $change = new Change;
        $change->setId('default');
        try {
            $change->delete();
            $this->fail("Delete default change should fail.");
        } catch (SpecException $e) {
            $this->assertTrue(true);
        }

        // delete a non-existant change.
        $change = new Change;
        $change->setId('123');
        try {
            $change->delete();
            $this->fail("Delete non-existent change should fail.");
        } catch (NotFoundException $e) {
            $this->assertTrue(true);
        }

        // delete a real pending change w. no files.
        $change = new Change;
        $change->setDescription("test")->save()->delete();
        $this->assertFalse(
            Change::exists($change->getId()),
            "Deleted change should no longer exist."
        );

        // delete a real pending change w. files.
        $file = new File;
        $file->setFilespec("//depot/test-file")->add();
        $change = new Change;
        $change->setDescription("test")->addFile("//depot/test-file")->save()->delete();
        $this->assertFalse(
            Change::exists($change->getId()),
            "Deleted change should no longer exist."
        );

        // delete a real pending change w. jobs attached.
        $job = new Job;
        $job->set("Description", "test-job")->save();
        $change = new Change;
        $change->setDescription("test")->addJob($job->getId())->save()->delete();
        $this->assertFalse(
            Change::exists($change->getId()),
            "Deleted change should no longer exist."
        );

        // create a change under another client workspace.
        $client = new Client;
        $client->setId("another-test-client")
               ->setRoot(DATA_PATH . "/clients/another-test-client")
               ->save();
        $p4 = Connection::factory(
            $this->p4->getPort(),
            $this->p4->getUser(),
            $client->getId(),
            $this->getP4Params('password')
        );
        $change = new Change($p4);
        $change->setDescription("test-change")->save();
        $id = $change->getId();

        // delete another client's change.
        $change = Change::fetch($id);
        try {
            $change->delete();
            $this->fail("Delete of another client change should fail.");
        } catch (SpecException $e) {
            $this->assertTrue(true);
        }

        // delete again, but with force option.
        $change->delete(true);
        $this->assertFalse(
            Change::exists($change->getId()),
            "Deleted change should no longer exist."
        );

        // test delete of a submitted change.
        $file = new File;
        $file->setFilespec("//depot/foo")
             ->setLocalContents("this is a test")
             ->add()
             ->submit("test");

        $change = Change::fetch($file->getStatus('headChange'));
        try {
            $change->delete();
            $this->fail("Delete of submitted change should fail.");
        } catch (SpecException $e) {
            $this->assertSame(
                "Cannot delete a submitted change without the force option.",
                $e->getMessage(),
                "Unexpected exception message."
            );
        }

        try {
            $change->delete(true);
            $this->fail("Delete of submitted change should fail.");
        } catch (SpecException $e) {
            $this->assertSame(
                "Cannot delete a submitted change that contains files.",
                $e->getMessage(),
                "Unexpected exception message."
            );
        }

        // obliterate the files in change.
        $this->p4->run("obliterate", array("-y", "//...@=" . $change->getId()));
        $change = Change::fetch($change->getId());
        $change->delete(true);
        $this->assertFalse(
            Change::exists($change->getId()),
            "Change should no longer exist."
        );
    }

    /**
     * Test submitting of changes.
     */
    public function testSubmit()
    {
        $file = new File;
        $file->setFilespec("//depot/foo")->setLocalContents("this is a test")->add();

        // ensure no changes to start.
        $changes = Change::fetchAll();
        $this->assertSame(
            0,
            count($changes),
            "There should be no changes."
        );

        // do a submit.
        $change = new Change;
        $change->addFile($file)->submit("test submit");

        // ensure change was successful.
        $changes = Change::fetchAll();
        $this->assertSame(
            1,
            count($changes),
            "There should be one change."
        );
        $this->assertSame(
            Change::SUBMITTED_CHANGE,
            $change->getStatus(),
            "Change should be submitted."
        );

        // test that saving a submitted change results in exception
        try {
            $change->save();
            $this->fail('Unexpected success saving a submitted change.');
        } catch (SpecException $e) {
            $this->assertSame(
                'Cannot update a submitted change without the force option.',
                $e->getMessage(),
                'Expected exception saving a submitted change.'
            );
        } catch (\Exception $e) {
            $this->fail(
                'Unexpected exception saving a submitted change ('
                . get_class($e) .') '. $e->getMessage()
            );
        }

        // test that submitted a submitted change results in exception
        try {
            $change->submit();
            $this->fail('Unexpected success submitting a submitted change.');
        } catch (SpecException $e) {
            $this->assertSame(
                'Can only submit pending changes.',
                $e->getMessage(),
                'Expected exception submitting a submitted change.'
            );
        } catch (\Exception $e) {
            $this->fail(
                'Unexpected exception submitting a submitted change ('
                . get_class($e) .') '. $e->getMessage()
            );
        }

        // test that setting jobs on a submitted change results in exception
        try {
            $change->setJobs(array());
            $this->fail('Unexpected success setting jobs on a submitted change.');
        } catch (SpecException $e) {
            $this->assertSame(
                'Cannot set jobs on a submitted change.',
                $e->getMessage(),
                'Expected exception setting jobs on a submitted change.'
            );
        } catch (\Exception $e) {
            $this->fail(
                'Unexpected exception setting jobs on a submitted change ('
                . get_class($e) .') '. $e->getMessage()
            );
        }
    }

    /**
     * Verify submit works if change is renumbered (was broken at one point).
     */
    public function testSubmitRenumber()
    {
        $file = new File;
        $file->setFilespec("//depot/foo")->setLocalContents("this is a test")->add();

        // make the change.
        $change = new Change;
        $change->addFile($file)->save();

        // make another change to induce renumbering
        $change2 = new Change;
        $change2->setDescription('bump change number')->save();

        $change->submit("test submit");

        // ensure submit was successful.
        $changes = Change::fetchAll();
        $this->assertSame(
            2,
            count($changes),
            "There should be two changes."
        );
        $this->assertSame(
            Change::SUBMITTED_CHANGE,
            $change->getStatus(),
            "Change should be submitted."
        );
    }

    /**
     * Test submit resolve behavior.
     */
    public function testSubmitConflicts()
    {
        // create a second client.
        $client = new Client;
        $client->setId("client-2")
               ->setRoot($this->getP4Params('clientRoot') . '/client-2')
               ->addView("//depot/...", "//client-2/...")
               ->save();

        // connect w. second client.
        $p4 = Connection::factory(
            $this->p4->getPort(),
            $this->p4->getUser(),
            $client->getId(),
            $this->getP4Params('password')
        );

        // create a situation where resolve is needed.
        //  a. from the main test client add/submit 'foo', then edit it.
        //  b. from another client sync/edit/submit 'foo'.
        $file1 = new File;
        $file1->setFilespec("//depot/foo")
              ->setLocalContents("contents-1")
              ->add()
              ->submit("change 1")
              ->edit();
        $file2 = new File($p4);
        $file2->setFilespec("//depot/foo")
              ->sync()
              ->edit()
              ->submit("change 2");

        // try to submit a change w. files needing resolve.
        $change = new Change;
        try {
            $change->addFile($file1)
                   ->submit("main client submit");
            $this->fail("Unexpected success; submit should fail.");
        } catch (ConflictException $e) {
            $files = $change->getFilesToResolve();
            $this->assertEquals(
                1,
                count($files),
                "Expected one file needing resolve for submit."
            );
            $this->assertSame(
                $file1->getFilespec(),
                $files[0]->getFilespec(),
                "Expected matching filespecs."
            );
        }

        // create a situation where revert is needed.
        //  a. from the main test client add 'foo'.
        //  b. from another client add/submit 'foo'.
        $file1 = new File;
        $file1->setFilespec("//depot/bar")
              ->setLocalContents("contents-1")
              ->add();
        $file2 = new File($p4);
        $file2->setFilespec("//depot/bar")
              ->setLocalContents("contents-1")
              ->add()
              ->submit("change 2")
              ->edit()
              ->submit("change 3");

        // try to submit a change w. files needing revert.
        $change = new Change;
        try {
            $change->addFile($file1)
                   ->submit("main client submit");
            $this->fail("Unexpected success; submit should fail.");
        } catch (ConflictException $e) {
            $files = $change->getFilesToRevert();
            $this->assertEquals(
                1,
                count($files),
                "Expected one file needing resolve for revert."
            );
            $this->assertSame(
                $file1->getFilespec(),
                $files[0]->getFilespec(),
                "Expected matching filespecs."
            );
        }

        // create another situation where revert is needed.
        //  a. from the main test client add/submit 'foo', then edit it.
        //  b. from another client sync/delete/submit 'foo'.
        $file1 = new File;
        $file1->setFilespec("//depot/baz")
              ->setLocalContents("contents-1")
              ->add()
              ->submit("change 1")
              ->edit();
        $file2 = new File($p4);
        $file2->setFilespec("//depot/baz")
              ->sync()
              ->delete()
              ->submit("change 2");

        // try to submit a change w. files needing revert.
        $change = new Change;
        try {
            $change->addFile($file1)
                   ->submit("main client submit");
            $this->fail("Unexpected success; submit should fail.");
        } catch (ConflictException $e) {
            $files = $change->getFilesToRevert();
            $this->assertSame(
                1,
                count($files),
                "Expected one file needing resolve."
            );
            $this->assertSame(
                $file1->getFilespec(),
                $files[0]->getFilespec(),
                "Expected matching filespecs."
            );
        }
    }

    /**
     * Test submit resolve behavior when passing resolve options.
     */
    public function testSubmitResolveConflicts()
    {
        // create a second client.
        $client = new Client;
        $client->setId("client-2")
               ->setRoot($this->getP4Params('clientRoot') . '/client-2')
               ->addView("//depot/...", "//client-2/...")
               ->save();

        // connect w. second client.
        $p4 = Connection::factory(
            $this->p4->getPort(),
            $this->p4->getUser(),
            $client->getId(),
            $this->getP4Params('password')
        );

        // create a situation where resolve is needed.
        //  a. from the main test client add/submit 'foo', then edit it.
        //  b. from another client sync/edit/submit 'foo'.
        $file1 = new File;
        $file1->setFilespec("//depot/foo")
              ->setLocalContents("contents-1")
              ->add()
              ->submit("change 1")
              ->edit();
        $file2 = new File($p4);
        $file2->setFilespec("//depot/foo")
              ->sync()
              ->edit()
              ->submit("change 2");

        // try to submit a change w. files needing resolve.
        $change = new Change;
        $change->addFile($file1)
               ->submit("main client submit", Change::RESOLVE_ACCEPT_YOURS);
    }

    /**
     * test bad change numbers against P4\Validate\ChangeNumber
     */
    public function testBadValidateChangeNumber()
    {
        $tests = array (
            array(
                'label'     => __LINE__ .': null',
                'value'     => null,
            ),
            array(
                'label'     => __LINE__ .': empty',
                'value'     => '',
            ),
            array(
                'label'     => __LINE__ .': negative',
                'value'     => -1,
            ),
            array(
                'label'     => __LINE__ .': float',
                'value'     => 10.10,
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $validator = new \P4\Validate\ChangeNumber();

            $this->assertSame(
                false,
                $validator->isValid($test['value']),
                "$label - Expected Invalid"
            );
        }
    }

    /**
     * Test a fetch/save use case that failed in the past.
     */
    public function testSave()
    {
        $change = Change::fetch('default');
        $change->setDescription('Test submit')
               ->save();
    }

    /**
     * Test a save but with no description
     */
    public function testSaveNoDescription()
    {
        $change = Change::fetch('default');
        $change->save();
    }

    /**
     * Test updating a submitted change description.
     */
    public function testUpdateSubmitted()
    {
        $file = new File;
        $file->setFilespec("//depot/foo")
             ->setLocalContents("this is a test")
             ->add()
             ->submit('original description');

        $change = Change::fetch(1);
        $this->assertSame("original description\n", $change->getDescription());

        $change->setDescription('updated description')
               ->save(true);

        $change = Change::fetch(1);
        $this->assertSame("updated description\n", $change->getDescription());
    }

    /**
     * Test reverting an entire change.
     */
    public function testRevert()
    {
        $file1 = new File;
        $file1->setFilespec('//depot/one');
        $file1->add();

        $file2 = new File;
        $file2->setFilespec('//depot/two');
        $file2->add();

        $file3 = new File;
        $file3->setFilespec('//depot/three');
        $file3->add();

        $change = new Change;
        $change->setDescription("Test change");
        $change->setFiles(array('//depot/two', '//depot/three'));
        $change->save();

        // check that we have three files open.
        $query = FileQuery::create()
                 ->addFilespec('//depot/...')
                 ->setLimitToOpened(true);
        $this->assertSame(
            3,
            File::fetchAll($query)->count(),
            "Expected three open files"
        );

        // revert the pending change.
        $change->revert();

        // check that we have one file open.
        $this->assertSame(
            1,
            File::fetchAll($query)->count(),
            "Expected one open file"
        );

        // check that the correct files are opened.
        $file1->clearStatusCache();
        $this->assertTrue($file1->isOpened());
        $file2->clearStatusCache();
        $this->assertFalse($file2->isOpened());
        $file3->clearStatusCache();
        $this->assertFalse($file3->isOpened());
    }

    /**
     * Test getFileObjects and getFileObject
     */
    public function testGetFileObjectsObject()
    {
        $file1 = new File;
        $file1->setFilespec('//depot/one')
              ->add()
              ->setLocalContents("contents-1");

        $file2 = new File;
        $file2->setFilespec('//depot/two')
              ->add()
              ->setLocalContents("contents-2");

        $change = new Change;
        $change->setDescription("Test change")
               ->setFiles(array($file1, $file2))
               ->save();

        $this->assertSame(
            $change->getFiles(),
            $change->getFileObjects()->invoke('getDepotFilename'),
            'Expected get files to match fileobject list pre-submit'
        );

        $change->submit('test');
        File::fetch('//depot/one')->edit()->submit('rev two');

        $this->assertSame(
            array('//depot/one#1', '//depot/two#1'),
            $change->getFiles(),
            'Expected matching list of post-submit files'
        );

        $this->assertSame(
            array('1', '1'),
            $change->getFileObjects()->invoke('getStatus', array('headRev')),
            'Expected properly reved file objects post submit'
        );

        $this->assertSame(
            '//depot/one',
            $change->getFileObject(File::fetch('//depot/one#2'))->getDepotFilename(),
            'Expected getFileObject to provide proper depot filename'
        );
        $this->assertSame(
            '//depot/two',
            $change->getFileObject(File::fetch('//depot/two'))->getDepotFilename(),
            'Expected getFileObject to provide proper depot filename'
        );
        $this->assertSame(
            '1',
            $change->getFileObject(File::fetch('//depot/one#2'))->getStatus('headRev'),
            'Expected getFileObject to provide proper rev'
        );

        try {
            $change->getFileObject('//depot/three');
            $this->fail('Expected exception on getFileObject for invalid entry');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            $change->getFileObject(12);
            $this->fail('Expected exception on getFileObject for invalid type');
        } catch (\InvalidArgumentException $e) {

        }
    }

    /**
     * Test getJobObjects and getJobObject
     */
    public function testGetJobObjectsObject()
    {
        $job1 = new Job;
        $job1->setId('job1')
             ->setDescription('job1')
             ->save();
        $job2 = new Job;
        $job2->setId('job2')
             ->setDescription('job2')
             ->save();

        $change = new Change;
        $change->setDescription("Test change")
            ->setJobs(array($job1, $job2))
            ->save();

        $this->assertSame(
            $change->getJobs(),
            $change->getJobObjects()->invoke('getId'),
            'Expected get jobs to match job object list pre-submit'
        );

        $this->assertSame(
            'job1',
            $change->getJobObject('job1')->getId(),
            'Expected get job object to return result'
        );

        // verify invalid id fails
        try {
            $change->getJobObject('job3');
        } catch (\InvalidArgumentException $e) {
        }
    }

    /**
     * Test running fetch all with an array of ids
     */
    public function testFetchAllByIds()
    {
        $file = new File;
        $file->setFilespec("//depot/file")
             ->add()
             ->setLocalContents("one")
             ->submit("test");
        $file->edit()
             ->setLocalContents("two")
             ->submit("test2");
        $file->edit()
            ->setLocalContents("three")
            ->submit("test3");

        $changes = Change::fetchAll(array(Change::FETCH_BY_IDS => array('1', '3')));

        $this->assertSame(
            array(1, 3),
            $changes->invoke('getId'),
            'expected matching result'
        );
    }

    /**
     * Test getting changes data
     */
    public function testGetChangesData()
    {
        $change = new Change;
        try {
            $change->getChangesData();
            $this->assertFalse(true, "Expected exception");
        } catch (\P4\Spec\Exception\Exception $e) {
            $this->assertTrue(true);
        }

        $file = new File;
        $file->setFilespec("//depot/file")
             ->add()
             ->setLocalContents("one")
             ->submit("test");

        $change = Change::fetch(1);
        $data   = $change->getChangesData();

        $this->assertSame('1',         $data['change']);
        $this->assertSame('tester',    $data['user']);
        $this->assertSame('//depot/*', $data['path']);

        // ensure it doesn't take any more commands to get the change path
        // and original id -- we verify this by peeking at the log
        $original = Logger::hasLogger() ? Logger::getLogger() : null;
        $logger   = new ZendLogger;
        $mock     = new MockLog;
        $logger->addWriter($mock);
        Logger::setLogger($logger);

        $this->assertSame('//depot', $change->getPath());
        $this->assertSame(1, $change->getOriginalId());
        $this->assertSame(0, count($mock->events));

        // restore original logger if there is one.
        Logger::setLogger($original);
    }

    /**
     * Test setting a new client on an existing change
     */
    public function testSetClient()
    {
        $change = new Change($this->p4);
        $change->setDescription('test')->save();
        $oldClient = $change->getClient();

        $this->p4->getService('clients')->grab();
        $change->setClient($this->p4->getClient());
        $change->save();
        $this->p4->getService('clients')->release();

        $this->assertFalse(
            $oldClient == Change::fetch($change->getId(), $this->p4)->getClient(),
            'expected client change to have saved.'
        );
    }

    /**
     * Test what happens when 'type' field isn't present
     */
    public function testNoType()
    {
        // journal patch the server so it appears to have a change spec that was locked at 2006.1
        $this->p4->run(
            'admin',
            'import',
            "@pv@ 0 @db.bodtext@ @change@ 0 @Change;code:201;fmt:L;rq;ro;seq:1;len:10;;Date;code:202;"
            . "type:date;opt:always;fmt:R;ro;seq:3;len:20;;Client;code:203;opt:always;fmt:L;ro;seq:2;"
            . "len:32;;User;code:204;opt:always;fmt:L;ro;seq:4;len:32;;Status;code:205;opt:always;"
            . "fmt:R;ro;seq:5;len:10;;Description;code:206;type:text;opt:required;rq;seq:6;;JobStatus;"
            . "code:207;type:select;fmt:I;seq:8;;Jobs;code:208;type:wlist;seq:7;len:32;;Files;code:210;"
            . "type:llist;len:64;;@\n"
        );

        $change = new Change;
        $this->assertSame(Change::PUBLIC_CHANGE, $change->getType());
    }

    /**
     * Test a saving with a different user id set
     */
    public function testSaveOtherUser()
    {
        // verify we cannot swap to an invalid user
        $this->assertFalse(User::exists('swapped'));
        $change = Change::fetch('default');

        try {
            $change->setDescription('Test submit')
                   ->setUser('swapped')
                   ->save();
            $this->fail('should not have worked');
        } catch (CommandException $e) {
            $this->assertSame(
                "Command failed: Error in change specification.\nUser swapped doesn't exist.",
                $e->getMessage()
            );
        }


        // add the user swapped to make later attempts work
        $user = new User;
        $user->setId('swapped')->setEmail('foo@bar.com')->setFullName('Swapped User')->save();


        // verify set user works on existing change
        $change = Change::fetch('default');
        $change->setDescription('Test submit2')
               ->save();

        $this->assertSame('tester', Change::fetch(1)->getUser());

        $change = Change::fetch(1);
        $change->setUser('swapped')->save();

        $this->assertSame('swapped', Change::fetch(1)->getUser());


        // verify out of the gate set user works
        $change = Change::fetch('default');
        $change->setDescription('Test submit')
               ->setUser('swapped')
               ->save();

        $this->assertSame('swapped', Change::fetch(2)->getUser());
    }

    /**
     * Test canAccess() method.
     */
    public function testCanAccess()
    {
        // create user 'foo' with limited access to depot
        $p4Foo = $this->connectWithAccess('foo', array('//depot/foo/...'));

        // create few changes to test with
        $file   = new File;
        $file->setFilespec('//depot/test1')->open()->setLocalContents('abc');
        $change = new Change($this->p4);
        $change->setType(Change::RESTRICTED_CHANGE)->addFile($file)->submit('restricted #1'); // NOT accessible by 'foo'
        $id1 = $change->getId();

        $file   = new File;
        $file->setFilespec('//depot/foo/test3')->open()->setLocalContents('def');
        $change = new Change($this->p4);
        $change->setType(Change::RESTRICTED_CHANGE)->addFile($file)->submit('restricted #2'); // accessible by 'foo'
        $id2 = $change->getId();

        $file   = new File;
        $file->setFilespec('//depot/test2')->open()->setLocalContents('ghi');
        $change = new Change($this->p4);
        $change->setType(Change::PUBLIC_CHANGE)->addFile($file)->submit('public'); // accessible by 'foo'
        $id3 = $change->getId();

        // for users other than 'foo', all changes should be accessible
        $this->assertTrue(Change::fetch($id1, $this->p4)->canAccess());
        $this->assertTrue(Change::fetch($id2, $this->p4)->canAccess());
        $this->assertTrue(Change::fetch($id3, $this->p4)->canAccess());

        // ensure user 'foo' cannot access change id1, but can access the others
        $this->assertFalse(Change::fetch($id1, $p4Foo)->canAccess());
        $this->assertTrue(Change::fetch($id2, $p4Foo)->canAccess());
        $this->assertTrue(Change::fetch($id3, $p4Foo)->canAccess());
    }

    public function testGetFileData()
    {
        $files = array();
        for ($i = 0; $i < 10; $i++) {
            $file    = new File;
            $files[] = $file;
            $file->setFilespec('//depot/' . $i)
                 ->add()
                 ->setLocalContents('contents-' . $i);
        }

        $change = new Change;
        $change->setDescription("Test change")
               ->setFiles($files)
               ->save('test');

        $change = Change::fetch(1);
        $files  = $change->getFileData();
        $this->assertSame(10, count($files));
        $this->assertSame('//depot/0', current(current($files)));

        // test max limiting
        $files = $change->getFileData(null, 5);
        $this->assertSame(5, count($files));

        // test that shelved/unshelved files get cached separately
        $files = $change->getFileData(true);
        $this->assertSame(0, count($files));
    }
}
