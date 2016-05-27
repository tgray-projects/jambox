<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace UsersTest\Controller;

use ModuleTest\TestControllerCase;
use Projects\Model\Project;
use Users\Authentication\Adapter as AuthAdapter;
use Users\Model\Group;
use Users\Model\User;
use Zend\Stdlib\Parameters;

class UsersIndexControllerTest extends TestControllerCase
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
     * Test the login action with posted valid credentials.
     */
    public function testLoginPostValid()
    {
        // create a new user
        $user = new User($this->superP4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        $postData = new Parameters(
            array(
                'user'     => 'foo',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/login');

        $result = $this->getResult();
        $this->assertRoute('login');
        $this->assertRouteMatch('users', 'users\controller\indexcontroller', 'login');
        $this->assertResponseStatusCode(200);
        $this->assertInstanceOf('Zend\View\Model\JsonModel', $result);
        $this->assertTrue($result->getVariable('isValid'));
    }

    /**
     * Test the logout action.
     */
    public function testLogout()
    {
        // add a user
        $user = new User($this->superP4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        // first get the session and re-open it so the auth adapter can function
        $services = $this->getApplication()->getServiceManager();
        $session  = $services->get('session');
        $session->start();

        // authenticate the foo user
        $auth     = $services->get('auth');
        $adapter  = new AuthAdapter('foo', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);
        $this->assertTrue($auth->hasIdentity());

        // now that we're authed, close the session to match standard behaviour
        $session->writeClose();

        // ensure closing didn't muck anything
        $this->assertTrue($auth->hasIdentity());

        // logout via dispatching to 'logout' action
        $this->dispatch('/logout');
        $this->assertRoute('logout');
        $this->assertRouteMatch('users', 'users\controller\indexcontroller', 'logout');
        $this->assertResponseStatusCode(302);
        $this->assertFalse($auth->hasIdentity());
    }

    /**
     * Test the follow action.
     */
    public function testBasicFollowing()
    {
        // add a user
        $user = new User($this->superP4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        // authenticate the foo user
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter('foo', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // verify get of follow indicates not currently a follower
        $this->dispatch('/follow/user/tester');
        $this->assertRoute('follow');
        $this->assertResponseStatusCode(200);
        $result = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame(array('isFollowing' => false), $result);

        // foo follows tester
        $this->getRequest()->setMethod(\Zend\Http\Request::METHOD_POST);
        $this->dispatch('/follow/user/tester');
        $this->assertRoute('follow');
        $this->assertResponseStatusCode(200);

        $user = User::fetch('foo', $this->p4);
        $this->assertSame(array('user' => array('tester')), $user->getConfig()->getFollows());

        // verify get of follow indicates now a follower
        $this->getRequest()->setMethod(\Zend\Http\Request::METHOD_GET);
        $this->dispatch('/follow/user/tester');
        $result = json_decode($this->getResponse()->getBody(), true);
        $this->assertSame(array('isFollowing' => true), $result);

        // add a new project
        $project = new Project($this->p4);
        $project->set(
            array(
                'id'        => 'p1',
                'members'   => array('x', 'y')
            )
        )->save();

        // foo follows p1
        $this->getRequest()->setMethod(\Zend\Http\Request::METHOD_POST);
        $this->dispatch('/follow/project/p1');
        $this->assertRoute('follow');
        $this->assertResponseStatusCode(200);

        $user = User::fetch('foo', $this->p4);
        $this->assertSame(array('user' => array('tester'), 'project' => array('p1')), $user->getConfig()->getFollows());

        // verify indexing works
        $result = $this->p4->run('search', '1202=' . strtoupper(bin2hex('user:tester')));
        $this->assertSame('swarm-user-foo', $result->getData(0));
        $result = $this->p4->run('search', '1202=' . strtoupper(bin2hex('project:p1')));
        $this->assertSame('swarm-user-foo', $result->getData(0));
    }

    /**
     * Test the follow action.
     */
    public function testUnfollowing()
    {
        // add a user
        $user = new User($this->superP4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        // authenticate the foo user
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter('foo', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // foo follows tester
        $this->getRequest()->setMethod(\Zend\Http\Request::METHOD_POST);
        $this->dispatch('/follow/user/tester');
        $this->assertRoute('follow');
        $this->assertResponseStatusCode(200);

        $user = User::fetch('foo', $this->p4);
        $this->assertSame(array('user' => array('tester')), $user->getConfig()->getFollows());

        // foo unfollows tester
        $this->dispatch('/unfollow/user/tester');
        $this->assertRoute('unfollow');
        $this->assertResponseStatusCode(200);
        $user = User::fetch('foo', $this->p4);
        $this->assertSame(array(), $user->getConfig()->getFollows());

        // verify index cleaned up.
        $result = $this->p4->run('search', '1201=' . strtoupper(bin2hex('user:tester')));
        $this->assertSame(array(), $result->getData());
    }
}
