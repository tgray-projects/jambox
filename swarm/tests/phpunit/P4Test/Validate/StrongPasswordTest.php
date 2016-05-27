<?php
/**
 * Test methods for the P4 Validate StrongPassword class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Validate;

use P4Test\TestCase;

class StrongPasswordTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $validator = new \P4\Validate\StrongPassword;
        $this->assertTrue($validator instanceof \P4\Validate\StrongPassword, 'Expected class');
    }

    /**
     * Test isValid.
     */
    public function testIsValid()
    {
        $weakPasswordMessage = "Passwords must be at least 8 characters long and contain "
            . "mixed case or both alphabetic and non-alphabetic characters.";

        $tests = array(
            array(
                'label'   => __LINE__ .': null',
                'value'   => null,
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': empty',
                'value'   => '',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': short (7 characters, only digits)',
                'value'   => '1234567',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': short (7 characters, mixed case)',
                'value'   => 'asdQWEr',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': short (7 characters, alpha & nonalpha)',
                'value'   => 'Xa1b2c3',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': weak (only lowercase letters)',
                'value'   => 'qwertyuiop',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': weak (only uppercase letters)',
                'value'   => 'ZXCVBNML',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': weak (only nonaplha)',
                'value'   => '123456789!',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': weak (only nonaplha)',
                'value'   => '!12@_)0^%$',
                'valid'   => false,
                'error'   => array(
                    'weakPassword' => $weakPasswordMessage
                ),
            ),
            array(
                'label'   => __LINE__ .': strong (mixed case)',
                'value'   => 'aByUopXZkjhIU',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': strong (mixed case)',
                'value'   => 'xxxXxxxx',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': strong (mixed case)',
                'value'   => 'ABCDEFGh',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': strong (alpha & nonaplha)',
                'value'   => 'gf12ty2h4u5y66',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': strong (alpha & nonaplha)',
                'value'   => '1234567A',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': strong (alpha & nonaplha)',
                'value'   => 'abcxyz!u',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': strong (alpha & nonaplha)',
                'value'   => 'OOAAXX1Q',
                'valid'   => true,
                'error'   => array(),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $validator = new \P4\Validate\StrongPassword;

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
