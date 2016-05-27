<?php
/**
 * Test methods for the P4 Validate KeyName class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Validate;

use P4Test\TestCase;

class KeyNameTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $validator = new \P4\Validate\KeyName;
        $this->assertTrue($validator instanceof \P4\Validate\KeyName, 'Expected class');
    }

    /**
     * Test isValid.
     */
    public function testIsValid()
    {
        $tests = array(
            array(
                'label'      => __LINE__ .': string, null',
                'numeric'    => false,
                'positional' => true,
                'value'      => null,
                'valid'      => false,
                'error'      => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'      => __LINE__ .': string, empty',
                'numeric'    => false,
                'positional' => true,
                'value'      => '',
                'valid'      => false,
                'error'      => array(
                    'isEmpty' => 'Is an empty string.',
                ),
            ),
            array(
                'label'      => __LINE__ .': string, numeric',
                'numeric'    => false,
                'positional' => true,
                'value'      => 123,
                'valid'      => false,
                'error'      => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'      => __LINE__ .': string, array',
                'numeric'    => false,
                'positional' => true,
                'value'      => array('123'),
                'valid'      => false,
                'error'      => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'      => __LINE__ .': string, number',
                'numeric'    => false,
                'positional' => true,
                'value'      => '123',
                'valid'      => false,
                'error'      => array(
                    'isNumeric' => 'Purely numeric values are not allowed.',
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'abc',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alphanumeric',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'abc123',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, numericalpha',
                'numeric'    => false,
                'positional' => true,
                'value'      => '123abc',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with leading dash',
                'numeric'    => false,
                'positional' => true,
                'value'      => '-abc',
                'valid'      => false,
                'error'      => array(
                    'leadingMinus' => "First character cannot be minus ('-').",
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with whitespace',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a b c',
                'valid'      => false,
                'error'      => array(
                    'hasSpaces' => 'Whitespace is not permitted.',
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with .',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a.b',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with ..',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a..b',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with *',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a*b',
                'valid'      => false,
                'error'      => array(
                    'wildcards' => "Wildcards ('*', '...') are not permitted."
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with ...',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a...b',
                'valid'      => false,
                'error'      => array(
                    'wildcards' => "Wildcards ('*', '...') are not permitted."
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with @',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a@b',
                'valid'      => false,
                'error'      => array(
                    'revision' => "Revision characters ('#', '@') are not permitted.",
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with #',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a#b',
                'valid'      => false,
                'error'      => array(
                    'revision' => "Revision characters ('#', '@') are not permitted.",
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with /',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a/b',
                'valid'      => false,
                'error'      => array(
                    'slashes' => "Slashes ('/') are not permitted.",
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a%b',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %%, positional true',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a%%',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %%, positional false',
                'numeric'    => false,
                'positional' => false,
                'value'      => 'a%%',
                'valid'      => false,
                'error'      => array(
                    'positional' => "Positional specifiers ('%%x') are not permitted."
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %%b, positional true',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a%%b',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %%b, positional false',
                'numeric'    => false,
                'positional' => false,
                'value'      => 'a%%b',
                'valid'      => false,
                'error'      => array(
                    'positional' => "Positional specifiers ('%%x') are not permitted."
                ),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %%1, positional true',
                'numeric'    => false,
                'positional' => true,
                'value'      => 'a%%1',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': string, alpha with %%1, positional false',
                'numeric'    => false,
                'positional' => false,
                'value'      => 'a%%1',
                'valid'      => false,
                'error'      => array(
                    'positional' => "Positional specifiers ('%%x') are not permitted."
                ),
            ),

            // numeric tests
            array(
                'label'      => __LINE__ .': numeric, null',
                'numeric'    => true,
                'positional' => true,
                'value'      => null,
                'valid'      => false,
                'error'      => array(
                    'invalidType' => 'Invalid type given.',
                ),
            ),
            array(
                'label'      => __LINE__ .': numeric, empty',
                'numeric'    => true,
                'positional' => true,
                'value'      => '',
                'valid'      => false,
                'error'      => array(
                    'isEmpty' => 'Is an empty string.',
                ),
            ),
            array(
                'label'      => __LINE__ .': numeric, numeric',
                'numeric'    => true,
                'positional' => true,
                'value'      => 123,
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': numeric, negative numeric',
                'numeric'    => true,
                'positional' => true,
                'value'      => -123,
                'valid'      => false,
                'error'      => array(
                    'leadingMinus' => "First character cannot be minus ('-').",
                ),
            ),
            array(
                'label'      => __LINE__ .': numeric, number',
                'numeric'    => true,
                'positional' => true,
                'value'      => '123',
                'valid'      => true,
                'error'      => array(),
            ),
            array(
                'label'      => __LINE__ .': numeric, negative number',
                'numeric'    => true,
                'positional' => true,
                'value'      => '-123',
                'valid'      => false,
                'error'      => array(
                    'leadingMinus' => "First character cannot be minus ('-').",
                ),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $validator = new \P4\Validate\KeyName;
            $validator->allowPurelyNumeric($test['numeric']);
            $validator->allowPositional($test['positional']);

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
