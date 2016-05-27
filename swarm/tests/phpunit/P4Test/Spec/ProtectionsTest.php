<?php
/**
 * Test methods for the P4 Protections class.
 *
 * @copyright   201 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Protections;

class ProtectionsTest extends TestCase
{
    /**
     * Test getting values
     */
    public function testGet()
    {
        // Test in-memory default values
        $protections = new Protections;
        $this->assertSame(
            array('Protections' => array()),
            $protections->get(),
            'Expected in-memory default values to match'
        );

        // Test server default values
        $protections = Protections::fetch();
        $expected = array (
            'Protections' => array (
                0 => array (
                    'mode' => 'write',
                    'type' => 'user',
                    'name' => '*',
                    'host' => '*',
                    'path' => '//...',
                ),
                1 => array (
                    'mode' => 'super',
                    'type' => 'user',
                    'name' => 'tester',
                    'host' => '*',
                    'path' => '//...',
                ),
            ),
        );

        $this->assertSame(
            $expected,
            $protections->get(),
            'Expected default values to match'
        );

        // set new values.
        $values = array(
            array(
                'mode'  => 'super',
                'type'  => 'user',
                'name'  => '*',
                'host'  => '*',
                'path'  => '//...'
            )
        );

        $protections->setProtections($values);

        // Verify instance reflects updated values via accessor
        $this->assertSame(
            $values,
            $protections->getProtections(),
            'Expected instance values to match'
        );

        // Verify field and accessor give same result
        $this->assertSame(
            $protections->get('Protections'),
            $protections->getProtections(),
            'Expected instance values in field to match accessor'
        );

        $this->assertSame(
            array('Protections' => $values),
            $protections->get(),
            'Expected instance values array to match'
        );

        // test save.
        $protections->save();

        $protections = Protections::fetch();

        $this->assertSame(
            $values,
            $protections->getProtections(),
            'Expected saved values to match'
        );

        // Verify field and accessor give same result
        $this->assertSame(
            $protections->get('Protections'),
            $protections->getProtections(),
            'Expected saved values in field to match accessor'
        );

        $this->assertSame(
            array('Protections' => $values),
            $protections->get(),
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
                'value' => "write user * * //...",
                'error' => true
            ),
            array(
                'label' => __LINE__ . " bool input",
                'value' => true,
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, doubled-up space",
                'value' => array("writer  user * * //..."),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, space in field",
                'value' => array("writ er user * * //..."),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, missing field",
                'value' => array("writer * * //..."),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " string input, space in unquoted path",
                'value' => array("writ er user * * //test path/..."),
                'error' => true
            ),
            array(
                'label' => __LINE__ . " array input, name has space",
                'value' => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "* ",
                        'host' => "*",
                        'path' => "//test with spaces/..."
                    )
                ),
                'error' => true,
            ),
            array(
                'label' => __LINE__ . " array input, missing type",
                'value' => array (
                    0 => array (
                        'mode' => "write ",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//test with spaces/..."
                    )
                ),
                'error' => true,
            ),
            array(
                'label' => __LINE__ . " array input, missing path",
                'value' => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                    )
                ),
                'error' => true,
            ),
            array(
                'label' => __LINE__ . " one string input, path has spaces.",
                'value' => array('write user * * "//test with spaces/..."'),
                'error' => false,
                'out'   => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//test with spaces/..."
                    )
                )
            ),
            array(
                'label' => __LINE__ . " one array input, path has spaces.",
                'value'   => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//test with spaces/..."
                    )
                ),
                'error' => false,
            ),
            array(
                'label' => __LINE__ . " one string input.",
                'value' => array("write user * * //..."),
                'error' => false,
                'out'   => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//..."
                    )
                )
            ),
            array(
                'label' => __LINE__ . " four string input",
                'value' => array(
                    "write user * * //...",
                    "read user bob * //test1/...",
                    "review group testGroup * //test2/...",
                    "open user * example.com //test3/..."
                ),
                'out'   => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//..."
                    ),
                    1 => array (
                        'mode' => "read",
                        'type' => "user",
                        'name' => "bob",
                        'host' => "*",
                        'path' => "//test1/..."
                    ),
                    2 => array (
                        'mode' => "review",
                        'type' => "group",
                        'name' => "testGroup",
                        'host' => "*",
                        'path' => "//test2/..."
                    ),
                    3 => array (
                        'mode' => "open",
                        'type' => "user",
                        'name' => "*",
                        'host' => "example.com",
                        'path' => "//test3/..."
                    )
                ),
                'error' => false
            ),
            array(
                'label' => __LINE__ . " four array input",
                'value'   => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//..."
                    ),
                    1 => array (
                        'mode' => "read",
                        'type' => "user",
                        'name' => "bob",
                        'host' => "*",
                        'path' => "//test1/..."
                    ),
                    2 => array (
                        'mode' => "review",
                        'type' => "group",
                        'name' => "testGroup",
                        'host' => "*",
                        'path' => "//test2/..."
                    ),
                    3 => array (
                        'mode' => "open",
                        'type' => "user",
                        'name' => "*",
                        'host' => "example.com",
                        'path' => "//test3/..."
                    )
                ),
                'error' => false
            ),
            array(
                'label' => __LINE__ . " mixed string/array input",
                'value' => array(
                    "write user * * //...",
                    array (
                        'mode' => "read",
                        'type' => "user",
                        'name' => "bob",
                        'host' => "*",
                        'path' => "//test1/..."
                    ),
                    'review group testGroup * "//test2 with spaces/..."',
                    3 => array (
                        'mode' => "open",
                        'type' => "user",
                        'name' => "*",
                        'host' => "example.com",
                        'path' => "//test3/..."
                    )
                ),
                'out'   => array (
                    0 => array (
                        'mode' => "write",
                        'type' => "user",
                        'name' => "*",
                        'host' => "*",
                        'path' => "//..."
                    ),
                    1 => array (
                        'mode' => "read",
                        'type' => "user",
                        'name' => "bob",
                        'host' => "*",
                        'path' => "//test1/..."
                    ),
                    2 => array (
                        'mode' => "review",
                        'type' => "group",
                        'name' => "testGroup",
                        'host' => "*",
                        'path' => "//test2 with spaces/..."
                    ),
                    3 => array (
                        'mode' => "open",
                        'type' => "user",
                        'name' => "*",
                        'host' => "example.com",
                        'path' => "//test3/..."
                    )
                ),
                'error' => false
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $protect = new Protections;

            try {
                $protect->setProtections($test['value']);

                if ($test['error']) {
                    $this->fail("$label: Unexpected success.");
                }

                $expected = array_key_exists('out', $test) ? $test['out'] : $test['value'];

                $this->assertSame(
                    $expected,
                    $protect->getProtections(),
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

    /**
     * Test removing values
     */
    public function testRemove()
    {
        $values = array (
            0 => array (
                'mode' => 'write',
                'type' => 'user',
                'name' => '*',
                'host' => '*',
                'path' => '//...',
            ),
            1 => array (
                'mode' => 'super',
                'type' => 'user',
                'name' => 'tester',
                'host' => '*',
                'path' => '//...',
            ),
            2 => array(
                'mode'  => 'super',
                'type'  => 'user',
                'name'  => '*',
                'host'  => '*',
                'path'  => '//...'
            ),
        );
        $expected = array_slice($values, 0, 2);

        $protections = Protections::fetch();
        $protections->setProtections($values);
        $protections->removeProtection('super', 'user', '*', '*', '"//..."');

        // Verify instance reflects updated values via accessor
        $this->assertSame(
            $expected,
            $protections->getProtections(),
            'Expected instance values to match'
        );

        // test save.
        $protections->save();

        $protections = Protections::fetch();

        $this->assertSame(
            $expected,
            $protections->getProtections(),
            'Expected saved values to match'
        );
    }

    /**
     * Test adding values
     */
    public function testAdd()
    {
        $protections = Protections::fetch();
        $expected = array (
            0 => array (
                'mode' => 'write',
                'type' => 'user',
                'name' => '*',
                'host' => '*',
                'path' => '//...',
            ),
            1 => array (
                'mode' => 'super',
                'type' => 'user',
                'name' => 'tester',
                'host' => '*',
                'path' => '//...',
            ),
            2 => array(
                'mode'  => 'super',
                'type'  => 'user',
                'name'  => '*',
                'host'  => '*',
                'path'  => '//...'
            ),
        );

        $protections->addProtection('super', 'user', '*', '*', '//...');

        // Verify instance reflects updated values via accessor
        $this->assertSame(
            $expected,
            $protections->getProtections(),
            'Expected instance values to match'
        );

        // test save.
        $protections->save();

        $protections = Protections::fetch();

        $this->assertSame(
            $expected,
            $protections->getProtections(),
            'Expected saved values to match'
        );
    }
}
