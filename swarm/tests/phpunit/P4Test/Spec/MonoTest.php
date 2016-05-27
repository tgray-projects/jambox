<?php
/**
 * This is a test thoroughly exercises the SpecAbstract via the SpecMono class.
 * It is used to thoroughly exercise the base spec functionality so latter implementors
 * can focus on testing only their own additions/modifications.
 *
 * The actual spec type represented by SpecMono is of no importance and should not be considered
 * tested in this context.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Exception\Exception as SpecException;

class MonoTest extends TestCase
{
    /**
     * Constructor Test
     */
    public function testConstructor()
    {
        // Construct object with no passed values
        $mono = new MonoMock;

        $expected = array (
            'TypeMap' => null
        );

        $this->assertSame(
            $expected,
            $mono->get(),
            'Expected starting fields to match'
        );
    }

    /**
     * Test retrieval of the spec definition
     */
    public function testGetSpecDefinition()
    {
        $specDef = \P4\Spec\Definition::fetch('typemap');

        $this->assertSame(
            $specDef->getType(),
            'typemap',
            'Expected spec definition type to match'
        );

        $expected = array (
            'TypeMap' => array (
                'code' => '601',
                'dataType' => 'wlist',
                'displayLength' => '64',
                'fieldType' => 'default',
                'wordCount' => '2',
            ),
        );

        $this->assertSame(
            $expected,
            $specDef->getFields(),
            'Expected fields to match'
        );
    }

    /**
     * Test the has field function.
     */
    public function testHasField()
    {
        $tests = array (
            array (
                'label' => __LINE__ . " Empty String",
                'field' => '',
                'error' => true
            ),
            array (
                'label' => __LINE__ . " null",
                'field' => null,
                'error' => true
            ),
            array (
                'label' => __LINE__ . " bool",
                'field' => true,
                'error' => true
            ),
            array (
                'label' => __LINE__ . " int",
                'field' => 10,
                'error' => true
            ),
            array (
                'label' => __LINE__ . " float",
                'field' => 10.10,
                'error' => true
            ),
            array (
                'label' => __LINE__ . " bad field name",
                'field' => 'badField',
                'error' => true
            ),
            array (
                'label' => __LINE__ . " incorrect case",
                'field' => 'typeMap',
                'error' => true
            ),
            array (
                'label' => __LINE__ . " known good field",
                'field' => 'TypeMap',
                'error' => false
            ),
        );

        foreach ($tests as $test) {
            $mono = new MonoMock;

            $result = $mono->hasField($test['field']);

            if ($test['error']) {
                $this->assertFalse($result, 'Unexpected false: '. $test['label']);
            } else {
                $this->assertTrue($result, 'Unexpected true: '. $test['label']);
            }
        }
    }

    /**
     * Test get/setFields
     */
    public function testGetSetMulti()
    {
        $mono = new MonoMock;

        $expected = array (
            'TypeMap' => array('blah //...','etc //test/...','oneMore "//test with space/..."')
        );

        $mono = new MonoMock;
        $mono->set($expected);

        $this->assertSame(
            $expected,
            $mono->get(),
            'Expected passed fields to take'
        );
    }

    /**
     * Exercise get/set values with combinations of mutator/accessor
     */
    public function testGetSetMultiWithMutatorAccessor()
    {
        // Enable mutator and accessor and verify fields passed are affected
        $mono = new MonoMock;
        $mono->setProtected(
            'fields',
            array(
                'TypeMap' => array(
                    'accessor'  => 'getTypeMapAppendA',
                    'mutator'   => 'setTypeMapRemoveA'
                )
            )
        );

        $raw = array (
            'TypeMap' => array('blah //...','etc //test/...','oneMore "//test with space/..."')
        );
        $mutated = array (
            'TypeMap' => array('blah //...A','etc //test/...A','oneMore "//test with space/..."A')
        );

        $mono->set($mutated);

        $this->assertSame(
            $raw,
            $mono->callProtected('getRawValues'),
            'Expected getRawValues to match unmodified version'
        );

        $this->assertSame(
            $mutated,
            $mono->get(),
            'Expected get to match mutated version'
        );


        // Enable mutator only and verify fields passed are affected
        $mono = new MonoMock;
        $mono->setProtected(
            'fields',
            array(
                'TypeMap' => array(
                    'mutator'   => 'setTypeMapRemoveA'
                )
            )
        );

        $mono->set($mutated);

        $this->assertSame(
            $raw,
            $mono->callProtected('getRawValues'),
            'Expected getRawValues to match unmodified version'
        );

        $this->assertSame(
            $raw,
            $mono->get(),
            'Expected get to match raw version'
        );
    }

    /**
     * Test set raw values
     */
    public function testSetRawValues()
    {
        // Enable mutator and accessor and verify setRawValues is unaffected
        $mono = new MonoMock;
        $mono->setProtected(
            'fields',
            array(
                'TypeMap' => array(
                    'accessor'  => 'getTypeMapAppendA',
                    'mutator'   => 'setTypeMapRemoveA'
                )
            )
        );

        $raw = array (
            'TypeMap' => array('blah //...','etc //test/...','oneMore "//test with space/..."')
        );
        $mutated = array (
            'TypeMap' => array('blah //...A','etc //test/...A','oneMore "//test with space/..."A')
        );


        $mono->callProtected('setRawValues', array($raw));

        $this->assertSame(
            $raw,
            $mono->callProtected('getRawValues'),
            'Expected getRawValues to match unmodified version'
        );

        $this->assertSame(
            $mutated,
            $mono->get(),
            'Expected get to match mutated version'
        );
    }

    /**
     * Test get/set Value
     */
    public function testGetSet()
    {
        $mono = new MonoMock;

        $expected = array('blah //...','etc //test/...','oneMore "//test with space/..."');

        // Verify get value reflects set input
        $mono = new MonoMock;
        $mono->set(array('TypeMap' => $expected));

        $this->assertSame(
            $expected,
            $mono->get('TypeMap'),
            'Expected set to take'
        );

        // Verify get value reflects set value input
        $mono = new MonoMock;
        $mono->set('TypeMap', $expected);

        $this->assertSame(
            $expected,
            $mono->get('TypeMap'),
            'Expected set to take'
        );

        // Verify get values reflects set value input
        $this->assertSame(
            array('TypeMap' => $expected),
            $mono->get(),
            'Expected set to affect get'
        );
    }

    /**
     * Test get/set Value with mutator/accessor
     */
    public function testGetSetWithMutatorAccessor()
    {
        // Enable mutator and accessor and verify fields passed are affected
        $mono = new MonoMock;
        $mono->setProtected(
            'fields',
            array(
                'TypeMap' => array(
                    'accessor'  => 'getTypeMapAppendA',
                    'mutator'   => 'setTypeMapRemoveA'
                )
            )
        );

        $raw     = array('blah //...','etc //test/...','oneMore "//test with space/..."');
        $mutated = array('blah //...A','etc //test/...A','oneMore "//test with space/..."A');

        $mono->set('TypeMap', $mutated);

        $this->assertSame(
            $raw,
            $mono->callProtected('getRawValue', 'TypeMap'),
            'Expected getRawValue to match unmodified version'
        );

        $this->assertSame(
            $mutated,
            $mono->get('TypeMap'),
            'Expected get to match mutated version'
        );


        // Enable mutator only and verify fields passed are affected
        $mono = new MonoMock;
        $mono->setProtected(
            'fields',
            array(
                'TypeMap' => array(
                    'mutator'   => 'setTypeMapRemoveA'
                )
            )
        );

        $mono->set('TypeMap', $mutated);

        $this->assertSame(
            $raw,
            $mono->callProtected('getRawValue', 'TypeMap'),
            'Expected getRawValue to match unmodified version'
        );

        $this->assertSame(
            $raw,
            $mono->get('TypeMap'),
            'Expected get to match raw version'
        );
    }

    /**
     * Test getting a bad field fails
     */
    public function testGetBadField()
    {
        $mono = new MonoMock;

        $this->assertFalse(
            $mono->hasField('BadFieldName'),
            'Expected BadFieldName field would not exist'
        );

        try {
            $mono->get('BadFieldName');

            $this->fail('Expected get value of BadFieldName would fail');
        } catch (SpecException $e) {
            $this->assertSame(
                "Can't get the value of a non-existant field.",
                $e->getMessage(),
                'Unexpected message in exception'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected Exception ('. get_class($e) .'): '. $e->getMessage());
        }
    }

    /**
     * Test setting a bad field fails
     */
    public function testSetBadField()
    {
        $mono = new MonoMock;

        $this->assertFalse(
            $mono->hasField('BadFieldName'),
            'Expected BadFieldName field would not exist'
        );

        try {
            $mono->set('BadFieldName', 'blah');

            $this->fail('Expected set value of BadFieldName would fail');
        } catch (SpecException $e) {
            $this->assertSame(
                "Can't set the value of a non-existant field.",
                $e->getMessage(),
                'Unexpected message in exception'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected Exception ('. get_class($e) .'): '. $e->getMessage());
        }
    }

    /**
     * Test save.
     */
    public function testSave()
    {
        $values = array (
            'TypeMap' => array('ctext //...','xtext //test/...','xbinary "//test with space/..."')
        );

        $mono = new MonoMock;

        $this->assertNotSame(
            $values,
            $mono->get(),
            'Expected mono starting values to be different'
        );

        $mono->set($values);
        $mono->save();

        $this->assertSame(
            $values,
            $mono->get(),
            'Expected updated values to match'
        );

        // Get a fresh instance to verify it is also ok
        $mono = MonoMock::fetch();

        $this->assertSame(
            $values,
            $mono->get(),
            'Expected updated values to match in new instance'
        );

        // ensure new in-memory objects are still empty.
        $mono = new MonoMock;
        $this->assertSame(
            array('TypeMap' => null),
            $mono->get(),
            'Expected updated values to match in new instance'
        );
    }

    /**
     * Test get default value when no default is present
     */
    public function testGetDefaultValueNoDefault()
    {
        $mono = new MonoMock;

        $this->assertSame(
            null,
            $mono->callProtected('getDefaultValue', 'TypeMap'),
            'Expected default typemap value to match'
        );
    }

    /**
     * Test setRawValue are defensive for bad fields
     */
    public function testProtectedSetDefensive()
    {
        $mono = new MonoMock;

        $this->assertFalse(
            $mono->hasField('BadFieldName'),
            'Expected BadFieldName field would not exist'
        );

        try {
            $mono->callProtected('setRawValue', array('BadFieldName', 'blah'));

            $this->fail('Expected setRawValue of BadFieldName would fail');
        } catch (SpecException $e) {
            $this->assertSame(
                "Can't set the value of a non-existant field.",
                $e->getMessage(),
                'Unexpected message in exception'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected Exception ('. get_class($e) .'): '. $e->getMessage());
        }
    }

    /**
     * Test getRawValue are defensive for bad fields
     */
    public function testProtectedGetDefensive()
    {
        $mono = new MonoMock;

        $this->assertFalse(
            $mono->hasField('BadFieldName'),
            'Expected BadFieldName field would not exist'
        );

        try {
            $mono->callProtected('getRawValue', array('BadFieldName', 'blah'));

            $this->fail('Expected getRawValue of BadFieldName would fail');
        } catch (SpecException $e) {
            $this->assertSame(
                "Can't get the value of a non-existant field.",
                $e->getMessage(),
                'Unexpected message in exception'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected Exception ('. get_class($e) .'): '. $e->getMessage());
        }
    }
}
