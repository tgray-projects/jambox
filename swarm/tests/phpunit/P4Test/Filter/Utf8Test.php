<?php
/**
 * Tests for the utf-8 sanitization filter
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Filter;

use \P4\Filter\Utf8 as Utf8Filter;

class Utf8Test extends \PHPUnit_Framework_TestCase
{
    public function testBasicFunction()
    {
        $filter = new Utf8Filter;
        $this->assertSame(
            'test',
            $filter->filter('test')
        );
    }

    /**
     * The invalid utf-8 sequences were taken from:
     * http://www.cl.cam.ac.uk/~mgk25/ucs/examples/UTF-8-test.txt
     *
     * @return  array   various tests to run
     */
    public function utf8InvalidProvider()
    {
        return array(
            'no-problems' => array("this is a plain string!"),
            'empty'       => array(""),
            'utf8-chars'  => array("ÐƉđ"),
            'invalid'     => array("test\xFE\xBF\xBEing", "test���ing"),
            'invalid2'    => array("test\xC0\xAFing", "test��ing"),
            'invalid3'    => array("test\xC1\xBFing", "test��ing"),
            'invalid4'    => array("test\xFC\x80\x80\x80\x80\xAFing", "test������ing"),
            'two-invalid' => array("te\xFE\xBF\xBEst\xC0\xAFing", "te���st��ing"),
            'all-bad'     => array("\xC1\xBF", "��"),
            'a-no-prob'   => array(array("plain string!")),
            'a-prob'      => array(array("test\xFE\xBF\xBEing"), array("test���ing")),
            'a-deep-good' => array(array("a" => array("b" => "fancy ÐƉđ!"))),
            'a-all-bad'   => array(array("a" => array("b" => "\xFE\xBF\xBE")), array("a" => array("b" => "���"))),
            'a-deep-bad'  => array(
                array("a" => array("b" => "a\xFE\xBF\xBEb!")),
                array("a" => array("b" => "a���b!"))
            )
        );
    }

    /**
     * @dataProvider utf8InvalidProvider
     */
    public function testReplaceInvalid($in, $expected = null)
    {
        if ($expected === null) {
            $expected = $in;
        }

        $filter = new Utf8Filter;
        $output = $filter->filter($in);

        $this->assertSame(
            $expected,
            $output,
            'expected matching result'
        );
    }

    /**
     * @return  array   various tests to run
     */
    public function convertProvider()
    {
        return array(
            'no-problems'   => array("this is a plain string!"),
            'smart-quotes'  => array("\x93smart\x94", "\xE2\x80\x9Csmart\xE2\x80\x9D"),
            'mdash'         => array("m\x96dash", "m\xE2\x80\x93dash"),
            'has-utf8'      => array("\x93hasutf8Ɖ", "�hasutf8Ɖ")
        );
    }

    /**
     * @dataProvider convertProvider
     */
    public function testConvertEncoding($in, $expected = null)
    {
        if ($expected === null) {
            $expected = $in;
        }

        $filter = new Utf8Filter;
        $filter->setConvertEncoding(true);
        $output = $filter->filter($in);

        $this->assertSame(
            $expected,
            $output,
            'expected matching result'
        );
    }
}
