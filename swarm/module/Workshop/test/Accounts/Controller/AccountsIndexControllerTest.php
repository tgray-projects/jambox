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
use P4\Uuid\Uuid;

/**
 * Test that the user signup link is visible as required.
 * Test that when the user signs up, they get a mail with the link.
 * Test that when the user inputs the link, their account is created.
 * Test that when the user inputs the link, but the timeout has elapsed, the
 * account is not created.
 */
class AccountsIndexControllerTest extends TestControllerCase
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

    // Updated test to work with the workshop
    public function testVerifyExpiredToken()
    {
        $this->configSuper();

        $user = array(
            'username'  => 'foo',
            'email'     => 'foo@test.com',
            'password'  => 'test1234'
        );

        $token = $this->createToken($user, 'last week');

        @$this->dispatch('/account/verify/' . $token);

        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains('body.route-verify div div.verify-error', 'Your signup url has expired.');
    }

    // navigate to user link, invalid token, expect error
    public function testVerifyInvalidToken()
    {
        $this->configSuper();

        $token = 'invalidtoken';

        @$this->dispatch('/account/verify/' . $token);

        $this->assertRoute('verify');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains(
            'body.route-verify div div.verify-error',
            'Invalid token submitted.  If you feel this message is in error, '
            . 'please re-register to receive a new token or contact your administrator.'
        );
    }

    // navigate to user link, invalid token, expect error
    public function testVerifyMaliciousToken()
    {
        $this->configSuper();

        $token = '&#46;&#46;&#47;config.php';

        @$this->dispatch('/account/verify/' . $token);

        $this->assertRoute('verify');
        $this->assertResponseStatusCode(200);
        $this->assertQueryContentContains(
            'body.route-verify div div.verify-error',
            'Invalid token submitted.  If you feel this message is in error, '
            . 'please re-register to receive a new token or contact your administrator.'
        );
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
