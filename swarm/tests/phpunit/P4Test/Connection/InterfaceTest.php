<?php
/**
 * Test methods for the P4 api interface.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Connection;

use P4Test\TestCase;
use P4\Connection\Connection;
use P4\Connection\Exception\LoginException;
use P4\Spec\User;

abstract class InterfaceTest extends TestCase
{
    /**
     * Test that connecting / disconnecting works.
     */
    public function testConnectAndDisconnect()
    {
        $this->p4->connect();
        $this->assertTrue($this->p4->isConnected());
        $this->p4->disconnect();
        $this->assertFalse($this->p4->isConnected());

        // try connecting to a bogus server.
        // should throw an exception.
        $this->p4->setPort('laskdjfskdlafj:4231');
        try {
            $this->p4->connect();
            $this->fail('Expect failure with a bogus server.');
        } catch (\P4\Connection\Exception\ConnectException $e) {
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '. $e->getMessage());
        }
    }

    /**
     * Test setting/getting port.
     */
    public function testPort()
    {
        $this->p4->setPort('hostname.something.com:5555');
        $this->assertSame('hostname.something.com:5555', $this->p4->getPort(), 'Expect just-set port');

        // test port via constructor.
        $type = get_class($this->p4);
        $p4   = Connection::factory('1234', null, null, null, null, $type);
        $this->assertSame($p4->getPort(), '1234', 'Expect factory-set port.');

        // getPort should always return a string
        $this->p4->setPort(3333);
        $this->assertTrue(is_string($this->p4->getPort()));
        $this->assertSame($this->p4->getPort(), '3333', 'Expect correct string.');
        $this->assertEquals($this->p4->getPort(), 3333, 'Expect correct numeric value.');
        $this->assertNotSame($this->p4->getPort(), 3333, 'Expect port to be non-numeric.');
    }

    /**
     * Test setting/getting user.
     */
    public function testUser()
    {
        $this->p4->setUser('john_doe');
        $this->assertSame($this->p4->getUser(), 'john_doe', 'Expected user.');

        // test setting user via constructor.
        $type = get_class($this->p4);
        $p4   = Connection::factory(null, 'jdoe', null, null, null, $type);
        $this->assertSame($p4->getUser(), 'jdoe', 'Expected factory user.');

        // test user name acceptance
        $tests = array(
            // valid cases
            array('name' => 'jdoe',   'valid' => true),
            array('name' => 'jdoe.',  'valid' => true),
            array('name' => 'jdoe..', 'valid' => true),
            array('name' => 'j_doe',  'valid' => true),
            array('name' => 'jdoe"',  'valid' => true),
            array('name' => "jdoe'",  'valid' => true),
            array('name' => 'jdoe&',  'valid' => true),
            array('name' => 'jdoe1',  'valid' => true),
            array('name' => 'jdoe%%', 'valid' => true),
            array('name' => 'j/doe',  'valid' => true),

            // invalid cases
            array(
                'name'  => '1234',
                'valid' => false,
                'error' => 'Username: Purely numeric values are not allowed.'
            ),
            array(
                'name'  => 'john doe',
                'valid' => false,
                'error' => 'Username: Whitespace is not permitted.'
            ),
            array(
                'name'  => 'jdoe*',
                'valid' => false,
                'error' => "Username: Wildcards ('*', '...') are not permitted."
            ),
            array(
                'name'  => 'jdoe...',
                'valid' => false,
                'error' => "Username: Wildcards ('*', '...') are not permitted."
            ),
            array(
                'name'  => 'jdoe#',
                'valid' => false,
                'error' => "Username: Revision characters ('#', '@') are not permitted."
            ),
            array(
                'name'  => 'jdoe@',
                'valid' => false,
                'error' => "Username: Revision characters ('#', '@') are not permitted."
            ),
        );
        foreach ($tests as $test) {
            try {
                $this->p4->setUser($test['name']);
                if (!$test['valid']) {
                    $this->fail("Expected '". $test['name'] ."' to fail.");
                }
            } catch (\P4\Exception $e) {
                if ($test['valid']) {
                    $this->fail("Expected '". $test['name'] ."' to succeed: ". $e->getMessage());
                } else {
                    $this->assertEquals(
                        $test['error'],
                        $e->getMessage(),
                        'Expected error message'
                    );
                }
            } catch (\Exception $e) {
                $this->fail('Unexpected exception: '. $e->getMessage());
            }
        }
    }

    /**
     * Test setting/getting client.
     */
    public function testClient()
    {
        $this->p4->setClient('test-client');
        $this->assertSame('test-client', $this->p4->getClient(), 'Expected client.');
        $this->assertNotSame('blah', $this->p4->getClient(), 'Unexpected client.');

        // test setting client via constructor.
        $type = get_class($this->p4);
        $p4   = Connection::factory(null, null, 'jdoes_client', null, null, $type);
        $this->assertSame($p4->getClient(), 'jdoes_client', 'Expected factory client.');

        // test user name acceptance
        $tests = array(
            // valid cases
            array('client' => 'johnsclient',    'valid' => true),
            array('client' => 'johnsClient',    'valid' => true),
            array('client' => 'johns_client',   'valid' => true),
            array('client' => 'johns_client.',  'valid' => true),
            array('client' => 'johns_client..', 'valid' => true),
            array('client' => 'johns_client"',  'valid' => true),
            array('client' => "johns_client'",  'valid' => true),
            array('client' => 'johns_client&',  'valid' => true),
            array('client' => 'johns_client1',  'valid' => true),

            // invalid cases
            array(
                'client' => '1234',
                'valid'  => false,
                'error'  => 'Client name: Purely numeric values are not allowed.'
            ),
            array(
                'client' => 'johns client',
                'valid'  => false,
                'error'  => 'Client name: Whitespace is not permitted.'
            ),
            array(
                'client' => 'johns_client*',
                'valid'  => false,
                'error' => "Client name: Wildcards ('*', '...') are not permitted."
            ),
            array(
                'client' => 'johns_client...',
                'valid'  => false,
                'error' => "Client name: Wildcards ('*', '...') are not permitted."
            ),
            array(
                'client' => 'johns_client%%',
                'valid'  => false,
                'error' => "Client name: Positional specifiers ('%%x') are not permitted."
            ),
            array(
                'client' => 'johns_client#',
                'valid'  => false,
                'error'  => "Client name: Revision characters ('#', '@') are not permitted."
            ),
            array(
                'client' => 'johns_client@',
                'valid'  => false,
                'error'  => "Client name: Revision characters ('#', '@') are not permitted."
            ),
        );
        foreach ($tests as $test) {
            try {
                $this->p4->setClient($test['client']);
                if (!$test['valid']) {
                    $this->fail("Expected '". $test['client'] ."' to fail.");
                }
            } catch (\P4\Exception $e) {
                if ($test['valid']) {
                    $this->fail("Expected '". $test['client'] ."' to succeed: ". $e->getMessage());
                } else {
                    $this->assertEquals(
                        $test['error'],
                        $e->getMessage(),
                        'Expected error message'
                    );
                }
            } catch (\Exception $e) {
                $this->fail('Unexpected exception: '. $e->getMessage());
            }
        }
    }

    /**
     * Test setting/getting password.
     */
    public function testPassword()
    {
        $this->p4->setPassword('test-password');
        $this->assertSame('test-password', $this->p4->getPassword());
        $this->assertNotSame('blah', $this->p4->getPassword());

        // test setting password via constructor.
        $type = get_class($this->p4);
        $p4   = Connection::factory(null, null, null, 'secret key', null, $type);
        $this->assertTrue($p4->getPassword() == 'secret key');
    }


    /**
     * Test setting/getting ticket.
     */
    public function testTicket()
    {
        $this->p4->setTicket('ALKSJROIEL2134235');
        $this->assertSame('ALKSJROIEL2134235', $this->p4->getTicket(), 'Expected ticket.');
        $this->assertNotSame('blah', $this->p4->getTicket(), 'Unexpected ticket.');

        // test setting ticket via constructor.
        $type = get_class($this->p4);
        $p4   = Connection::factory(null, null, null, null, 'ALKSJROIEL2134235', $type);
        $this->assertSame($p4->getTicket(), 'ALKSJROIEL2134235', 'Expected factory ticket.');
    }

    /**
     * Test Connection identity.
     */
    public function testConnectionIdentity()
    {
        $identity = $this->p4->getConnectionIdentity();
        $this->assertTrue(isset($identity['name']), 'Expect name is set.');
        $this->assertTrue(isset($identity['platform']), 'Expect platform is set.');
        $this->assertTrue(isset($identity['version']), 'Expect version is set.');
        $this->assertTrue(isset($identity['build']), 'Expect build is set.');
        $this->assertTrue(isset($identity['apiversion']), 'Expect apiversion is set.');
        $this->assertTrue(isset($identity['apibuild']), 'Expect apibuild is set.');
        $this->assertTrue(isset($identity['date']), 'Expect date is set.');
        $this->assertTrue(isset($identity['original']), 'Expect original is set.');
    }

    /**
     * Test get info.
     */
    public function testGetInfo()
    {
        $info = $this->p4->getInfo();
        $this->assertTrue(is_array($info), 'Expect info to be an array.');
        $this->assertTrue(isset($info['userName']), 'Expect userName is set.');
        $this->assertTrue(isset($info['clientName']), 'Expect clientName is set.');
        $this->assertTrue(isset($info['clientCwd']), 'Expect clientCwd is set.');
        $this->assertTrue(isset($info['clientHost']), 'Expect clientHost is set.');
        $this->assertTrue(isset($info['clientAddress']), 'Expect clientAddress is set.');
        $this->assertTrue(isset($info['serverAddress']), 'Expect serverAddress is set.');
        $this->assertTrue(isset($info['serverRoot']), 'Expect serverRoot is set.');
        $this->assertTrue(isset($info['serverDate']), 'Expect serverDate is set.');
        $this->assertTrue(isset($info['serverUptime']), 'Expect serverUptime is set.');
        $this->assertTrue(isset($info['serverVersion']), 'Expect serverVersion is set.');
        $this->assertTrue(isset($info['serverLicense']), 'Expect serverLicense is set.');

        // test that cache is cleared when connection params change.
        $this->p4->setClient('a-client');
        $info2 = $this->p4->getInfo();
        $this->assertNotEquals(serialize($info), serialize($info2), 'Unexpected client match.');
    }

    /**
     * Test client root accessor.
     */
    public function testGetClientRoot()
    {
        $root = $this->p4->getClientRoot();
        $info = $this->p4->getInfo();
        if ($root) {
            $this->assertSame($root, $info['clientRoot'], 'Expect root to match info.');
        } else {
            $this->assertFalse(isset($info['clientRoot']), 'Unexpected clientRoot with no root.');
        }
    }

    /**
     * Test login/authentication.
     */
    public function testLogin()
    {
        $this->assertTrue(
            $this->p4->isAuthenticated(),
            'Expected user to be authenticated'
        );

        try {
            $ticket = $this->p4->login();
            $this->assertTrue(strlen($ticket) > 0, "Expected login ticket");
        } catch (LoginException $e) {
            $this->fail("Expected login to succeed");
        }

        try {
            $this->p4->setPassword('alskdfj23523');
            $ticket = $this->p4->login();
            $this->fail("Expected login failure");
        } catch (LoginException $e) {
            $this->assertSame(
                LoginException::CREDENTIAL_INVALID,
                $e->getCode(),
                "Expected credential invalid login exception"
            );
        }

        // erase the successful login ticket so that the password is re-evaluated
        $this->p4->setTicket(null);
        $this->assertFalse(
            $this->p4->isAuthenticated(),
            'Expected user not to be authenticated'
        );

        try {
            $this->p4->setUser('laksdjflkasdfj');
            $ticket = $this->p4->login();
            $this->fail("Expected login failure");
        } catch (LoginException $e) {
            $this->assertSame(
                LoginException::IDENTITY_NOT_FOUND,
                $e->getCode(),
                "Expected identity not found login exception"
            );
        }
    }

    /**
     * Test running a command.
     */
    public function testRun()
    {
        $result = $this->p4->run('users', null, null, false);
        $this->assertFalse($result->isTagged(), 'Expect untagged result.');

        $result = $this->p4->run('users');
        $this->assertTrue($result->isTagged(), 'Expect tagger result.');

        $data = $result->getData();
        $this->assertSame(
            serialize($result->getData(0)),
            serialize($data[0]),
            'Expect getData match.'
        );
        $this->assertSame(
            $result->getData(0, 'User'),
            $data[0]['User'],
            'Expect getData User match.'
        );
        $this->assertSame($result->getCommand(), 'users', 'Expect command match.');
    }

    /**
     * Test super user detection.
     */
    public function testSuperUser()
    {
        // instance _p4 object runs as super.
        $this->assertTrue($this->p4->isSuperUser(), 'Connection should have super user privs.');

        // create un-privileged user.
        $user = $this->p4->run('user', array('-o', 'jdoe'));
        $this->p4->run('user', array('-i', '-f'), $user->getData(-1));

        // connect as un-privileged user.
        $class = get_class($this->p4);
        $p4 = new $class;
        $p4->setUser('jdoe');
        $p4->setPort($this->p4->getPort());
        $p4->connect();
        $this->assertFalse($p4->isSuperUser(), 'Connection should not have super user privs.');
    }

    /**
     * Test the security level.
     */
    public function testSecurityLevel()
    {
        // should be zero to start.
        $this->assertTrue($this->p4->getSecurityLevel() == 0, "Expected security level zero");

        $counter = new \P4\Counter\Counter;
        $counter->setId('security');

        // 1
        $counter->set(1, true);
        $this->assertTrue($this->p4->getSecurityLevel() == 1, "Expected security level one");

        // 2
        $counter->set(2, true);

        // once the security counter is increased to 2, the current user's password must be reset.
        // see the Perforce System Administrator's Guide, Chapter 3, Server Security Levels
        // http://www.perforce.com/perforce/doc.current/manuals/p4sag/03_superuser.html#1081537
        //
        // Unfortunately, this doesn't work the way you'd hope. The desire would be to write
        // something similar to:
        //
        //     $user = \P4\Spec\User::fetch($this->p4->getUser())
        //                    ->setPassword('testing321')
        //                    ->save();
        //
        // Calling setPassword() invokes other Perforce commands prior to the password command
        // (for lazy loading, verifying protections, etc.), and these commands will fail due
        // to the password reset requirement.
        //
        // So, we need to directly execute the password command.
        $newPassword = 'newPassword123';
        $this->p4->run(
            'password',
            null,
            array(
                $this->p4->getPassword(),
                $newPassword,
                $newPassword
            )
        );
        $this->p4->setPassword($newPassword);

        $this->assertTrue($this->p4->getSecurityLevel() == 2, "Expected security level two");

        // 3
        $counter->set(3, true);

        // must login.
        $this->p4->login();

        $this->assertTrue($this->p4->getSecurityLevel() == 3, "Expected security level three");
    }

    /**
     * Test app name
     */
    public function testAppName()
    {
        $root = $this->getP4Params('serverRoot');
        $port = $this->p4->getPort() . ' -vrpc=3 -L ' . $root . '/test-log';
        $p4   = Connection::factory(
            $port,
            $this->getP4Params('user'),
            $this->getP4Params('client'),
            $this->getP4Params('password')
        );

        // verify can set/get app name
        $p4->setAppName('some-name');
        $this->assertSame('some-name', $p4->getAppName());

        // verify can set/get program name
        $p4->setProgName('my-prog');
        $this->assertSame('my-prog', $p4->getProgName());

        // verify can set/get program version
        $p4->setProgVersion('2013.1.WHATEVER/123456');
        $this->assertSame('2013.1.WHATEVER/123456', $p4->getProgVersion());

        // verify server sees app name
        $p4->getInfo();
        $log = file_get_contents($root . '/test-log');
        $this->assertTrue(strpos($log, 'app = some-name') !== false, "Looking for app name in log.");
    }

    /**
     * Test the service locator facilities of the connection
     */
    public function testServiceLocator()
    {
        $p4 = $this->p4;

        // should throw for non-existent services
        try {
            $p4->getService('foo');
            $this->fail();
        } catch (\P4\Connection\Exception\ServiceNotFoundException $e) {
            $this->assertTrue(true);
        }

        // should require a object or callable
        try {
            $p4->setService('foo', 'bar');
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // configure a basic service
        $service = new \stdClass;
        $service->value = true;
        $p4->setService('foo', $service);
        $this->assertSame($service, $p4->getService('foo'));

        // configure a service factory
        $factory = function ($p4, $name) {
            $service = new \stdClass;
            $service->p4    = $p4;
            $service->name  = $name;
            return $service;
        };
        $p4->setService('baz', $factory);
        $service = $p4->getService('baz');
        $this->assertTrue($service instanceof \stdClass);
        $this->assertSame($p4,   $service->p4);
        $this->assertSame('baz', $service->name);

        // once created, service should be reused
        $this->assertTrue($service === $p4->getService('baz'));
    }

    public function testIsAuthenticated()
    {
        $user = new User($this->p4);
        $user->setId('jdoe');
        $user->setPassword('abc123');
        $user->setEmail('jdoe@example.com');
        $user->setFullName('Jonathan H. Doe');
        $user->save();

        $user2 = new User($this->p4);
        $user2->setId('jdoe2');
        $user2->setEmail('jdoe2@example.com');
        $user2->setFullName('Jonathan H. DEUX');
        $user2->save();

        // test valid password
        $p4 = Connection::factory($this->p4->getPort(), 'jdoe', null, 'abc123');
        $this->assertTrue($p4->isAuthenticated());

        // test invalid password
        $p4 = Connection::factory($this->p4->getPort(), 'jdoe', null, 'xyz789');
        $this->assertFalse($p4->isAuthenticated());

        // test valid ticket
        $p4 = Connection::factory($this->p4->getPort(), 'jdoe', null, 'abc123');
        $p4->login();
        $p4->setPassword(null);
        $this->assertNotNull($p4->getTicket());
        $p4->setTicket($p4->getTicket());
        $this->assertTrue($p4->isAuthenticated());

        // test invalid ticket
        $ticket = $p4->getTicket();
        $p4->run('logout');
        $p4->disconnect();
        $this->assertFalse($p4->isAuthenticated());

        // test edge case of valid blank password
        $p4 = Connection::factory($this->p4->getPort(), 'jdoe2', null, null);
        $this->assertTrue($p4->isAuthenticated());

        // test edge case of invalid password (when login not required)
        $p4 = Connection::factory($this->p4->getPort(), 'jdoe2', null, 'abc123');
        $this->assertFalse($p4->isAuthenticated());

        // test invalid ticket (recycling invalid ticket from above)
        $p4 = Connection::factory($this->p4->getPort(), 'jdoe2', null, null, $ticket);
        $this->assertFalse($p4->isAuthenticated());
    }
}
