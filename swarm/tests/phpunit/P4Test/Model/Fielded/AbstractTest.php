<?php
/**
 * Test methods for the P4 FieldedAbstract model. Since we cannot directly call
 * abstract methods, we use P4\File\File or Hidden which extend the abstract.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Model\Fielded;

use P4\Connection\Connection;
use P4\File\File;
use P4Test\TestCase;

class AbstractTest extends TestCase
{
    /**
     * Test connection handling
     */
    public function testConnectionHandling()
    {
        $file = new File;
        $this->assertTrue($file->hasConnection(), 'Expect a connection.');

        // record the current connections, clear them, and make sure
        // our model no longer has a connection
        $originalP4Connection = Connection::getDefaultConnection();
        $originalModelConnection = $file->getConnection();
        Connection::clearDefaultConnection();
        $file->clearConnection();
        $this->assertFalse($file->hasConnection(), 'Expect no connection.');
        try {
            $file->getDefaultConnection();
            $this->fail('Expected default connection to fail.');
        } catch (\P4\Exception $e) {
            $this->assertEquals(
                "Failed to get connection. A default connection has not been set.",
                $e->getMessage(),
                'Expected error message.'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected exception: '. $e->getMessage());
        }

        // establish some valid connections to test with
        $type = get_class($this->p4);
        $p41  = Connection::factory(null, 'jdoe', null, null, null, $type);
        $p42  = Connection::factory(null, 'jdoe2', null, null, null, $type);
        $this->assertNotEquals(serialize($p41), serialize($p42), 'Expect connections to differ.');

        // test setting valid connections
        $file->setConnection($p41);
        $this->assertSame(
            serialize($file->getConnection()),
            serialize($p41),
            'Expect specfied connection #1.'
        );
        $file->setConnection($p42);
        $this->assertSame(
            serialize($file->getConnection()),
            serialize($p42),
            'Expect specfied connection #2.'
        );

        // test setting a bogus connection
        try {
            $file->setConnection('bob');
            $this->fail('Expected bogus connection to fail.');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        // reset connections, and set a default
        $file->clearConnection();
        Connection::setDefaultConnection($p41);
        $this->assertSame(
            serialize($file->getDefaultConnection()),
            serialize($p41),
            'Expect specfied default connection #1.'
        );
        try {
            $file->getConnection();
            $this->fail("Get connection should fail after clear connection called.");
        } catch (\P4\Exception $e) {
            $this->assertTrue(true);
        }

        // now set a connection and check whether default returned
        $file->setConnection($p42);
        $this->assertSame(
            serialize($file->getConnection()),
            serialize($p42),
            'Expect specfied connection not via default.'
        );
        $this->assertSame(
            serialize($file->getDefaultConnection()),
            serialize($p41),
            'Expect default connection to be unchanged.'
        );

        // test setting a bogus default connection
        try {
            Connection::setDefaultConnection('bob');
            $this->fail('Expected bogus default connection to fail.');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        Connection::setDefaultConnection($originalP4Connection);
        $file->clearConnection();
        $file->setConnection($originalModelConnection);
    }

    public function testHidden()
    {
        $shown = array(
            'foo'    => 'foo1',
            'bar'    => 'bar1',
            'baz'    => 'baz1',
        );
        $model = new Hidden;
        $model->set($shown + array('hidden' => 'secret'));

        $this->assertSame(
            array('foo', 'bar', 'baz', 'hidden'),
            $model->getFields(),
            'expected fields to match'
        );
        $this->assertSame(
            'secret',
            $model->get('hidden'),
            'expected direct get to work'
        );
        $this->assertSame(
            $shown,
            $model->toArray(),
            'expected toArray to hide'
        );
        $this->assertSame(
            $shown,
            $model->get(),
            'expected get() to hide'
        );
    }
}
