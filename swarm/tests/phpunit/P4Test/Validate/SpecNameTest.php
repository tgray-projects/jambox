<?php
/**
 * Test methods for the P4 Validate SpecName class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Validate;

use P4Test\TestCase;

class SpecNameTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $validator = new \P4\Validate\SpecName;
        $this->assertTrue($validator instanceof \P4\Validate\SpecName, 'Expected class');
    }

    /**
     * Test isValid.
     */
    public function testIsValid()
    {
        $tests = array(
            array(
                'label'   => __LINE__ .': string, null',
                'numeric' => false,
                'value'   => null,
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, empty',
                'numeric' => false,
                'value'   => '',
                'valid'   => false,
                'error'   => array(
                    'isEmpty' => 'Is an empty string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, numeric',
                'numeric' => false,
                'value'   => 123,
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, number',
                'numeric' => false,
                'value'   => '123',
                'valid'   => false,
                'error'   => array(
                    'isNumeric' => 'Purely numeric values are not allowed.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha',
                'numeric' => false,
                'value'   => 'abc',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': string, alphanumeric',
                'numeric' => false,
                'value'   => 'abc123',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': string, numericalpha',
                'numeric' => false,
                'value'   => '123abc',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with whitespace',
                'numeric' => false,
                'value'   => 'a b c',
                'valid'   => false,
                'error'   => array(
                    'hasSpaces' => 'Whitespace is not permitted.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with .',
                'numeric' => false,
                'value'   => 'a.b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with ..',
                'numeric' => false,
                'value'   => 'a..b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with *',
                'numeric' => false,
                'value'   => 'a*b',
                'valid'   => false,
                'error'   => array(
                    'wildcards' => "Wildcards ('*', '...') are not permitted."
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with ...',
                'numeric' => false,
                'value'   => 'a...b',
                'valid'   => false,
                'error'   => array(
                    'wildcards' => "Wildcards ('*', '...') are not permitted."
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with @',
                'numeric' => false,
                'value'   => 'a@b',
                'valid'   => false,
                'error'   => array(
                    'revision' => "Revision characters ('#', '@') are not permitted.",
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with #',
                'numeric' => false,
                'value'   => 'a#b',
                'valid'   => false,
                'error'   => array(
                    'revision' => "Revision characters ('#', '@') are not permitted.",
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with %',
                'numeric' => false,
                'value'   => 'a%b',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with %%',
                'numeric' => false,
                'value'   => 'a%%',
                'valid'   => false,
                'error'   => array(
                    'positional' => "Positional specifiers ('%%x') are not permitted."
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with %%b',
                'numeric' => false,
                'value'   => 'a%%b',
                'valid'   => false,
                'error'   => array(
                    'positional' => "Positional specifiers ('%%x') are not permitted."
                ),
            ),
            array(
                'label'   => __LINE__ .': string, alpha with %%1',
                'numeric' => false,
                'value'   => 'a%%1',
                'valid'   => false,
                'error'   => array(
                    'positional' => "Positional specifiers ('%%x') are not permitted."
                ),
            ),

            // numeric tests
            array(
                'label'   => __LINE__ .': numeric, null',
                'numeric' => true,
                'value'   => null,
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'   => __LINE__ .': numeric, empty',
                'numeric' => true,
                'value'   => '',
                'valid'   => false,
                'error'   => array(
                    'isEmpty' => 'Is an empty string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': numeric, numeric',
                'numeric' => true,
                'value'   => 123,
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': numeric, number',
                'numeric' => true,
                'value'   => '123',
                'valid'   => true,
                'error'   => array(),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $validator = new \P4\Validate\SpecName;
            $validator->allowPurelyNumeric($test['numeric']);

            $this->assertSame(
                $test['valid'],
                $validator->isValid($test['value']),
                "$label - Expected validation result."
            );

            $this->assertSame(
                $test['error'],
                $validator->getMessages(),
                "$label - Expected error message(s)"
            );
        }
    }
}
