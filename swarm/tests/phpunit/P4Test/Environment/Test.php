<?php
/**
 * Test methods for the P4 Environment class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Environment;

use P4Test\TestCase;
use P4\Environment\Environment;

class Test extends TestCase
{
    /**
     * Test getArgMax method
     */
    public function testGetArgMax()
    {
        $argMax = Environment::getArgMax();
        $this->assertTrue(isset($argMax), 'Expect argMax to be set');
        $this->assertTrue(is_integer($argMax), 'Expect argMax to be an integer');
        $this->assertTrue($argMax >= 250, 'Expect argMax to be larger than 250 bytes');
    }

    /**
     * test passing an invalid callback to addShutdownCallback
     */
    public function testAddingBadCallback()
    {
        try {
            Environment::addShutdownCallback('bogus');
            $this->fail('Unexpected success adding a bad callback');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals(
                'Cannot add shutdown callback. Given callback is not callable.',
                $e->getMessage(),
                'Expected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(
                "$label: Unexpected Exception (" . get_class($e) . '): ' . $e->getMessage()
            );
        }
    }

    /**
     * test passing an array of invalid callbacks to addShutdownCallback
     */
    public function testAddingBadCallbacks()
    {
        try {
            Environment::setShutdownCallbacks(array('bogus'));
            $this->fail('Unexpected success adding bad callbacks');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals(
                'Cannot add shutdown callback. Given callback is not callable.',
                $e->getMessage(),
                'Expected exception message'
            );
        } catch (\Exception $e) {
            $this->fail(
                "$label: Unexpected Exception (" . get_class($e) . '): ' . $e->getMessage()
            );
        }
    }

    /**
     * Test getShutdownCallbacks.
     */
    public function testGetShutdownCallbacks()
    {
        $callbacks = Environment::getShutdownCallbacks();
        $this->assertTrue(isset($callbacks), 'Expect callbacks to be defined');
        $this->assertTrue(is_array($callbacks), 'Expect callbacks to be an array');
    }
}
