<?php
/**
 * Test methods for the P4 Counter class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Counter;

use P4Test\TestCase;
use P4\Counter\Counter;
use P4\Connection\Connection;
use P4\Connection\Exception\CommandException;

class Test extends TestCase
{
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

        $startEntries = array_combine(
            Counter::fetchAll()->invoke('getId'),
            Counter::fetchAll()->invoke('get')
        );

        if (USE_UNICODE_P4D) {
            $expectedCounters = array('unicode', 'upgrade');
        } else {
            $expectedCounters = array('upgrade');
        }

        $this->assertSame(
            $expectedCounters,
            array_keys($startEntries),
            'Expected list of counters to start'
        );

        // prime the data
        foreach ($entries as $id => $value) {
            $counter = new Counter;
            $counter->setId($id)->set($value);
        }

        // merge in and sort the startEntries
        $entries = array_merge($entries, $startEntries);
        ksort($entries);


        // run a fetch all and validate result
        $counters = Counter::fetchAll();
        foreach ($counters as $counter) {
            $this->assertTrue(
                array_key_exists($counter->getId(), $entries),
                'Expected counter '.$counter->getId().' to exist in our entries array'
            );

            $this->assertSame(
                $entries[$counter->getId()],
                $counter->get(),
                'Expected matching counter value for entry '.$counter->getId()
            );
        }

        // Verify fetchAll with made up option works
        $this->assertSame(
            count($entries),
            count(Counter::fetchAll(array('fooBar' => true))),
            'Expected fetch all with made up option to match'
        );

        // Verify full FETCH_MAXIMUM works
        $this->assertSame(
            array_slice(array_keys($entries), 0, 3),
            Counter::fetchAll(array(Counter::FETCH_MAXIMUM => '3'))->invoke('getId'),
            'Expected fetch all with Maximum to match'
        );
    }

    /**
     * Test fetchAll with 'after' works
     */
    public function testFetchAllAfter()
    {
        $entries = array(
            'test1' => 'value1',
            'test2' => 'value2',
            'test3' => 'value3',
            'test4' => 'value4',
            'test5' => 'value5',
            'test6' => 'value6',
        );

        $startEntries = array_combine(
            Counter::fetchAll()->invoke('getId'),
            Counter::fetchAll()->invoke('get')
        );

        // prime the data
        foreach ($entries as $id => $value) {
            $counter = new Counter;
            $counter->setId($id)->set($value);
        }

        $after = Counter::fetchAll(
            array(
                 Counter::FETCH_BY_NAME => 'test*',
                 Counter::FETCH_AFTER   => 'test3'
            )
        );
        $this->assertSame(
            array_slice(array_keys($entries), 3),
            $after->invoke('getId'),
            'expected results to start after test3'
        );
    }

    /**
     * Ensure calling fetchAll() with FETCH_BY_NAME flag works.
     */
    public function testFetchAllWithOptions()
    {
        // add counters to test
        $counters = array(
            'testcounter1',
            'testcounter2',
            'testcounter3',
            'testa',
            'testb',
            'testc',
            'counter1',
            'counter2',
            'testcounter4'
        );

        foreach ($counters as $id) {
            $counter = new Counter;
            $counter->setId($id)
                    ->set(1);
        }

        // define tests
        $tests = array(
            array(
                'pattern'   => 'testc',
                'expected'  => array(
                    'testc'
                )
            ),
            array(
                'pattern'   => 'testc*',
                'expected'  => array(
                    'testcounter1',
                    'testcounter2',
                    'testcounter3',
                    'testc',
                    'testcounter4'
                )
            ),
            array(
                'pattern'   => '*stc*',
                'expected'  => array(
                    'testcounter1',
                    'testcounter2',
                    'testcounter3',
                    'testcounter4',
                    'testc'
                )
            )
        );

        // run tests
        foreach ($tests as $test) {
            $options = array(
                Counter::FETCH_BY_NAME => $test['pattern']
            );

            $result = Counter::fetchAll($options)->invoke('getId');

            // sort resulting arrays as counters may come in different order
            // from the server
            sort($result);
            sort($test['expected']);

            $this->assertSame(
                $test['expected'],
                $result
            );
        }
    }

    /**
     * Test calling set without an ID
     */
    public function testSetNoId()
    {
        try {
            $counter = new Counter;
            $counter->set('test');

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
        $counter = new Counter();
        $counter->setId('test')->set('testValue');

        $counter = Counter::fetch('test');

        $this->assertSame(
            'test',
            $counter->getId(),
            'Expected matching Id'
        );

        $this->assertSame(
            'testValue',
            $counter->get(),
            'Expected matching value'
        );

        Counter::fetch('test')->set('testValue2');

        $this->assertSame(
            'testValue',
            $counter->get(),
            'Expected cached value after outside modification'
        );

        $this->assertSame(
            'testValue2',
            $counter->get(true),
            'Expected matching value after outside modification'
        );

        $counter = new Counter;
        $this->assertSame(
            null,
            $counter->get(),
            'Expected no-id counter value to match'
        );

        $counter->setId('newCounter');
        $this->assertSame(
            null,
            $counter->get(),
            'Expected non-existent counter value to match'
        );
    }

    /**
     * Test a good call to fetch
     */
    public function testFetch()
    {
        $counter = new Counter();
        $counter->setId('test')->set('testValue');

        $counter = Counter::fetch('test');

        $this->assertSame(
            'test',
            $counter->getId(),
            'Expected matching Id'
        );

        $this->assertSame(
            'testValue',
            $counter->get(),
            'Expected matching value'
        );
    }

    /**
     * Test fetch of non-existent record
     */
    public function testNonExistentFetch()
    {
        // ensure fetch fails for a non-existant counter.
        try {
            Counter::fetch('alskdfj2134');
            $this->fail("Fetch should fail for a non-existant counter.");
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
            Counter::fetch('te/st');
            $this->fail("Fetch should fail for a il-formated counter.");
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
            // ensure fetch fails for a non-existant counter.
            try {
                $counter = new Counter;
                $counter->setId($test['value']);
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
        // ensure id-exists returns false for ill formatted counter
        $this->assertFalse(Counter::exists("-alsdjf"), "Leading - counter id should not exist.");
        $this->assertFalse(Counter::exists("als/djf"), "Forward slash counter id should not exist.");

        // ensure id-exists returns false for non-existant counter
        $this->assertFalse(Counter::exists("alsdjf"), "Given counter id should not exist.");

        // create counter and ensure it exists.
        $group = new Counter;
        $group->setId('test')
              ->set('tester');
        $this->assertTrue(Counter::exists("test"), "Given counter id should exist.");
    }

    /**
     * Test the increment function
     */
    public function testIncrement()
    {
        $counter = new Counter;

        // Test counter that already exists, starting at 0
        $counter->setId('existing')->set(0);
        $this->assertSame(
            "1",
            $counter->increment(),
            'Expected matching value when starting at 0'
        );
        $this->assertSame(
            "1",
            Counter::fetch('existing')->get(),
            'Expected matching value when starting at 0 on independent fetch'
        );
        $this->assertSame(
            "2",
            $counter->increment(),
            'Expected matching value after second increment'
        );
        $this->assertSame(
            "2",
            Counter::fetch('existing')->get(),
            'Expected matching value after second increment on independent fetch'
        );

        // Test counter that already exists starting at 1
        $counter->set(1);
        $this->assertSame(
            "2",
            $counter->increment(),
            'Expected matching value when starting at 2'
        );

        // Test increment will create a counter if it doesn't exist
        $counter = new Counter;
        $counter->setId('newCounter');
        $this->assertSame(
            "1",
            $counter->increment(),
            'Expected matching value for new counter'
        );
        $this->assertSame(
            "1",
            Counter::fetch('newCounter')->get(),
            'Expected matching value for new counter on independent fetch'
        );
    }

    /**
     * Test the increment function with bad starting value
     */
    public function testBadIncrement()
    {
        // Test counter that already exists, starting at 'bad'
        try {
            $counter = new Counter;
            $counter->setId('existing')->set('bad');
            $counter->increment();
            $this->fail("Increment should fail for a il-valued counter.");
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
        $counter = new Counter;
        $counter->setId('test')->set('testValue');

        $this->assertTrue(Counter::exists('test'), 'expected test entry to exist');

        $counter->delete('test');
        $this->assertFalse(Counter::exists('test'), 'expected test entry was deleted');

        $counters = Counter::fetchAll();
        $this->assertFalse(
            in_array('test', $counters->invoke('getId')),
            'expected deleted entry would not be returned by fetchall'
        );
    }

    /**
     * Test the delete function with non-existent id
     */
    public function testMissingIdDelete()
    {
        try {
            $counter = new Counter;
            $counter->setId('missing')->delete();
            $this->fail("Delete should fail for a missing counter.");
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
            $counter = new Counter;
            $counter->delete();
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

    /**
     * Test the force option.
     */
    public function testForce()
    {
        // ensure 'security' counter protected.
        $counter = new Counter;
        $counter->setId('security');
        try {
            $counter->set(1);
            $this->fail("Expected exception");
        } catch (CommandException $e) {
            $this->assertTrue(true);
        }

        // set a protected counter.
        $counter->set(1, true);
        $this->assertSame(1, (int) $counter->get(), "Expected security level 1");

        // now try to delete it.
        try {
            $counter->delete();
            $this->fail("Expected exception");
        } catch (CommandException $e) {
            $this->assertTrue(true);
        }

        // delete with force.
        $counter->delete(true);
        $this->assertFalse(
            Counter::exists('security'),
            "Expected 'security' counter to be deleted."
        );
    }
}
