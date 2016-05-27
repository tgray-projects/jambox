<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Comments\Controller;

use Comments\Model\Comment;
use ModuleTest\TestControllerCase;
use Users\Model\Group;
use Users\Model\User;
use Zend\Stdlib\Parameters;

class CommentsIndexControllerTest extends TestControllerCase
{
    public function setUp()
    {
        parent::setUp();

        // set up registered group
        // make registered group, if it does not exist, and clear cache
        if (!Group::exists('registered', $this->p4)) {
            Group::fromArray(
                array('Owners' => array($this->p4->getUser()), Group::ID_FIELD => 'registered'),
                $this->superP4
            )->save();
            $this->p4->getService('cache')->invalidateItem('groups');
        }
    }

    /**
     * Test queue behavior.
     */
    public function testAddQueuesComment()
    {
        // set active user
        $user = new User($this->superP4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        $services = $this->getApplication()->getServiceManager();
        $auth     = $services->get('auth');
        $adapter  = new \Users\Authentication\Adapter('foo', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        $post = new Parameters(
            array(
                'user'      => 'foo',
                'topic'     => 'a-b-c',
                'body'      => 'a b c',
                'timestamp' => 123
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($post);

        // dispatch and check output
        $this->dispatch('/comments/add');

        // ensure comment has been stored
        $this->assertSame(1, count(Comment::fetchAll(array(), $this->p4)));

        // ensure comment was queue
        $queue = $services->get('queue');
        $this->assertSame(1, $queue->getTaskCount());
        $task = $queue->grabTask();
        $this->assertSame('comment', $task['type']);
        $this->assertSame('1',       $task['id']);
    }
}
