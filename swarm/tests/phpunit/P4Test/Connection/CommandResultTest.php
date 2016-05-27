<?php
/**
 * Test methods for the P4 fielded model iterator.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Connection;

use P4Test\TestCase;
use P4\Connection\CommandResult;

class CommandResultTest extends TestCase
{
    /**
     * Test set and get data.
     */
    public function testSetGetData()
    {
        $result = new CommandResult('test');
        $this->assertSame(
            array(),
            $result->getData(),
            'Expected data after init'
        );
        $this->assertFalse($result->hasData(), 'Expect no data after init');

        $result->setData('bob');
        $this->assertSame(
            array('bob'),
            $result->getData(),
            'Expected data after 1 set'
        );
        $this->assertTrue($result->hasData(), 'Expect data after set 1');

        $result->setData(array('bob', 'fred', 'jane'));
        $this->assertSame(
            array('bob', 'fred', 'jane'),
            $result->getData(),
            'Expected data after 3 set'
        );
        $this->assertTrue($result->hasData(), 'Expect data after set 3');
    }

    /**
     * Test set, add, and get warnings.
     */
    public function testSetAddGetWarnings()
    {
        $result = new CommandResult('test');
        $this->assertSame(
            array(),
            $result->getWarnings(),
            'Expected warnings after init'
        );
        $this->assertFalse($result->hasWarnings(), 'Expect no warnings after init');

        $result->setWarnings('bob');
        $this->assertSame(
            array('bob'),
            $result->getWarnings(),
            'Expected Warnings after 1 set'
        );
        $this->assertTrue($result->hasWarnings(), 'Expect warnings after set 1');

        $result->setWarnings(array('bob', 'fred', 'jane'));
        $this->assertSame(
            array('bob', 'fred', 'jane'),
            $result->getWarnings(),
            'Expected Warnings after 3 set'
        );
        $this->assertTrue($result->hasWarnings(), 'Expect warnings after set 3');

        $result->addWarning('another');
        $this->assertSame(
            array('bob', 'fred', 'jane', 'another'),
            $result->getWarnings(),
            'Expected Warnings after 1 added'
        );
        $this->assertTrue($result->hasWarnings(), 'Expect warnings after add');
    }

    /**
     * Test set, add, and get errors.
     */
    public function testSetAddGetErrors()
    {
        $result = new CommandResult('test');
        $this->assertSame(
            array(),
            $result->getErrors(),
            'Expected errors after init'
        );
        $this->assertFalse($result->hasErrors(), 'Expect no errors after init');

        $result->setErrors('bob');
        $this->assertSame(
            array('bob'),
            $result->getErrors(),
            'Expected errors after 1 set'
        );
        $this->assertTrue($result->hasErrors(), 'Expect errors after set 1');

        $result->setErrors(array('bob', 'fred', 'jane'));
        $this->assertSame(
            array('bob', 'fred', 'jane'),
            $result->getErrors(),
            'Expected errors after 3 set'
        );
        $this->assertTrue($result->hasErrors(), 'Expect errors after set 3');

        $result->addError('another');
        $this->assertSame(
            array('bob', 'fred', 'jane', 'another'),
            $result->getErrors(),
            'Expected errors after 1 added'
        );
        $this->assertTrue($result->hasErrors(), 'Expect errors after add');
    }
}
