<?php
/**
 * Test methods for the P4 Log class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Log;

use P4Test\TestCase;
use P4\Log\Logger;

class LoggerTest extends TestCase
{
    /**
     * Test logger manipulation.
     */
    public function testBasicOperation()
    {
        Logger::setLogger(null);
        $this->assertFalse(Logger::hasLogger());

        // expect success writing with no logger set.
        Logger::log(Logger::INFO, 'test');

        // set a bad logger.
        try {
            Logger::setLogger('bob');
            $this->fail("Expected exception setting bad logger.");
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // try setting a legit logger.
        $stream = fopen("php://temp", "a");
        $writer = new \Zend\Log\Writer\Stream($stream);
        $logger = new \Zend\Log\Logger;
        $logger->addWriter($writer);
        Logger::setLogger($logger);
        $this->assertTrue(Logger::hasLogger());
        $this->assertSame(
            $logger,
            Logger::getLogger(),
            'Expect the set logger'
        );

        // try logging.
        $logData = stream_get_contents($stream, -1, 0);
        $this->assertTrue(strlen($logData) == 0, 'Expect log to not contain data');
        Logger::log(Logger::INFO, 'test');
        $logData = stream_get_contents($stream, -1, 0);
        $this->assertTrue(strlen($logData) > 0, 'Expect log to contain data');
        $this->assertRegexp('/test/', $logData, 'Expect log message in log');

        Logger::log(null, 'something else');
        $logData = stream_get_contents($stream, -1, 0);
        $this->assertRegexp('/something else/', $logData, 'Expect second log message in log');

        // try logging an exception
        $e = new \InvalidArgumentException('poof');
        Logger::logException('my log message', $e);
        $logData = stream_get_contents($stream, -1, 0);
        $this->assertRegexp('/my log message/', $logData, 'Expect exception message in log');

        // try logging a bogus exception
        Logger::logException('bad', 'badexception');
        $logData = stream_get_contents($stream, -1, 0);
        $this->assertRegexp('/bad/', $logData, 'Expect second exception message in log');
        $this->assertNotRegexp(
            '/badexception/',
            $logData,
            'Expect second exception bad exception message to not be in log'
        );
    }
}
