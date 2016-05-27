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
use Users\Authentication\Adapter as AuthAdapter;
use Users\Model\Group;
use Users\Model\User;
use P4\Uuid\Uuid;
use Zend\Stdlib\Parameters;

/**
 * Tests:
 * GET reset, no super config, error
 * GET reset, no user param: show form
 * GET reset, invalid user param, error.
 * GET reset, user, no token: error.
 * GET reset, user, invalid token: expired, error.
 * GET reset, user, invalid token: bad token value, error.
 * GET reset, user, valid token, redirect to change action
 * POST reset, invalid user, error
 * POST reset, valid user, token generated and email sent
 *
 * GET change,
 * POST change, invalid user, error
 * POST change, password is reset
 */
class PasswordControllerTest extends TestControllerCase
{
    public function setUp()
    {
        parent::setUp();

        // tweak subject prefix (because we can)
        $services = $this->getApplication()->getServiceManager();
        $config   = $services->get('config');
        $config['mail']['subject_prefix'] = '[TEST]';
        $services->setService('config', $config);

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

    // GET reset, no super config, error
    public function testResetFormNoSuper()
    {
        @$this->dispatch('/account/password/reset?format=partial');
        $this->assertQuery('body.route-resetPassword.error');
    }

    // GET reset, no user param: show form
    public function testResetForm()
    {
        $this->configSuper();
        @$this->dispatch('/account/password/reset?format=partial');
        $this->assertQuery('form input[name="identity"]');
        $this->assertQuery('form button.reset');
    }

    /*
     * POST reset, invalid user, error
     */
    public function testResetPostInvalidUserData()
    {
        $this->configSuper();

        // invalid user
        $postData = new Parameters(
            array('identity' => 'invaliduser')
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/account/password/reset');

        $result = $this->getResult();
        $this->assertRoute('resetPassword');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));

        $this->resetApplication();

        $this->configSuper();

        // missing username
        $postData = new Parameters(
            array(
                'identity' => ''
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/account/password/reset');

        $result = $this->getResult();
        $this->assertRoute('resetPassword');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }
    /**
     * POST reset, valid user, token generated and email sent
     */
    public function testResetPostValidData()
    {
        $this->configSuper();

        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        $mailer   = $this->getApplication()->getServiceManager()->get('mailer');
        $lastFile = $mailer->getLastFile();

        $postData = new Parameters(
            array(
                'identity' => 'foo'
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/account/password/reset');

        $result = $this->getResult();
        $this->assertRoute('resetPassword');
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
        $this->assertContains('To: foo@test.com', $contents);
        $this->assertContains('Subject: =?UTF-8?Q?[TEST]=20Your=20Perforce=20Workshop=20Account?=', $contents);
        $this->assertContains('Reply-To: ' . $config['mail']['sender'], $contents);
        $this->assertContains('Content-Type: multipart/alternative;', $contents);
        $this->assertContains('Content-Type: text/plain', $contents);
        $this->assertContains('Content-Type: text/html', $contents);

        // verify username and verify link
        $this->assertContains('account/password/reset/foo/', $contents);
    }

    /**
     * GET reset, invalid user, error
     */
    public function testResetGetInvalidUserData()
    {
        $this->configSuper();

        // invalid user
        @$this->dispatch('/account/password/reset/invaliduser');

        $result = $this->getResult();
        $this->assertRoute('resetPassword');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('body.route-resetPassword script', 'Invalid username or token provided.');
    }

    /**
     * GET reset, user, no token: error.
     * GET reset, user, invalid token: expired, error.
     * GET reset, user, invalid token: bad token value, error.
     */
    public function testResetInvalidTokenData()
    {
        $this->configSuper();

        //invalid token
        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        @$this->dispatch('/account/password/reset/foo/invalid');

        $result = $this->getResult();
        $this->assertRoute('resetPassword');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('body.route-resetPassword script', 'Invalid username or token provided.');

        $this->resetApplication();
        $this->configSuper();

        //expired token
        $user   = User::fetch('foo');
        $token  = (string)new Uuid;
        $config = $user->getConfig();
        $config->setRawValue('resetToken', $token);
        $config->setRawValue('tokenExpiryTime', strtotime('-1 week'));
        $config->save();

        @$this->dispatch('/account/password/reset/foo/' . $token);
        $result = $this->getResult();
        $this->assertRoute('resetPassword');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('body.route-resetPassword script', 'Invalid username or token provided.');
    }

    /**
     * GET reset, user, valid token, redirect to change action
     */
    public function testResetValidTokenData()
    {
        $this->configSuper();

        //invalid token
        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        $token  = (string)new Uuid;
        $config = $user->getConfig();
        $config->setRawValue('resetToken', $token);
        $config->setRawValue('tokenExpiryTime', strtotime('+1 day'));
        $config->save();

        @$this->dispatch('/account/password/reset/foo/' . $token);

        $this->assertRoute('resetPassword');
        $this->assertResponseStatusCode(200);
        $this->assertQuery('body.route-resetPassword form input[type="hidden"][value="' . $token . '"]');
        $this->assertQuery('body.route-resetPassword form input[type="hidden"][value="' . $user->getId() . '"]');
        $this->assertQuery('body.route-resetPassword form input[type="password"][name="new"]');
        $this->assertQuery('body.route-resetPassword form input[type="password"][name="verify"]');
    }

    /**
     * GET change, shows form, no validation of user
     */
    public function testChangeGet()
    {
        @$this->dispatch('/account/password/change/foo');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertQuery('body.route-changePassword form input[type="password"][name="new"]');
        $this->assertQuery('body.route-changePassword form input[type="password"][name="verify"]');
    }

    /**
     * POST change, invalid user, error
     */
    public function testChangePostUserDoesNotExist()
    {
        $this->configSuper();

        // invalid user
        $postData = new Parameters(
            array('username' => 'invaliduser')
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        @$this->dispatch('/account/password/change');

        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(500);
        $this->assertQueryContentContains(
            'body.route-changePassword.error',
            'Cannot fetch user invaliduser. Record does not exist.'
        );
    }

    /**
     * POST change, invalid user, error
     */
    public function testChangePostInvalidUser()
    {
        $this->configSuper();

        $victim = new User($this->p4);
        $victim->setId('foo')
               ->setEmail('foo@test.com')
               ->setFullName('Mr Foo')
               ->setPassword('abcd1234')
               ->save();

        $malicious = new User($this->p4);
        $malicious->setId('bar')
                  ->setEmail('bar@test.com')
                  ->setFullName('Mr Bar')
                  ->setPassword('abcd1234')
                  ->save();

        // authenticate the foo user
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter($malicious->getId(), 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // invalid user
        $postData = new Parameters(
            array('username' => $victim->getId())
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/account/password/change');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }

    /**
     * POST change, password no match
     */
    public function testChangePostPasswordNoMatch()
    {
        $this->configSuper();

        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        // authenticate the foo user
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter($user->getId(), 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // invalid user
        $postData = new Parameters(
            array(
                'identity' => $user->getId(),
                'new'      => '1234abcd',
                'verify'   => '5678efgh'
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/account/password/change');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }

    /**
     * POST change, valid user, no password provided, failure
     */
    public function testChangePostNoPassword()
    {
        $this->configSuper();

        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        // authenticate the foo user
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter($user->getId(), 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // valid user
        $postData = new Parameters(
            array('username' => $user->getId())
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        @$this->dispatch('/account/password/change');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }

    /**
     * POST change, valid user, current password provided,
     * new password provided, success
     */
    public function testChangePostValid()
    {
        $this->configSuper();

        $user = new User($this->superP4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->addToGroup('registered')
             ->save();

        // authenticate the foo user
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter($user->getId(), 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // valid user
        $postData = new Parameters(
            array(
                'username' => $user->getId(),
                'current'  => 'abcd1234',
                'new'      => 'efgh5678',
                'verify'   => 'efgh5678'
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        @$this->dispatch('/account/password/change');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertTrue($result->getVariable('isValid'));
    }

    /**
     * GET reset, user, valid token, redirect to change action
     * Note that expired token validation is covered by tests above.
     */
    public function testChangePostInvalidToken()
    {
        $this->configSuper();

        //invalid token
        $user = new User($this->p4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->save();

        $token  = (string)new Uuid;
        $config = $user->getConfig();
        $config->setRawValue('resetToken', $token);
        $config->setRawValue('tokenExpiryTime', strtotime('last week'));
        $config->save();

        // invalid user
        $postData = new Parameters(
            array(
                'username' => $user->getId(),
                'new'      => '1234abcd',
                'verify'   => '1234abcd'
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/account/password/change');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertFalse($result->getVariable('isValid'));
    }

    /**
     * GET reset, user, valid token, redirect to change action
     * Note that expired token validation is covered by tests above.
     */
    public function testChangePostValidToken()
    {
        $this->configSuper();

        //invalid token
        $user = new User($this->superP4);
        $user->setId('foo')
             ->setEmail('foo@test.com')
             ->setFullName('Mr Foo')
             ->setPassword('abcd1234')
             ->addToGroup('registered')
             ->save();

        $token  = (string)new Uuid;
        $config = $user->getConfig();
        $config->setRawValue('resetToken', $token);
        $config->setRawValue('tokenExpiryTime', strtotime('+1 day'));
        $config->save();

        // invalid user
        $postData = new Parameters(
            array(
                'username' => $user->getId(),
                'token'    => $token,
                'new'      => '1234abcd',
                'verify'   => '1234abcd'
            )
        );

        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        @$this->dispatch('/account/password/change');

        $result = $this->getResult();
        $this->assertRoute('changePassword');
        $this->assertResponseStatusCode(200);
        $this->assertTrue($result->getVariable('isValid'));

        // authenticate the foo user with new password
        $auth    = $this->getApplication()->getServiceManager()->get('auth');
        $adapter = new AuthAdapter($user->getId(), '1234abcd', $this->p4);
        $auth->authenticate($adapter);
        $this->assertTrue($auth->hasIdentity());
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
