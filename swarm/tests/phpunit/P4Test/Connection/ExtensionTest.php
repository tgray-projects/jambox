<?php
/**
 * Test methods for the client extension class.
 * Note: inherits tests from interface test.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Connection;

use P4\Connection;

class ExtensionTest extends InterfaceTest
{
    /**
     * Override setUp to ensure that the p4 client being used is of the correct
     * type for these tests.
     */
    public function setUp()
    {
        // skip all tests if the perforce extension is not available.
        if (!extension_loaded('perforce')) {
            $this->markTestSkipped();
        }

        parent::setUp();

        if (!$this->p4 instanceof Connection\ConnectionExtension) {
            $this->p4 = $this->createP4Connection('\P4\Connection\Extension');
            Connection\Connection::setDefaultConnection($this->p4);
        }
    }

    /**
     * Test batchArgs() method.
     */
    public function testBatchArgs()
    {
        $connection = Connection\Connection::getDefaultConnection();

        // for the extension, the argMax should be zero
        $this->assertSame(
            0,
            $connection->getArgMax(),
            "Expected unlimited argMax."
        );

        // create relatively large list of arguments and verify that
        // they are all put in one batch
        $arguments = array();
        while (count($arguments) < 10000) {
            $arguments[] = 'abcd123X';
        }

        $batches = $connection->batchArgs($arguments);
        $this->assertSame(
            1,
            count($batches),
            "Expected all arguments are in one batch."
        );

        $this->assertSame(
            10000,
            count(reset($batches)),
            "Expected number of arguments in the only batch."
        );

        // verify option-limit is enforced.
        $arguments = array();
        while (count($arguments) <= $connection->getOptionLimit()) {
            $arguments[] = '-foo';
        }

        $batches = $connection->batchArgs($arguments);
        $this->assertSame(
            1,
            count($batches),
            "Expected all options are in one batch."
        );

        $arguments[] = '-bar';
        $batches = $connection->batchArgs($arguments);
        $this->assertSame(
            2,
            count($batches),
            "Expected options in two batches."
        );
    }
}
