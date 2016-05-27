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

class PortTest extends TestCase
{
    /**
     * Test instantiation.
     */
    public function testInstantiation()
    {
        $validator = new \P4\Validate\Port;
        $this->assertTrue($validator instanceof \P4\Validate\Port, 'Expected class');
    }

    /**
     * Test isValid.
     */
    public function testIsValid()
    {
        $invalidPortError = 'does not appear to contain a valid numeric port.';
        $invalidHostError = 'appears to have a invalid hostname component.';
        $tests = array(
            array(
                'label' => __LINE__ .': null',
                'value' => null,
                'valid' => false,
                'error' => array(
                    'invalidPort' => "'' $invalidPortError",
                ),
            ),
            array(
                'label' => __LINE__ .': empty',
                'value' => '',
                'valid' => false,
                'error' => array(
                    'invalidPort' => "'' $invalidPortError",
                ),
            ),
            array(
                'label' => __LINE__ .': numeric',
                'value' => 123,
                'valid' => true,
                'error' => array(),
            ),
            array(
                'label' => __LINE__ .': number',
                'value' => '123',
                'valid' => true,
                'error' => array(),
            ),
            array(
                'label' => __LINE__ .': ssl with host/port',
                'value' => 'ssl:perforce:1666',
                'valid' => true,
                'error' => array(),
            ),
            array(
                'label' => __LINE__ .': ssl with port',
                'value' => 'ssl:1666',
                'valid' => true,
                'error' => array(),
            ),
            array(
                'label' => __LINE__ .': alpha',
                'value' => 'abc',
                'valid' => false,
                'error' => array(
                    'invalidPort' => "'abc' $invalidPortError",
                ),
            ),
            array(
                'label' => __LINE__ .': alphanumeric',
                'value' => 'abc123',
                'valid' => false,
                'error' => array(
                    'invalidPort' => "'abc123' $invalidPortError",
                ),
            ),
            array(
                'label' => __LINE__ .': numericalpha',
                'value' => '123abc',
                'valid' => false,
                'error' => array(
                    'invalidPort' => "'123abc' $invalidPortError",
                ),
            ),
            array(
                'label' => __LINE__ .': empty host, good port',
                'value' => ':123',
                'valid' => false,
                'error' => array(
                    'invalidHost' => "':123' $invalidHostError"
                ),
            ),
            array(
                'label' => __LINE__ .': localhost, bad port',
                'value' => 'localhost:abc',
                'valid' => false,
                'error' => array(
                    'invalidPort' => "'localhost:abc' $invalidPortError",
                ),
            ),

        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $validator = new \P4\Validate\Port;

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
