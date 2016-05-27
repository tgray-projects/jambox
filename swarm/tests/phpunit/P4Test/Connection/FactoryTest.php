<?php
/**
 * Test methods for the P4\Connection\Connection class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Connection;

use P4Test\TestCase;
use P4\Connection\Connection;

class FactoryTest extends TestCase
{
    protected $clients;

    /**
     * Test setup.
     */
    public function setUp()
    {
        parent::setUp();

        // create extension client implementation
        $this->clients[] = Connection::factory(
            $this->getP4Params('port'),
            $this->getP4Params('user'),
            $this->getP4Params('client'),
            $this->getP4Params('password'),
            null,
            '\P4\Connection\Extension'
        );
    }

    /**
     * Clear app name static.
     */
    public function tearDown()
    {
        Connection::setAppName(null);

        parent::tearDown();
    }

    /**
     * Test that the factory method functions properly.
     */
    public function testValidTypeCreation()
    {
        // verify that each client created is of the correct type.
        foreach ($this->clients as $client) {
            $this->assertTrue(
                $client instanceof \P4\Connection\ConnectionInterface,
                'Expected client object type'
            );
        }
    }

    /**
     * Attempting to create a P4 connection with a non-existing type should
     * result in an exception being thrown.
     *
     * @expectedException \P4\Exception
     */
    public function testBadTypeCreation()
    {
        $type = 'Bogus_Type';
        $this->assertFalse(class_exists($type), 'Expect bogus class to not exist');
        $connection = Connection::factory(null, null, null, null, null, $type);
    }

    /**
     * Test app name
     */
    public function testAppName()
    {
        Connection::setAppName('test-name');

        $p4 = Connection::factory(
            $this->getP4Params('port'),
            $this->getP4Params('user'),
            $this->getP4Params('client'),
            $this->getP4Params('password')
        );

        $this->assertSame('test-name', $p4->getAppName());
    }

    /**
     * Test program name
     */
    public function testProgName()
    {
        Connection::setProgName('test-program');

        $p4 = Connection::factory(
            $this->getP4Params('port'),
            $this->getP4Params('user'),
            $this->getP4Params('client'),
            $this->getP4Params('password')
        );

        $this->assertSame('test-program', $p4->getProgName());
    }

    /**
     * Test program version
     */
    public function testProgVersion()
    {
        Connection::setProgVersion('test-version');

        $p4 = Connection::factory(
            $this->getP4Params('port'),
            $this->getP4Params('user'),
            $this->getP4Params('client'),
            $this->getP4Params('password')
        );

        $this->assertSame('test-version', $p4->getProgVersion());
    }

    /**
     * Test the Connection identity method.
     */
    public function testConnectionIdentity()
    {
        $identity = Connection::getConnectionIdentity();
        $this->assertTrue(is_array($identity), 'Expect identity array');
        $this->assertSame(sizeof($identity), 8, 'Expect 8 identities');
        $this->assertArrayHasKey('name', $identity, 'Expect name identity');
        $this->assertArrayHasKey('platform', $identity, 'Expect platform identity');
        $this->assertArrayHasKey('version', $identity, 'Expect version identity');
        $this->assertArrayHasKey('build', $identity, 'Expect build identity');
        $this->assertArrayHasKey('apiversion', $identity, 'Expect apiversion identity');
        $this->assertArrayHasKey('apibuild', $identity, 'Expect apibuild identity');
        $this->assertArrayHasKey('date', $identity, 'Expect date identity');
        $this->assertArrayHasKey('original', $identity, 'Expect original identity');
    }
}
