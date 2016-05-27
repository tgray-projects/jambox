<?php
/**
 * Test methods for the P4 Spec Definition class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Exception\Exception as SpecException;

class DefinitionTest extends TestCase
{
    /**
     * Testing get fields.
     */
    public function testGetFields()
    {
        try {
            $specSpec = \P4\Spec\Definition::fetch('spec');
            $fields   = $specSpec->getFields();
        } catch (\Exception $e) {
            $this->fail(
                "Unexpected failure fetching spec spec: ". $e->getMessage()
            );
        }

        $expected = array (
          'Fields' =>
          array (
            'code' => '351',
            'dataType' => 'wlist',
            'displayLength' => '0',
            'fieldType' => 'required',
            'wordCount' => '5',
          ),
          'Words' =>
          array (
            'code' => '352',
            'dataType' => 'wlist',
            'displayLength' => '0',
            'fieldType' => 'optional',
            'wordCount' => '2',
          ),
          'Formats' =>
          array (
            'code' => '353',
            'dataType' => 'wlist',
            'displayLength' => '0',
            'fieldType' => 'optional',
            'wordCount' => '3',
          ),
          'Values' =>
          array (
            'code' => '354',
            'dataType' => 'wlist',
            'displayLength' => '0',
            'fieldType' => 'optional',
            'wordCount' => '2',
          ),
          'Presets' =>
          array (
            'code' => '355',
            'dataType' => 'wlist',
            'displayLength' => '0',
            'fieldType' => 'optional',
            'wordCount' => '2',
          ),
          'Comments' =>
          array (
            'code' => '356',
            'dataType' => 'text',
            'displayLength' => '0',
            'fieldType' => 'optional',
          ),
        );

        $this->assertSame($expected, $fields, "Expected spec fields");
    }

    /**
     * Testing get comments.
     */
    public function testGetComments()
    {
        try {
            $specSpec = \P4\Spec\Definition::fetch('spec');
        } catch (\Exception $e) {
            $this->fail(
                "Unexpected failure fetching spec spec: ". $e->getMessage()
            );
        }

        $expected = <<<EOE
# A Perforce Spec Specification.
#
#  Updating this form can be dangerous!
#  To update the job spec, see 'p4 help jobspec' for proper directions.
#  Otherwise, see 'p4 help spec'.

EOE;

        // ensure unix-style line endings are used on Windows
        $expected = str_replace("\r\n", "\n", $expected);

        $this->assertSame($expected, $specSpec->getComments(), "Expected comments");

        // Clear cache and try again; verifies populating on getComments works
        try {
            $specSpec->clearCache();
        } catch (\Exception $e) {
            $this->fail(
                "Unexpected failure clearing spec spec cache: ". $e->getMessage()
            );
        }
        $this->assertSame($expected, $specSpec->getComments(), "Expected comments, after clear");
    }

    /**
     * Test setting the comments
     */
    public function testSetComments()
    {
        $tests = array(
           array(
               'label' => __LINE__ . ' Empty String',
               'text'  => '',
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' string with hash',
               'text'  => '# test12312test',
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' multiline string with hashes',
               'text'  => "# test line 1\n# test line2\n# test line 3!\n",
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' empty multiline string',
               'text'  => "\n\n",
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' integer',
               'text'  => 10,
               'error' => "Comments must be a string."
           ),
           array(
               'label' => __LINE__ . ' float',
               'text'  => 10.1,
               'error' => "Comments must be a string."
           ),
           array(
               'label' => __LINE__ . ' bool',
               'text'  => true,
               'error' => "Comments must be a string."
           ),
           array(
               'label' => __LINE__ . ' null',
               'text'  => null,
               'error' => "Comments must be a string."
           ),
           array(
               'label' => __LINE__ . ' array',
               'text'  => array('test' => 'value'),
               'error' => "Comments must be a string."
           ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $spec = new \P4\Spec\Definition;

            try {
                $spec->setComments($test['text']);

                if ($test['error']) {
                    $this->fail("$label: Unexpected success.");
                }

                $this->assertSame($spec->getComments(), $test['text'], "$label: Expected Comment");
            } catch (\InvalidArgumentException $e) {
                if (!$test['error']) {
                    $this->fail("$label: Unexpected failure.");
                } else {
                    $this->assertSame(
                        $test['error'],
                        $e->getMessage(),
                        "$label Expected Error Message"
                    );
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                $this->fail(
                    "$label: Unexpected Exception (" . get_class($e) . '): ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Test setting comments whitespace issues.
     */
    public function testSetCommentsWhitespace()
    {
        $tests = array(
            array(
                'label' => __LINE__ . ' leading whitespace',
                'value' => "\n\t\n   \n# Test Text\n",
                'out'   => "\n\n\n# Test Text\n"
            ),
            array(
                'label' => __LINE__ . ' interior whitespace',
                'value' => "# Start Text\n\t\n   \n# Test Text\n",
                'out'   => "# Start Text\n\n\n# Test Text\n"
            ),
            array(
                'label' => __LINE__ . ' trailing whitespace',
                'value' => "# Test Text\n\t\n   \n\n",
                'out'   => "# Test Text\n"
            ),
            array(
                'label' => __LINE__ . ' no trailing whitespace',
                'value' => "# Test Text\n# Line 2",
                'out'   => "# Test Text\n# Line 2\n"
            ),
            array(
                'label' => __LINE__ . ' populated lines, trailing whitespace',
                'value' => "# Test Text\t\n# Line 2   \n# Last\n",
                'out'   => "# Test Text\t\n# Line 2   \n# Last\n"
            ),
        );

        foreach ($tests as $test) {
            // Use explicit 'out' as expected if present, value otherwise
            $expect = array_key_exists('out', $test) ? $test['out'] : $test['value'];

            $jobSpec = \P4\Spec\Definition::fetch('job');

            $jobSpec->setComments($test['value']);
            $jobSpec->save();

            $this->assertSame(
                $expect,
                $jobSpec->getComments(),
                $test['label'] . " Unexpected comment result"
            );
        }
    }

    /**
     * Testing get and set type.
     */
    public function testGetSetType()
    {
        $tests = array(
           array(
               'label' => __LINE__ . ' Empty String',
               'type'  => '',
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' Alpha numeric string',
               'type'  => 'test232test',
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' pure numeric string',
               'type'  => '12345',
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' Alpha string',
               'type'  => 'test',
               'error' => false
           ),
           array(
               'label' => __LINE__ . ' integer',
               'type'  => 10,
               'error' => "Type must be a string."
           ),
           array(
               'label' => __LINE__ . ' float',
               'type'  => 10.1,
               'error' => "Type must be a string."
           ),
           array(
               'label' => __LINE__ . ' bool',
               'type'  => true,
               'error' => "Type must be a string."
           ),
           array(
               'label' => __LINE__ . ' null',
               'type'  => null,
               'error' => "Type must be a string."
           ),
           array(
               'label' => __LINE__ . ' array',
               'type'  => array('test' => 'value'),
               'error' => "Type must be a string."
           ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $spec = new \P4\Spec\Definition;

            try {
                $spec->setType($test['type']);

                if ($test['error']) {
                    $this->fail("$label: Unexpected success.");
                }

                $this->assertSame($spec->getType(), $test['type'], "$label: Expected Type");
            } catch (\InvalidArgumentException $e) {
                if (!$test['error']) {
                    $this->fail("$label: Unexpected failure.");
                } else {
                    $this->assertSame(
                        $test['error'],
                        $e->getMessage(),
                        "$label Expected Error Message"
                    );
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                $this->fail(
                    "$label: Unexpected Exception (" . get_class($e) . '): ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Test getField
     */
    public function testGetSetField()
    {
        $specSpec = \P4\Spec\Definition::fetch('spec');

        // Verify get field output matched getFields output
        foreach ($specSpec->getFields() as $name => $field) {
            $this->assertSame(
                $field,
                $specSpec->getField($name),
                "Expected Field ". $name
            );
        }

        // Verify bad fields throw.
        $badField = 'ahsdhadsl';

        $this->assertFalse($specSpec->hasField($badField), 'Garbage field should not exist');

        try {
            $specSpec->getField($badField);
            $this->fail('Unexpected Success getting garbage field');
        } catch (SpecException $e) {
            $this->assertSame(
                "Can't get field '$badField'. Field does not exist.",
                $e->getMessage(),
                'Expected exception for Garbage field'
            );
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            $this->fail($e->getMessage());
        } catch (\Exception $e) {
            $this->fail(
                "Unexpected Exception (" . get_class($e) . '): ' . $e->getMessage()
            );
        }

        // Verify we can get bad field after adding it
        $newFields = array(
            $badField => array (
                'code'          => '512',
                'dataType'      => 'word',
                'fieldType'     => 'optional',
            )
        );

        $specSpec->setFields($newFields);

        $this->assertTrue($specSpec->hasField($badField), 'Garbage field should now exist');

        $this->assertSame(
            $newFields[$badField],
            $specSpec->getField($badField),
            'Expected retrieved garbage field to match'
        );
    }

    /**
     * Test setting invalid calls to setFields
     */
    public function testSetInvalidFields()
    {
        $tests = array(
           array(
               'label' => __LINE__ . ' Empty String',
               'value'  => '',
           ),
           array(
               'label' => __LINE__ . ' Alpha string',
               'value'  => 'test',
           ),
           array(
               'label' => __LINE__ . ' integer',
               'value'  => 10,
           ),
           array(
               'label' => __LINE__ . ' float',
               'value'  => 10.1,
           ),
           array(
               'label' => __LINE__ . ' bool',
               'value'  => true,
           ),
           array(
               'label' => __LINE__ . ' null',
               'value'  => null,
           ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $spec = \P4\Spec\Definition::fetch('spec');

            try {
                $spec->setFields($test['value']);

                $this->fail("$label: Unexpected success.");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    "Fields must be an array.",
                    $e->getMessage(),
                    "$label Expected Error Message"
                );
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                $this->fail(
                    "$label: Unexpected Exception (" . get_class($e) . '): ' . $e->getMessage()
                );
            }
        }
    }

    /**
     * Test required fields
     */
    public function testRequiredFields()
    {
        $requiredFields = array('Fields');

        $specSpec = \P4\Spec\Definition::fetch('spec');

        foreach ($specSpec->getFields() as $name => $field) {
            if (in_array($name, $requiredFields)) {
                $this->assertTrue(
                    $specSpec->isRequiredField($name),
                    "Expected $name to be required."
                );
            } else {
                $this->assertFalse(
                    $specSpec->isRequiredField($name),
                    "Expected $name not to be required."
                );
            }
        }
    }

    /**
     * Test read only fields
     */
    public function testReadOnlyFields()
    {
        $specSpec = \P4\Spec\Definition::fetch('spec');
        $newFields = array(
            'optional' => array (
                'code'          => '512',
                'dataType'      => 'word',
                'fieldType'     => 'optional',
            ),
            'default' => array (
                'code'          => '512',
                'dataType'      => 'word',
                'fieldType'     => 'default',
            ),
            'required' => array (
                'code'          => '512',
                'dataType'      => 'word',
                'fieldType'     => 'required',
            ),
            'once' => array (
                'code'          => '512',
                'dataType'      => 'word',
                'fieldType'     => 'once',
            ),
            'always' => array (
                'code'          => '512',
                'dataType'      => 'word',
                'fieldType'     => 'always',
            ),
        );
        $readOnlyFields = array('once');

        $specSpec->setFields($newFields);

        foreach ($specSpec->getFields() as $name => $field) {
            if (in_array($name, $readOnlyFields)) {
                $this->assertTrue(
                    $specSpec->isReadOnlyField($name),
                    "Expected $name to be read-only."
                );
            } else {
                $this->assertFalse(
                    $specSpec->isReadOnlyField($name),
                    "Expected $name not to be read-only."
                );
            }
        }
    }

    /**
     * Test expansion of defaults
     */
    public function testExpandDefault()
    {
        $defaults = array(
           array(
               'label'      => __LINE__ . ' Empty String',
               'input'      => '',
           ),
           array(
               'label'      => __LINE__ . ' Alpha numeric string',
               'input'      => 'test232test',
           ),
           array(
               'label'      => __LINE__ . ' Alpha string',
               'input'      => 'test',
           ),
           array(
               'label'      => __LINE__ . ' Dollar string',
               'input'      => '$noExpand',
           ),
           array(
               'label'      => __LINE__ . ' Invalid Expansion',
               'input'      => '$users',
           ),
           array(
               'label'      => __LINE__ . ' integer',
               'input'      => 10,
               'exception'  => '\InvalidArgumentException'
           ),
           array(
               'label'      => __LINE__ . ' float',
               'input'      => 10.1,
               'exception'  => '\InvalidArgumentException'
           ),
           array(
               'label'      => __LINE__ . ' bool',
               'input'      => true,
               'exception'  => '\InvalidArgumentException'
           ),
           array(
               'label'      => __LINE__ . ' null',
               'input'      => null,
               'exception'  => '\InvalidArgumentException'
           ),
           array(
               'label'      => __LINE__ . ' array',
               'input'      => array('test' => 'value'),
               'exception'  => '\InvalidArgumentException'
           ),
           array(
               'label'      => __LINE__ . ' User Expansion',
               'input'      => '$user',
               'out'        => \P4\Spec\Definition::getDefaultConnection()->getUser()
           ),
           array(
               'label'      => __LINE__ . ' Blank Expansion',
               'input'      => '$blank',
               'out'        => null
           ),
        );

        // Test static, unmodified values
        foreach ($defaults as $test) {
            // ensure that an exception of the expected type if thrown when
            // expanding value of an invalid type
            if (isset($test['exception'])) {
                try {
                    \P4\Spec\Definition::expandDefault($test['input']);
                    $this->fail("Expected throwing a '" . $test['exception'] . "' exception.");
                } catch (\Exception $e) {
                    $this->assertTrue($e instanceof $test['exception']);
                }
                continue;
            }

            // If we have an 'out' paramater use it. Otherwise assume input == output
            $expect = array_key_exists('out', $test)?$test['out']:$test['input'];

            $this->assertSame(
                $expect,
                \P4\Spec\Definition::expandDefault($test['input']),
                "{$test['label']} expected matching return"
            );
        }
    }

    /**
     * Test saving a spec
     */
    public function testSave()
    {
        // Snag the spec spec and add a field
        $jobSpec  = \P4\Spec\Definition::fetch('job');
        $fields   = $jobSpec->getFields();
        $comments = $jobSpec->getComments();

        $fields['NewField'] = array (
            'code' => '198',
            'dataType' => 'wlist',
            'displayLength' => '0',
            'fieldType' => 'optional',
            'wordCount' => '4'
        );
        $fields['NewField2'] = array (
            'code'          => '199',
            'dataType'      => 'select',
            'displayLength' => '12',
            'fieldType'     => 'optional',
            'order'         => '0',
            'position'      => 'L',
            'options'       => array (
                0 => 'local',
                1 => 'unix',
            )
        );

        $jobSpec->setFields($fields);
        $this->assertSame(
            $fields,
            $jobSpec->getFields(),
            'Expected instance fields to match'
        );

        $comments .= "\n# Testing tweak comments!\n";
        $jobSpec->setComments($comments);
        $this->assertSame(
            $comments,
            $jobSpec->getComments(),
            'Expected instance comments to match'
        );

        // Save our changes
        $jobSpec->save();

        // Following save, validate fields/comments twice
        // - Run 1 checks against current instance
        // - Run 2 fetches a fresh instance and re-checks
        for ($i=0; $i<2; $i++) {
            $this->assertSame(
                $comments,
                $jobSpec->getComments(),
                "Expected matching comments (run $i)"
            );

            $this->assertSame(
                $fields,
                $jobSpec->getFields(),
                "Expected matching fields array (run $i)"
            );

            $this->assertSame(
                $fields['NewField'],
                $jobSpec->getField('NewField'),
                "Expected matching NewField array (run $i)"
            );

            $this->assertSame(
                $fields['NewField2'],
                $jobSpec->getField('NewField2'),
                "Expected matching NewField2 array (run $i)"
            );

            // Verify a fresh instance reflects updated values
            $jobSpec = \P4\Spec\Definition::fetch('job');
        }
    }
}
