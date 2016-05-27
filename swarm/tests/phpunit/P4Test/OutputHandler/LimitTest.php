<?php
/**
 * Test the 'limit' output handler
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\OutputHandler;

use P4Test\TestCase;
use P4\OutputHandler\Limit;

class Test extends TestCase
{
    public function testWasCancelled()
    {
        $handler = new Limit;
        $this->assertFalse($handler->wasCancelled());

        // need a couple of entries to test the following
        for ($i = 0; $i < 10; $i++) {
            $this->p4->run('counter', array('test' . $i, $i));
        }

        // test without exceeding max
        $handler->setMax(99);
        $this->p4->runHandler($handler, 'counters');
        $this->assertFalse($handler->wasCancelled());

        // test with exceeding max
        $handler->setMax(1);
        $this->p4->runHandler($handler, 'counters');
        $this->assertTrue($handler->wasCancelled());
        $handler->setMax(null);

        // reset should clear 'cancelled' flag
        $handler->reset();
        $this->assertFalse($handler->wasCancelled());

        // test with an output callback that doesn't cancel
        $handler->setOutputCallback(
            function () {
                return Limit::HANDLER_HANDLED;
            }
        );
        $this->p4->runHandler($handler, 'counters');
        $this->assertFalse($handler->wasCancelled());

        // test with an output callback that does cancel
        $handler->setOutputCallback(
            function () {
                return Limit::HANDLER_CANCEL;
            }
        );
        $this->p4->runHandler($handler, 'counters');
        $this->assertTrue($handler->wasCancelled());
    }
}
