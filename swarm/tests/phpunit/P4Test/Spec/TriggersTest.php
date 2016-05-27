<?php
/**
 * Test methods for the P4 Triggers class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Triggers;

class TriggersTest extends TestCase
{
    /**
     * Test getting values
     */
    public function testGet()
    {
        // Test default values
        $triggers = new Triggers;
        $expected = array (
            'Triggers' => array(),
        );

        $this->assertSame(
            $expected,
            $triggers->get(),
            'Expected default values to match'
        );

        // set new values.
        $values = array(
            array(
                'name'    => 'write',
                'type'    => 'archive',
                'path'    => '//test/...',
                'command' => 'myscript.sh'
            )
        );

        $triggers->setTriggers($values);

        // Verify instance reflects updated values via accessor
        $this->assertSame(
            $values,
            $triggers->getTriggers(),
            'Expected instance values to match'
        );

        // Verify field and accessor give same result
        $this->assertSame(
            $triggers->get('Triggers'),
            $triggers->getTriggers(),
            'Expected instance values in field to match accessor'
        );

        $this->assertSame(
            array('Triggers' => $values),
            $triggers->get(),
            'Expected instance values array to match'
        );

        // test save.
        $triggers->save();

        $triggers = Triggers::fetch();

        $this->assertSame(
            $values,
            $triggers->getTriggers(),
            'Expected saved values to match'
        );

        // Verify field and accessor give same result
        $this->assertSame(
            $triggers->get('Triggers'),
            $triggers->getTriggers(),
            'Expected saved values in field to match accessor'
        );

        $this->assertSame(
            array('Triggers' => $values),
            $triggers->get(),
            'Expected saved values array to match'
        );
    }

    /**
     * Test setting valid and invalid values.
     */
    public function testSet()
    {
        $tests = array(
            array(
                'label' => __LINE__ . " string input",
                'value' => "my-trigger archive //test/... /path/to/myscript.sh",
                'error' => true
            ),
            array(
                'label' => __LINE__ . " bool input",
                'value' => true,
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, doubled-up space",
                'value' => array("my-trigger  archive //test/... /path/to/myscript.sh"),
                'out'   => array (
                    0 => array (
                        'name'      => "my-trigger",
                        'type'      => 'archive',
                        'path'      => '//test/...',
                        'command'   => '/path/to/myscript.sh'
                    )
                ),
                'error' => false
            ),
            array(
                'label' => __LINE__ . " string input, space in field",
                'value' => array("my trigger archive //test/... /path/to/myscript.sh"),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, missing field",
                'value' => array("archive //test/... /path/to/myscript.sh"),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, space in unquoted path",
                'value' => array("my-trigger archive //test/... /with/spa ce/myscript.sh"),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " array input, name has space",
                'value' => array (
                    0 => array (
                        'name'      => "my trigger",
                        'type'      => "user",
                        'path'      => "//test with spaces/...",
                        'command'   => "/path/to/script.sh"
                    )
                ),
                'error' => true,
            ),
            array(
                'label' => __LINE__ . " array input, missing type",
                'value' => array (
                    0 => array (
                        'name'      => "my-trigger",
                        'path'      => "//test with spaces/...",
                        'command'   => "/path/to/script.sh"
                    )
                ),
                'error' => true,
            ),
            array(
                'label' => __LINE__ . " array input, missing path",
                'value' => array (
                    0 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-in",
                        'command'   => "/path/to/script.sh"
                    )
                ),
                'error' => true,
            ),
            array(
                'label' => __LINE__ . " one string input, path has spaces.",
                'value' => array('my-trigger form-delete "//test spaces/..." /cmd/nospace.sh'),
                'error' => false,
                'out'   => array (
                    0 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-delete",
                        'path'      => "//test spaces/...",
                        'command'   => "/cmd/nospace.sh"
                    )
                )
            ),
            array(
                'label' => __LINE__ . " one string input, command has spaces.",
                'value' => array('my-trigger form-delete "//test/..." "/cmd/spa ce.sh"'),
                'error' => false,
                'out'   => array (
                    0 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-delete",
                        'path'      => "//test/...",
                        'command'   => "/cmd/spa ce.sh"
                    )
                )
            ),
            array(
                'label' => __LINE__ . " one array input, path has spaces.",
                'value'   => array (
                    0 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-delete",
                        'path'      => "//test spaces/...",
                        'command'   => "/cmd/spa ce.sh"
                    )
                ),
                'error' => false,
            ),
            array(
                'label' => __LINE__ . " four string input",
                'value' => array(
                    'atrigger form-delete //testfolder1/... /cmd/sample.sh',
                    'my-trigger form-out //testfolder2/... /path/to/go',
                    'identify form-in //testfolder3/... /scripts/test.py',
                    'gogo fix-add //testfolder4/... /scripts/test.php',
                ),
                'out'   => array (
                    0 => array (
                        'name'      => "atrigger",
                        'type'      => "form-delete",
                        'path'      => "//testfolder1/...",
                        'command'   => "/cmd/sample.sh"
                    ),
                    1 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-out",
                        'path'      => "//testfolder2/...",
                        'command'   => "/path/to/go"
                    ),
                    2 => array (
                        'name'      => "identify",
                        'type'      => "form-in",
                        'path'      => "//testfolder3/...",
                        'command'   => "/scripts/test.py"
                    ),
                    3 => array (
                        'name'      => "gogo",
                        'type'      => "fix-add",
                        'path'      => "//testfolder4/...",
                        'command'   => "/scripts/test.php"
                    )
                ),
                'error' => false
            ),
            array(
                'label' => __LINE__ . " four array input",
                'value'   => array (
                    0 => array (
                        'name'      => "atrigger",
                        'type'      => "form-delete",
                        'path'      => "//testfolder1/...",
                        'command'   => "/cmd/sample.sh"
                    ),
                    1 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-out",
                        'path'      => "//testfolder2/...",
                        'command'   => "/path/to/go"
                    ),
                    2 => array (
                        'name'      => "identify",
                        'type'      => "form-in",
                        'path'      => "//testfolder3/...",
                        'command'   => "/scripts/test.py"
                    ),
                    3 => array (
                        'name'      => "identify",
                        'type'      => "fix-add",
                        'path'      => "//testfolder4/...",
                        'command'   => "/scripts/test.php"
                    )
                ),
                'error' => false
            ),
            array(
                'label' => __LINE__ . " mixed string/array input",
                'value' => array(
                    0 => 'atrigger form-delete //testfolder1/... /cmd/sample.sh',
                    1 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-out",
                        'path'      => "//testfolder2/...",
                        'command'   => "/path/to/go"
                    ),
                    2 => 'identify form-in //testfolder3/... /scripts/test.py',
                    3 => array (
                        'name'      => "identify",
                        'type'      => "fix-add",
                        'path'      => "//testfolder4/...",
                        'command'   => "/scripts/test.php"
                    )
                ),
                'out'   => array (
                    0 => array (
                        'name'      => "atrigger",
                        'type'      => "form-delete",
                        'path'      => "//testfolder1/...",
                        'command'   => "/cmd/sample.sh"
                    ),
                    1 => array (
                        'name'      => "my-trigger",
                        'type'      => "form-out",
                        'path'      => "//testfolder2/...",
                        'command'   => "/path/to/go"
                    ),
                    2 => array (
                        'name'      => "identify",
                        'type'      => "form-in",
                        'path'      => "//testfolder3/...",
                        'command'   => "/scripts/test.py"
                    ),
                    3 => array (
                        'name'      => "identify",
                        'type'      => "fix-add",
                        'path'      => "//testfolder4/...",
                        'command'   => "/scripts/test.php"
                    )
                ),
                'error' => false
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $triggers = new Triggers;

            try {
                $triggers->setTriggers($test['value']);

                if ($test['error']) {
                    $this->fail("$label: Unexpected success.");
                }

                $expected = array_key_exists('out', $test) ? $test['out'] : $test['value'];

                $this->assertSame(
                    $expected,
                    $triggers->getTriggers(),
                    "$label: Unexpected Output"
                );
            } catch (\InvalidArgumentException $e) {
                if (!$test['error']) {
                    $this->fail("$label: Unexpected failure.");
                } else {
                    $this->assertTrue(true, "$label: Expected exception found");
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
}
