<?php
/**
 * Test methods for the P4 Validate UserName class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Validate;

use P4Test\TestCase;

class UserNameTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $validator = new \P4\Validate\UserName;
        $this->assertTrue($validator instanceof \P4\Validate\UserName, 'Expected class');
    }

    /**
     * Test isValid.
     */
    public function testIsValid()
    {
        $tests = array(
            array(
                'label'   => __LINE__ .': null',
                'value'   => null,
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'   => __LINE__ .': empty',
                'value'   => '',
                'valid'   => false,
                'error'   => array(
                    'isEmpty' => 'Is an empty string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': numeric',
                'value'   => 123,
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'   => __LINE__ .': number',
                'value'   => '123',
                'valid'   => false,
                'error'   => array(
                    'isNumeric' => 'Purely numeric values are not allowed.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha',
                'value'   => 'abc',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alphanumeric',
                'value'   => 'abc123',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': numericalpha',
                'value'   => '123abc',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with whitespace',
                'value'   => 'a b c',
                'valid'   => false,
                'error'   => array(
                    'hasSpaces' => 'Whitespace is not permitted.',
                ),
            ),
            array(
                'label'   => __LINE__ .': alpha with .',
                'value'   => 'a.b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with ..',
                'value'   => 'a..b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with *',
                'value'   => 'a*b',
                'valid'   => false,
                'error'   => array(
                    'wildcards' => "Wildcards ('*', '...') are not permitted."
                ),
            ),
            array(
                'label'   => __LINE__ .': alpha with ...',
                'value'   => 'a...b',
                'valid'   => false,
                'error'   => array(
                    'wildcards' => "Wildcards ('*', '...') are not permitted."
                ),
            ),
            array(
                'label'   => __LINE__ .': alpha with @',
                'value'   => 'a@b',
                'valid'   => false,
                'error'   => array(
                    'revision' => "Revision characters ('#', '@') are not permitted.",
                ),
            ),
            array(
                'label'   => __LINE__ .': alpha with #',
                'value'   => 'a#b',
                'valid'   => false,
                'error'   => array(
                    'revision' => "Revision characters ('#', '@') are not permitted.",
                ),
            ),
            array(
                'label'   => __LINE__ .': alpha with %',
                'value'   => 'a%b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with %%',
                'value'   => 'a%%',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with %%b',
                'value'   => 'a%%b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with %%1',
                'value'   => 'a%%1',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with bad utf-8',
                'value'   => 'foo\xC0\xAFbar',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha with umlaut',
                'value'   => 'jöhan',
                'valid'   => true,
                'error'   => array(),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $validator = new \P4\Validate\UserName;
            $valid = $validator->isValid($test['value']);

            $this->assertSame(
                $test['error'],
                $validator->getMessages(),
                "$label - Expected error message(s)"
            );

            $this->assertSame(
                $test['valid'],
                $valid,
                "$label - Expected validation result."
            );
        }
    }
}
