<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace AccountsTest\Controller;

use ModuleTest\TestControllerCase;
use Users\Model\User;
use Users\Model\Group;
use Zend\Json\Json;
use Zend\Stdlib\Parameters;
use P4\File\File;
use P4\Uuid\Uuid;
use P4\Spec\Protections;

/**
 * Test that the user signup link is visible as required.
 * Test that when the user signs up, they get a mail with the link.
 * Test that when the user inputs the link, their account is created.
 * Test that when the user inputs the link, but the timeout has elapsed, the
 * account is not created.
 */
class IndexControllerTest extends TestControllerCase
{
    public function setUp()
    {
        parent::setUp();

        // tweak subject prefix (because we can)
        $services = $this->getApplication()->getServiceManager();
        $config   = $services->get('config');
        $config['mail']['subject_prefix'] = '[TEST]';
        $services->setService('config', $config);
    }

    // verify form absence and presence
    public function testSignupFormNoSuper()
    {
        @$this->dispatch('/login?format=partial');

        $this->assertNotQuery('div.login-dialog form button.signup');
        $this->assertNotQuery('div.login-dialog form a.reset[href="/account/password/reset/"]');
    }

    public function testLoginForm()
    {
        $this->configSuper();
        @$this->dispatch('/login?format=partial');
        $this->assertQuery('form button.signup');
        $this->assertQuery('form a[href="/account/password/reset/"]');
    }

    public function testSignupForm()
    {
        $this->configSuper();
        @$this->dispatch('/signup?format=partial');
        $this->assertQuery('form input[name="user"]');
        $this->assertQuery('form input[name="email"]');
        $this->assertQuery('form input[name="password"]');
        $this->assertQuery('form button.signup');
    }

    /**
     * Test the signup action.
     *
     * see if the user exists already, if so, login instead
     * see if we can create users, if not, return with error
     * validate user's requested username
     * send email to validate account
     * when user clicks link, try to create the user, if so, pass to login
     * fail condition
     */

    // test if user exists, expect error
    public function testSignupActionDuplicateUser()
    {
        $this->configSuper();

        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        $postData = new Parameters(
            array(
                'user'     => 'foo',
                'email'    => 'foo@test.com',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/signup');

        $result = $this->getResult();
        $this->assertRoute('signup');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }

    // invalid data, such as improper email address
    // invalid username, etc.  expect error
    public function testSignupInvalidData()
    {
        $this->configSuper();

        $postData = new Parameters(
            array(
                'user'     => 'bar',
                'email'    => 'bar@test',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/signup');

        $result = $this->getResult();
        $this->assertRoute('signup');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));

        $this->resetApplication();

        // missing username
        $postData = new Parameters(
            array(
                'user'     => '',
                'email'    => 'bar@test.com',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/signup');

        $result = $this->getResult();
        $this->assertRoute('signup');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));

        $this->resetApplication();

        // invalid
        $postData = new Parameters(
            array(
                'user'     => 'bar/...',
                'email'    => 'bar@test.com',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/signup');

        $result = $this->getResult();
        $this->assertRoute('signup');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));

        $this->resetApplication();

        // missing email
        $postData = new Parameters(
            array(
                'user'     => 'bar',
                'email'    => '',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/signup');

        $result = $this->getResult();
        $this->assertRoute('signup');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }

    // test if we can write to the data path, can we test this?
    //
    // move the path we store the tokens in to a config var, then set to
    // an invalid value?
    public function testSignupCannotWrite()
    {
    }

    // test valid signup action
    public function testSignupActionValid()
    {
        $this->configSuper();

        $mailer   = $this->getApplication()->getServiceManager()->get('mailer');
        $lastFile = $mailer->getLastFile();

        $postData = new Parameters(
            array(
                'user'     => 'bar',
                'email'    => 'bar@test.com',
                'password' => 'abcd1234'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/signup');

        $result = $this->getResult();
        $this->assertRoute('signup');
        $this->assertResponseStatusCode(200);
        $this->assertTrue($result->getVariable('isValid'));


        $emailFile = $mailer->getLastFile();
        $this->assertNotNull($emailFile);

        if ($lastFile) {
            $this->assertFileNotEquals($lastFile, $emailFile, 'Email File was not created');
        }

        $this->assertTrue(is_readable($emailFile));

        $config   = $this->getApplication()->getServiceManager()->get('config');
        $contents = file_get_contents($emailFile);
        $this->assertContains('To: bar@test.com', $contents);
        $this->assertContains('Subject: =?UTF-8?Q?[TEST]=20Your=20Perforce=20Workshop=20Account?=', $contents);
        $this->assertContains('Reply-To: ' . $config['mail']['sender'], $contents);
        $this->assertContains('Content-Type: multipart/alternative;', $contents);
        $this->assertContains('Content-Type: text/plain', $contents);
        $this->assertContains('Content-Type: text/html', $contents);

        // verify username and verify link
        $this->assertContains('username "bar"', $contents);
        $this->assertContains('account/verify/', $contents);
    }


    // navigate to user link, expired token, expect error
    public function testVerifyExpiredToken()
    {
        $this->markTestSkipped();
        $this->configSuper();

        $user = array(
            'username'  => 'foo',
            'email'     => 'foo@test.com',
            'password'  => 'test1234'
        );

        $token = $this->createToken($user, 'last week');

        @$this->dispatch('/account/verify/' . $token);

        $result = $this->getResult();
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('body.route-verify div.container-fluid', 'Your signup url has expired.');
    }

    // navigate to user link, invalid token, expect error
    public function testVerifyInvalidToken()
    {
        $this->markTestSkipped();
        $this->configSuper();

        $token = 'invalidtoken';

        @$this->dispatch('/account/verify/' . $token);

        $this->assertRoute('verify');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains(
            'body.route-verify div.container-fluid',
            'Invalid token submitted.  '
            . 'If you feel this message is in error, please re-register to receive a new token.'
        );
    }

    // navigate to user link, invalid token, expect error
    public function testVerifyMaliciousToken()
    {
        $this->markTestSkipped();
        $this->configSuper();

        $token = '&#46;&#46;&#47;config.php';

        @$this->dispatch('/account/verify/' . $token);

        $this->assertRoute('verify');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains(
            'body.route-verify div.container-fluid',
            'Invalid token submitted.  '
            . 'If you feel this message is in error, please re-register to receive a new token.'
        );
    }

    // navigate to user link, valid data, expect user account
    public function testVerifyValid()
    {
        $this->configSuper();

        $user = array(
            'username'  => 'foo',
            'email'     => 'foo@test.com',
            'password'  => 'test1234'
        );

        $token = $this->createToken($user, '+1 days');

        $userObject = new User($this->superP4);
        $userObject->setId($user['username'])
                   ->setFullName($user['username'])
                   ->setEmail($user['email'])
                   ->setPassword($user['password'])
                   ->save();

        $mailer   = $this->getApplication()->getServiceManager()->get('mailer');
        $lastFile = $mailer->getLastFile();

        @$this->dispatch('/account/verify/' . $token);

        // https://bugs.php.net/bug.php?id=61470
        session_write_close();

        // verify user was created
        $createdUser = User::fetch($user['username']);
        $this->assertEquals($user['email'], $createdUser->getEmail());
        $this->assertTrue(
            Group::isMember($user['username'], 'registered', false, $this->p4),
            'User is not a member of the "registered" group.'
        );

        // verify on login page
        //echo $this->getResponse()->getBody();

        $this->assertResponseStatusCode(200);
        $this->assertRoute('verify');

        $this->resetApplication();

        // verify login works
        $postData = new Parameters(
            array(
                'user'     => $user['username'],
                'password' => $user['password']
            )
        );
        $this->getRequest()
             ->setMethod(\Zend\Http\Request::METHOD_POST)
             ->setPost($postData);

        @$this->dispatch('/login');
        session_write_close();

        $result = $this->getResult();
        $this->assertRoute('login');
        $this->assertRouteMatch('users', 'users\controller\indexcontroller', 'login');
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);

        // verify protections created for the user
        $protections = Protections::fetch($this->superP4);
        $protectList = $protections->getProtections();
        $expectedProtect = array(
            'mode' => 'write',
            'type' => 'user',
            'name' => $user['username'],
            'host' => '*',
            'path' => '//guest/' . $user['username'] . '/...'
        );

        foreach ($protectList as $protect) {
            if ($protect === $expectedProtect) {
                break;
            }
        }

        $this->assertEquals(
            $protect,
            $expectedProtect,
            'Did not find expected protections table entry.'
        );

        // verify email was sent properly
        $emailFile = $mailer->getLastFile();
        $this->assertNotNull($emailFile);

        if ($lastFile) {
            $this->assertFileNotEquals($lastFile, $emailFile, 'Account creation email file was not created');
        }

        $this->assertTrue(is_readable($emailFile));

        $config   = $this->getApplication()->getServiceManager()->get('config');
        $contents = file_get_contents($emailFile);
        $this->assertContains('To: ' . $user['email'], $contents);
        $this->assertContains('Subject: =?UTF-8?Q?[TEST]=20Your=20Perforce=20Workshop=20Account?=', $contents);
        $this->assertContains('Reply-To: ' . $config['mail']['sender'], $contents);
        $this->assertContains('Content-Type: multipart/alternative;', $contents);
        $this->assertContains('Content-Type: text/plain', $contents);
        $this->assertContains('Content-Type: text/html', $contents);
    }

    public function testUserDelete()
    {
        $services = $this->getApplication()->getServiceManager();
        $p4super  = $services->get('p4_super');

        $user = array(
                'username'  => 'foo',
                'email'     => 'foo@test.com',
                'password'  => 'test1234'
        );

        $userObject = new User($this->superP4);
        $userObject->setId($user['username'])
                ->setFullName($user['username'])
                ->setEmail($user['email'])
                ->setPassword($user['password'])
                ->save();

        $postData = new Parameters(
            array(
                'username'     => 'foo',
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);
        $this->getApplication()->getServiceManager()->setService('p4_user', $p4super);

        $this->dispatch('/account/delete/foo');
        $this->assertResponseStatusCode(200);
        $this->assertFalse(User::exists('foo', $this->p4));
    }

    public function testUserDeleteNoPermission()
    {
        $services = $this->getApplication()->getServiceManager();

        $postData = new Parameters(
            array(
                'username' => 'nonadmin',
            )
        );

        $user = new User($this->p4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->save();

        $this->getRequest()
                ->setMethod(\Zend\Http\Request::METHOD_POST)
                ->setPost($postData);

        $this->dispatch('/account/delete/foo');
        $this->assertResponseStatusCode(403);
        $this->assertTrue(User::exists('foo', $this->p4));
    }

    public function testUserDeleteWithOpenFiles()
    {
        $services = $this->getApplication()->getServiceManager();
        $p4super  = $services->get('p4_super');

        $user = new User($this->p4);
        $user->setId('foo')
            ->setEmail('foo@test.com')
            ->setFullName('Mr Foo')
            ->save();

        $p4One = $this->p4;
        $p4Two = \P4\Connection\Connection::factory(
            $p4One->getPort(),
            $user->getId(),
            $p4One->getClient()
        )->connect();

        $file = new File($p4Two);
        $file->setFilespec('//depot/testfile')
            ->open()
            ->setLocalContents('xyz123')
            ->submit('change test');

        $file->open();

        $postData = new Parameters(
            array(
                'username'     => 'foo',
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);
        $this->getApplication()->getServiceManager()->setService('p4_user', $p4super);

        $this->dispatch('/account/delete/foo');
        $body = $this->getResponse()->getContent();
        $data = json_decode($body, true);

        $this->assertSame(
            $data['message'],
            'Could not delete user account.  User foo has file(s) open on 1 client(s) and can\'t be deleted.'
        );
        $this->assertTrue(User::exists('foo', $this->p4));
    }

    public function testUserDeleteAdmin()
    {
        $services = $this->getApplication()->getServiceManager();
        $p4super  = $services->get('p4_super');
        $p4admin  = $services->get('p4_admin');

        $postData = new Parameters(
            array(
                'username'     => $p4admin->getUser(),
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);
        $this->getApplication()->getServiceManager()->setService('p4_user', $p4super);

        $this->dispatch('/account/delete/' . $p4admin->getUser());
        $body = $this->getResponse()->getContent();
        $data = json_decode($body, true);

        $this->assertSame($data['message'], 'Deleting Swarm admin user is not allowed.');
        $this->assertTrue(User::exists($p4admin->getUser(), $this->p4));
    }

    /**
     * Creates a signup token for the specified username, email, and password.
     * Returns the file name.
     *
     * @param array     (username, email, password) User information
     * @param string    Expiry time, convertable with strtotime.
     * @return string filename
     */
    public function createToken($user, $expire = "+3 days")
    {
        $user += array('username' => null, 'password' => null, 'email' => null);

        $data = json_encode(
            array(
                'username' => $user['username'],
                'password' => $user['password'],
                'expiry'   => strtotime($expire),
                'email'    => $user['email'],
                'remember' => false
            )
        );

        $file   = (string)new Uuid;
        $path   = DATA_PATH . '/signup/';

        // make the path if it doesn't exist
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        file_put_contents($path . $file, $data);

        return $file;
    }

    /**
     * Adds the superuser config to the application config.
     */
    public function configSuper()
    {
        $services = $this->getApplication()->getServiceManager();
        $config   = $services->get('config');

        $config['p4_super'] = array(
            'port'     => $this->superP4->getPort(),
            'user'     => $this->superP4->getUser(),
            'password' => $this->superP4->getPassword(),
        );
        $services->setService('config', $config);
    }
}
