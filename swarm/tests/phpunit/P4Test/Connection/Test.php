<?php
/**
 * Test methods for the P4 Connection.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Connection;

use P4\Connection\CommandResult;
use P4\Connection\Exception\CommandException;
use P4Test\TestCase;
use P4\Connection\Connection;
use P4\Environment\Environment;

class Test extends TestCase
{
    /**
     * Test setDefaultConnection.
     */
    public function testSetDefaultConnection()
    {
        // test an invalid connection
        try {
            Connection::setDefaultConnection(null);
            $this->fail('Unexpected success setting empty default connection.');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        // test a valid connection.
        $connection = new \P4\Connection\Extension;
        Connection::setDefaultConnection($connection);
        $this->assertSame(
            $connection,
            Connection::getDefaultConnection(),
            'Expected connection'
        );
    }

    /**
     * Test isValidType.
     */
    public function testIsValidType()
    {
        $tests = array(
            ''                              => false,
            'bogus'                         => false,
            'P4\\File\\File'                => false,
            'P4\\Connection\\Extension'     => true,
        );

        foreach ($tests as $class => $expectation) {
            $this->assertSame(
                $expectation,
                Connection::isValidType($class),
                "Expected result for '$class'"
            );
        }
    }

    /**
     * Test getClientRoot with invalid client.
     */
    public function testGetClientRoot()
    {
        // by default, the test suite can connect; test the normal case
        $connection = $this->createP4Connection();
        $this->assertSame(
            realpath($this->getP4Params('clientRoot') .'/superuser'),
            realpath($connection->getClientRoot()),
            'Expected client root'
        );

        // skip the following test if P4PHP is loaded; we cannot manipulate P4PHP
        // to make this test pass.
        if (!extension_loaded('perforce')) {

            // now override P4 to get unexpected behaviour
            $connection->clearInfo();
            $script  = ASSETS_PATH . '/scripts/serializedArray.';
            $script .= Environment::isWindows() ? 'bat' : 'sh';
            $connection->setP4Path($script);
            $this->assertSame(
                false,
                $connection->getClientRoot(),
                'Expect no root for bogus P4'
            );
        }
    }

    public function timeZoneTranslationProvider()
    {
        return array(
            array('2014/02/26 17:26:31 -0800 PST',                          'America/Los_Angeles'),
            array('2014/02/26 17:35:12 -0800 Pacific Standard Time',        'America/Los_Angeles'),
            array('2014/02/20 16:44:53 -0600 Central Standard Time',        'America/Chicago'),
            array('2014/04/07 19:35:29 -0500 Central Daylight Time',        'America/Chicago'),
            array('2014/02/20 16:44:53 -0500 Eastern Standard Time',        'America/New_York'),
            array('2014/02/20 16:44:53 -0500 EST',                          'America/New_York'),
            array('2014/02/20 16:44:53 -0330 Newfoundland Standard Time',   'America/St_Johns'),
            array('2014/02/20 16:44:53 -0330 NST',                          'America/St_Johns'),
            array('2013/09/17 10:20:21 +1000 EST',                    array('Antarctica/Macquarie', 'Australia/ACT')),

            // and some bunk options to confirm they kaboom
            array('2014/02/26 17:26:31 -0100 MZF'),
            array('2014/02/26 17:26:31 -0200 El Timezono Invalidito'),
        );
    }

    /**
     * Verify that the timezone, for a handful of values, pareses out ok
     * @dataProvider timeZoneTranslationProvider
     */
    public function testGetTimeZone($serverDate, $zone = false)
    {
        // eval a mock object into existence which adds a 'setInfo' function
        $mockCode = 'class P4_ConnectionMock extends \\P4\\Connection\\Extension {
                        public static function setInfo($connection, $key, $value)
                        {
                            $connection->info[$key] = $value;
                        }
                    }';

        if (!class_exists('P4_ConnectionMock')) {
            eval($mockCode);
        }

        \P4_ConnectionMock::setInfo($this->p4, 'serverDate', $serverDate);
        $info = $this->p4->getInfo();
        $this->assertSame($info['serverDate'], $serverDate, 'expected setting time in info to work');

        try {
            $dateTimeZone = $this->p4->getTimeZone();
        } catch (\Exception $e) {
            // if we didn't expect an earth shattering kaboom rethrow
            if ($zone !== false) {
                throw $e;
            }

            // if we did expect an exception, just stop at this point, we're done
            return;
        }

        if (!in_array($dateTimeZone->getName(), (array) $zone)) {
            $this->assertSame(implode(' or ', (array) $zone), $dateTimeZone->getName(), 'unexpected zone');
        }
    }

    public function testPrePostRun()
    {
        $preRuns  = array();
        $postRuns = array();

        $this->p4->addPreRunCallback(
            function () use (&$preRuns) {
                $preRuns[] = func_get_args();
            }
        );
        $this->p4->addPostRunCallback(
            function () use (&$postRuns) {
                $postRuns[] = func_get_args();
            }
        );

        $this->assertSame(array(), $preRuns);
        $this->assertSame(array(), $postRuns);

        try {
            $this->p4->run('user', '-i', 'test-input!');
        } catch (CommandException $e) {
            // expected the exception
        }

        if (!isset($e)) {
            $this->fail('did not get the anticipated command exception');
        }

        // verify pre-run data was correct
        $this->assertSame(1, count($preRuns));
        $this->assertTrue(isset($preRuns[0][0]) && $preRuns[0][0] == $this->p4);
        unset($preRuns[0][0]);
        $this->assertSame(
            array(
                1 => 'user',
                2 => array('-i'),
                3 => 'test-input!',
                4 => true
            ),
            $preRuns[0]
        );

        // verify post-run data was correct
        $this->assertSame(1, count($postRuns));
        $this->assertSame(2, count($postRuns[0]));
        $this->assertTrue(isset($postRuns[0][0]) && $postRuns[0][0] == $this->p4);
        $this->assertTrue(isset($postRuns[0][1]) && $postRuns[0][1] instanceof CommandResult);
        $this->assertSame('user',   $postRuns[0][1]->getCommand());
        $this->assertSame(
            USE_NOISY_TRIGGERS ? array("user-form-in stdout\nuser-form-in stderr") : array(),
            $postRuns[0][1]->getData()
        );
        $this->assertSame(
            array("Error in user specification.\nError detected at line 1.\nSyntax error in 'test-input!'."),
            $postRuns[0][1]->getErrors()
        );
        $this->assertSame(array(),  $postRuns[0][1]->getWarnings());
        $this->assertSame(true,     $postRuns[0][1]->isTagged());
    }
}
