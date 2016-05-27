<?php
/**
 * Test methods for the P4 Validate ChangeNumber class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Validate;

use P4Test\TestCase;

class ChangeNumberTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $validator = new \P4\Validate\ChangeNumber;
        $this->assertTrue($validator instanceof \P4\Validate\ChangeNumber, 'Expected class');
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
                    'invalidType' => 'Change number must be an integer or purely numeric string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': string, empty',
                'value'   => '',
                'valid'   => false,
                'error'   => array(
                    'invalidNumber' => 'Change numbers must be greater than zero.',
                ),
            ),
            array(
                'label'   => __LINE__ .': numeric integer',
                'value'   => 123,
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': numeric float',
                'value'   => 12.3,
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Change number must be an integer or purely numeric string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': number string',
                'value'   => '123',
                'valid'   => true,
                'error'   => array(),
            ),
            array(
                'label'   => __LINE__ .': alpha',
                'value'   => 'abc',
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Change number must be an integer or purely numeric string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': alphanumeric',
                'value'   => 'abc123',
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Change number must be an integer or purely numeric string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': numericalpha',
                'value'   => '123abc',
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Change number must be an integer or purely numeric string.',
                ),
            ),
            array(
                'label'   => __LINE__ .': numeric with .',
                'value'   => '1.2',
                'valid'   => false,
                'error'   => array(
                    'invalidType' => 'Change number must be an integer or purely numeric string.',
                ),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $validator = new \P4\Validate\ChangeNumber;

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
