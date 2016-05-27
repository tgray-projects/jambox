<?php
/**
 * Test methods for the P4 Key class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Key;

use P4Test\TestCase;
use P4\Key\Key;
use P4\Counter\Counter;
use P4\Connection\Connection;
use P4\Connection\Exception\CommandException;

class Test extends TestCase
{
    /**
     * Verify keys don't mingle with counters
     */
    public function testNoCounterOverlap()
    {
        $key = new Key;
        $key->setId('k1')->set('test');
        $key->setId('k2')->set('test');

        $counter = new Counter;
        $counter->setId('c1')->set('test');
        $counter->setId('c2')->set('test');

        $this->assertSame(
            array('k1', 'k2'),
            Key::fetchAll()->invoke('getId'),
            'Expected to see all keys but nothing else'
        );
    }

    /**
     * Test fetchAll method
     */
    public function testFetchAll()
    {
        $entries = array(
            'test1' => 'value1',
            'test2' => 'value2',
            'test3' => 'value3',
            'test4' => 'value4',
            'test5' => 'value5',
            'test6' => 'value6',
        );

        $this->assertSame(
            0,
            count(Key::fetchAll()),
            'Expected matching number of entries to start'
        );

        // prime the data
        foreach ($entries as $id => $value) {
            $key = new Key;
            $key->setId($id)->set($value);
        }

        // run a fetch all and validate result
        $keys = Key::fetchAll();
        foreach ($keys as $key) {
            $this->assertTrue(
                array_key_exists($key->getId(), $entries),
                'Expected key '.$key->getId().' to exist in our entries array'
            );

            $this->assertSame(
                $entries[$key->getId()],
                $key->get(),
                'Expected matching key value for entry '.$key->getId()
            );
        }

        // Verify fetchAll with made up option works
        $this->assertSame(
            count($entries),
            count(Key::fetchAll(array('fooBar' => true))),
            'Expected fetch all with made up option to match'
        );

        // Verify full FETCH_MAXIMUM works
        $this->assertSame(
            array_slice(array_keys($entries), 0, 3),
            Key::fetchAll(array(Key::FETCH_MAXIMUM => '3'))->invoke('getId'),
            'Expected fetch all with Maximum to match'
        );
    }

    /**
     * Ensure calling fetchAll() with FETCH_BY_NAME flag works.
     */
    public function testFetchAllWithOptions()
    {
        // add keys to test
        $keys = array(
            'testkey1',
            'testkey2',
            'testkey3',
            'testa',
            'testb',
            'testk',
            'key1',
            'key2',
            'testkey4'
        );

        foreach ($keys as $id) {
            $key = new Key;
            $key->setId($id)
                ->set(1);
        }

        // define tests
        $tests = array(
            array(
                'pattern'   => 'testk',
                'expected'  => array(
                    'testk'
                )
            ),
            array(
                'pattern'   => 'testk*',
                'expected'  => array(
                    'testkey1',
                    'testkey2',
                    'testkey3',
                    'testk',
                    'testkey4'
                )
            ),
            array(
                'pattern'   => '*stk*',
                'expected'  => array(
                    'testkey1',
                    'testkey2',
                    'testkey3',
                    'testkey4',
                    'testk'
                )
            )
        );

        // run tests
        foreach ($tests as $test) {
            $options = array(
                Key::FETCH_BY_NAME => $test['pattern']
            );

            $result = Key::fetchAll($options)->invoke('getId');

            // sort resulting arrays as keys may come in different order
            // from the server
            sort($result);
            sort($test['expected']);

            $this->assertSame(
                $test['expected'],
                $result,
                'for pattern ' . $test['pattern']
            );
        }
    }

    /**
     * Test fetchAll by ids
     */
    public function testFetchAllByIds()
    {
        $entries = array(
            'test1' => 'value1',
            'test2' => 'value2',
            'test3' => 'value3',
            'test4' => 'value4',
            'test5' => 'value5',
            'test6' => 'value6',
        );

        // prime the data
        foreach ($entries as $id => $value) {
            $key = new Key;
            $key->setId($id)->set($value);
        }

        $this->assertSame(
            array('test1', 'test3', 'test4', 'test6'),
            Key::fetchAll(
                array(Key::FETCH_BY_IDS => array('test1', 'test3', 'test4', 'test6', 'test-bad'))
            )->invoke('getId'),
            'expected matching ids'
        );
    }

    /**
     * Test calling set without an ID
     */
    public function testSetNoId()
    {
        try {
            $key = new Key;
            $key->set('test');

            $this->fail('unexpected success');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\P4\Exception $e) {
            $this->assertSame(
                "Cannot set value. No id has been set.",
                $e->getMessage(),
                'unexpected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(': unexpected exception ('. get_class($e) .') '. $e->getMessage());
        }
    }

    /**
     * Test the get value function
     */
    public function testGetSet()
    {
        $key = new Key();
        $key->setId('test')->set('testValue');

        $key = Key::fetch('test');

        $this->assertSame(
            'test',
            $key->getId(),
            'Expected matching Id'
        );

        $this->assertSame(
            'testValue',
            $key->get(),
            'Expected matching value'
        );

        Key::fetch('test')->set('testValue2');

        $this->assertSame(
            'testValue',
            $key->get(),
            'Expected cached value after outside modification'
        );

        $this->assertSame(
            'testValue2',
            $key->get(true),
            'Expected matching value after outside modification'
        );

        $key = new Key;
        $this->assertSame(
            null,
            $key->get(),
            'Expected no-id key value to match'
        );

        $key->setId('newKey');
        $this->assertSame(
            null,
            $key->get(),
            'Expected non-existent key value to match'
        );
    }

    /**
     * Test a good call to fetch
     */
    public function testFetch()
    {
        $key = new Key();
        $key->setId('test')->set('testValue');

        $key = Key::fetch('test');

        $this->assertSame(
            'test',
            $key->getId(),
            'Expected matching Id'
        );

        $this->assertSame(
            'testValue',
            $key->get(),
            'Expected matching value'
        );
    }

    /**
     * Test fetch of non-existent record
     */
    public function testNonExistentFetch()
    {
        // ensure fetch fails for a non-existant key.
        try {
            Key::fetch('alskdfj2134');
            $this->fail("Fetch should fail for a non-existant key.");
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\P4\Exception $e) {
            $this->assertSame(
                "Cannot fetch entry. Id does not exist.",
                $e->getMessage(),
                'unexpected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(': unexpected exception ('. get_class($e) .') '. $e->getMessage());
        }
    }

    /**
     * Test fetch of bad id record
     */
    public function testBadIdFetch()
    {
        // ensure fetch fails for a bad Id.
        try {
            Key::fetch('te/st');
            $this->fail("Fetch should fail for a il-formated key.");
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(
                "Must supply a valid id to fetch.",
                $e->getMessage(),
                'unexpected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(': unexpected exception ('. get_class($e) .') '. $e->getMessage());
        }
    }

    /**
     * test bad values for setId
     */
    public function testBadSetId()
    {
        $tests = array(
            array(
                'title' => __LINE__." leading minus",
                'value' => '-test'
            ),
            array(
                'title' => __LINE__." forward slash",
                'value' => 'te/st'
            ),
            array(
                'title' => __LINE__." all numeric",
                'value' => '1234'
            ),
        );

        foreach ($tests as $test) {
            // ensure fetch fails for a non-existant key.
            try {
                $key = new Key;
                $key->setId($test['value']);
                $this->fail($test['title'].': unexpected success');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    "Cannot set id. Id is invalid.",
                    $e->getMessage(),
                    $test['title'].': unexpected exception message'
                );
            } catch (\Exception $e) {
                $this->fail($test['title'].': : unexpected exception ('. get_class($e) .') '. $e->getMessage());
            }
        }
    }

    /**
     * Test exists
     */
    public function testExists()
    {
        // ensure id-exists returns false for ill formatted key
        $this->assertFalse(Key::exists("-alsdjf"), "Leading - key id should not exist.");
        $this->assertFalse(Key::exists("als/djf"), "Forward slash key id should not exist.");

        // ensure id-exists returns false for non-existant key
        $this->assertFalse(Key::exists("alsdjf"), "Given key id should not exist.");

        // create key and ensure it exists.
        $group = new Key;
        $group->setId('test')
            ->set('tester');
        $this->assertTrue(Key::exists("test"), "Given key id should exist.");
    }

    /**
     * Test the increment function
     */
    public function testIncrement()
    {
        $key = new Key;

        // Test key that already exists, starting at 0
        $key->setId('existing')->set(0);
        $this->assertSame(
            "1",
            $key->increment(),
            'Expected matching value when starting at 0'
        );
        $this->assertSame(
            "1",
            Key::fetch('existing')->get(),
            'Expected matching value when starting at 0 on independent fetch'
        );
        $this->assertSame(
            "2",
            $key->increment(),
            'Expected matching value after second increment'
        );
        $this->assertSame(
            "2",
            Key::fetch('existing')->get(),
            'Expected matching value after second increment on independent fetch'
        );

        // Test key that already exists starting at 1
        $key->set(1);
        $this->assertSame(
            "2",
            $key->increment(),
            'Expected matching value when starting at 2'
        );

        // Test increment will create a key if it doesn't exist
        $key = new Key;
        $key->setId('newKey');
        $this->assertSame(
            "1",
            $key->increment(),
            'Expected matching value for new key'
        );
        $this->assertSame(
            "1",
            Key::fetch('newKey')->get(),
            'Expected matching value for new key on independent fetch'
        );
    }

    /**
     * Test the increment function with bad starting value
     */
    public function testBadIncrement()
    {
        // Test key that already exists, starting at 'bad'
        try {
            $key = new Key;
            $key->setId('existing')->set('bad');
            $key->increment();
            $this->fail("Increment should fail for a il-valued key.");
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\P4\Exception $e) {
            $this->assertSame(
                "Command failed: Can't increment counter 'existing' - value is not numeric.",
                $e->getMessage(),
                'unexpected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(': unexpected exception ('. get_class($e) .') '. $e->getMessage());
        }
    }

    /**
     * Test the delete function
     */
    public function testDelete()
    {
        $key = new Key;
        $key->setId('test')->set('testValue');

        $this->assertTrue(Key::exists('test'), 'expected test entry to exist');

        $key->delete('test');
        $this->assertFalse(Key::exists('test'), 'expected test entry was deleted');

        $keys = Key::fetchAll();
        $this->assertFalse(
            in_array('test', $keys->invoke('getId')),
            'expected deleted entry would not be returned by fetchall'
        );
    }

    /**
     * Test the delete function with non-existent id
     */
    public function testMissingIdDelete()
    {
        try {
            $key = new Key;
            $key->setId('missing')->delete();
            $this->fail("Delete should fail for a missing key.");
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\P4\Exception $e) {
            $this->assertSame(
                "Cannot delete entry. Id does not exist.",
                $e->getMessage(),
                'unexpected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(': unexpected exception ('. get_class($e) .') '. $e->getMessage());
        }
    }

    /**
     * Test the delete function with no id
     */
    public function testNoIdDelete()
    {
        try {
            $key = new Key;
            $key->delete();
            $this->fail("Delete should fail when no id is set.");
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\P4\Exception $e) {
            $this->assertSame(
                "Cannot delete. No id has been set.",
                $e->getMessage(),
                'unexpected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(': unexpected exception ('. get_class($e) .') '. $e->getMessage());
        }
    }
}
