<?php
/**
 * Test methods for the P4 Model Iterator.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Model\Fielded;

use P4\File\File;
use P4Test\TestCase;
use P4\Model\Fielded\Iterator as FieldedIterator;

class IteratorTest extends TestCase
{
    /**
     * Test the constructor
     */
    public function testConstructor()
    {
        // barebones construct
        $iterator = new FieldedIterator;
        $this->assertTrue($iterator instanceof FieldedIterator, 'should work');
        $this->assertEquals($iterator->count(), 0, 'Models size');

        // pass a non-array to the constructor
        $iterator = new FieldedIterator('bob');
        $this->assertTrue($iterator instanceof FieldedIterator, 'should work');
        $this->assertEquals($iterator->count(), 0, 'Models size');

        // pass bogus model to constructor
        try {
            $iterator = new FieldedIterator(array(1));
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals(
                'Models array contains one or more invalid elements.',
                $e->getMessage(),
                'Expected error message'
            );
        } catch (\Exception $e) {
            $this->assertFalse(true, 'Unexpected exception: '. $e->getMessage());
        }

        // pass a valid model to the constructor
        $file = new File;
        $iterator = new FieldedIterator(array($file));
        $this->assertTrue($iterator instanceof FieldedIterator, "should work");
        $this->assertEquals($iterator->count(), 1, 'Models size');

        // pass a mix array of valid/non-valid models to constructor
        try {
            $iterator = new FieldedIterator(array($file, $file, 1));
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals(
                'Models array contains one or more invalid elements.',
                $e->getMessage(),
                'Expected error message'
            );
        } catch (\Exception $e) {
            $this->assertFalse(true, 'Unexpected exception: '. $e->getMessage());
        }
    }

    /**
     * Test various iterator functionality
     */
    public function testIterator()
    {
        // instantiate an iterator
        $iterator = new FieldedIterator;

        // add a file
        $file1 = new File;
        $file1->setContentCache('one');
        $iterator[] = $file1;
        $this->assertEquals($iterator->count(), 1, 'expected size');
        $this->assertTrue($iterator->offsetExists(0), 'expected to exist');
        $this->assertFalse($iterator->offsetExists(1), 'expected not to exist');
        $this->assertFalse($iterator->offsetExists(2), 'expected not to exist');
        $this->assertFalse($iterator->offsetExists('bob'), 'expected not to exist');

        // add another file
        $file2 = new File;
        $file2->setContentCache('two');
        $iterator[] = $file2;
        $this->assertEquals($iterator->count(), 2, 'expected size');
        $this->assertTrue($iterator->offsetExists(0), 'expected to exist');
        $this->assertTrue($iterator->offsetExists(1), 'expected to exist');
        $this->assertFalse($iterator->offsetExists(2), 'expected not to exist');
        $this->assertFalse($iterator->offsetExists('bob'), 'expected not to exist');

        // rewind the iterator
        $iterator->rewind();
        $this->assertEquals($iterator->key(), 0, 'expected position');
        $this->assertTrue($iterator->valid(), 'should be valid');
        $aFile = $iterator->current();
        $this->assertEquals($aFile->getDepotContents(), 'one', 'expected file content');

        // retrieve an item from the iterator
        $iterator->next();
        $this->assertEquals($iterator->key(), 1, 'expected position');
        $this->assertTrue($iterator->valid(), 'should be valid');
        $aFile = $iterator->current();
        $this->assertEquals($aFile->getDepotContents(), 'two', 'expected file content');

        // retrieve another item from the iterator
        $iterator->next();
        $this->assertEquals($iterator->key(), null, 'expected invalid position');
        $this->assertFalse($iterator->valid(), 'should not be valid');

        // seek to position 1
        $iterator->seek(1);
        $this->assertEquals($iterator->key(), 1, 'expected position');
        $this->assertTrue($iterator->valid(), 'should be valid');
        $aFile = $iterator->current();
        $this->assertEquals($aFile->getDepotContents(), 'two', 'expected file content');

        // seek to position 0
        $iterator->seek(0);
        $this->assertEquals($iterator->key(), 0, 'expected position');
        $this->assertTrue($iterator->valid(), 'should be valid');
        $aFile = $iterator->current();
        $this->assertEquals($aFile->getDepotContents(), 'one', 'expected file content');

        // seek to a non-numeric position
        try {
            $iterator->seek('bob');
        } catch (\OutOfBoundsException $e) {
            $this->assertEquals(
                'Invalid seek position.',
                $e->getMessage(),
                'Expected error message'
            );
        } catch (\Exception $e) {
            $this->assertFalse(true, 'Unexpected exception: '. $e->getMessage());
        }

        // seek to a non-existant position
        try {
            $iterator->seek(123);
        } catch (\OutOfBoundsException $e) {
            $this->assertEquals(
                'Invalid seek position.',
                $e->getMessage(),
                'Expected error message'
            );
        } catch (\Exception $e) {
            $this->assertFalse(true, 'Unexpected exception: '. $e->getMessage());
        }

        // set a model at a specific offset
        $file3 = new File;
        $file3->setContentCache('bob');
        $iterator->offsetSet('bob', $file3);
        $this->assertEquals($iterator->count(), 3, 'expected size');
        $this->assertTrue($iterator->offsetExists(0), 'expected to exist');
        $this->assertTrue($iterator->offsetExists(1), 'expected to exist');
        $this->assertFalse($iterator->offsetExists(2), 'expected not to exist');
        $this->assertTrue($iterator->offsetExists('bob'), 'expected to exist');
        $this->assertEquals($iterator->key(), 'bob', 'expected position');
        $iterator->rewind();
        $this->assertEquals($iterator->key(), 0, 'expected position');

        // retrieve a model at a specific offsets
        $aFile = $iterator->offsetGet(1);
        $this->assertEquals($iterator->key(), 0, 'expected position');
        $this->assertEquals($aFile->getDepotContents(), 'two', 'expected file content');

        $aFile = $iterator->offsetGet('bob');
        $this->assertEquals($iterator->key(), '0', 'expected position');
        $this->assertEquals($aFile->getDepotContents(), 'bob', 'expected file content');

        try {
            $aFile = $iterator->offsetGet('does-not-exist');
            $this->fail('Expected error');
        } catch (\Exception $e) {
            $this->assertTrue(true);
        }

        // test foreach access
        $content = array();
        foreach ($iterator as $item) {
            $content[] = $item->getDepotContents();
        }
        $this->assertEquals(
            array('one', 'two', 'bob'),
            $content,
            'expected foreach content'
        );

        // test unset
        $iterator->offsetUnset('bob');
        $this->assertEquals($iterator->count(), 2, 'expected size');
        $this->assertTrue($iterator->offsetExists(0), 'expected to exist');
        $this->assertTrue($iterator->offsetExists(1), 'expected to exist');
        $this->assertFalse($iterator->offsetExists(2), 'expected not to exist');
        $this->assertFalse($iterator->offsetExists('bob'), 'expected not to exist');

    }

    /**
     *  Test php array-walk functions and their equivalence
     *  with class-defined counterparts.
     */
    public function testArrayWalk()
    {
        // init
        $iterator = new FieldedIterator;

        $file1 = new File;
        $file1->setContentCache('first');
        $iterator[] = $file1;

        $file2 = new File;
        $file2->setContentCache('second');
        $iterator[] = $file2;

        $file3 = new File;
        $file3->setContentCache('third-special');
        $iterator['special'] = $file3;

        $file4 = new File;
        $file4->setContentCache('fourth');
        $iterator[] = $file4;

        $file5 = new File;
        $file5->setContentCache('fifth');
        $iterator[] = $file5;

        // test php array-walk functions
        $iterator->rewind();
        $this->assertEquals($iterator->count(), 5, '-> expected size');
        $this->assertEquals(count($iterator), 5, 'expected size');
        $this->assertEquals($iterator->key(), '0', '-> expected position');
        $this->assertEquals(key($iterator), '0', 'expected position');
        $this->assertEquals($iterator->current()->getDepotContents(), 'first', '-> expected file contents');
        $this->assertEquals(current($iterator)->getDepotContents(), 'first', 'expected file contents');

        $iterator->next();
        $this->assertEquals($iterator->count(), 5, '-> expected size');
        $this->assertEquals(count($iterator), 5, 'expected size');
        $this->assertEquals($iterator->key(), '1', '-> expected position');
        $this->assertEquals(key($iterator), '1', 'expected position');
        $this->assertEquals($iterator->current()->getDepotContents(), 'second', '-> expected file contents');
        $this->assertEquals(current($iterator)->getDepotContents(), 'second', 'expected file contents');

        next($iterator);
        $this->assertEquals($iterator->key(), 'special', '-> expected position');
        $this->assertEquals(key($iterator), 'special', 'expected position');
        $this->assertEquals($iterator->current()->getDepotContents(), 'third-special', '-> expected file contents');
        $this->assertEquals(current($iterator)->getDepotContents(), 'third-special', 'expected file contents');

        $iterator->next();
        next($iterator);
        $this->assertFalse($iterator->next(), '-> expected not to exist');
        $this->assertFalse(next($iterator), 'expected not to exist');

        reset($iterator);
        $this->assertEquals($iterator->key(), '0', '-> expected position');
        $this->assertEquals(key($iterator), '0', 'expected position');
        $this->assertEquals(current($iterator)->getDepotContents(), 'first', 'expected file contents');
        $this->assertEquals($iterator->current()->getDepotContents(), 'first', '-> expected file contents');

        $iterator->next();
        next($iterator);
        next($iterator);
        $this->assertEquals(key($iterator), '2', 'expected position');
        $this->assertEquals($iterator->key(), '2', '-> expected position');
        $this->assertEquals(current($iterator)->getDepotContents(), 'fourth', 'expected file contents');
        $this->assertEquals($iterator->current()->getDepotContents(), 'fourth', '-> expected file contents');

        // test each() function
        $iterator->rewind();

        $currentPair = each($iterator);
        $this->assertEquals($currentPair['key'], '0', 'each() expected key');
        $this->assertSame($currentPair['value'], $file1, 'each() expected value');

        // check that array pointer has been advanced
        $this->assertEquals($iterator->key(), '1', '-> expected position');
        $this->assertEquals(key($iterator), '1', 'expected position');
        $this->assertEquals(current($iterator)->getDepotContents(), 'second', 'expected file contents');
        $this->assertEquals($iterator->current()->getDepotContents(), 'second', '-> expected file contents');

        $currentPair = each($iterator);
        $this->assertEquals($currentPair['key'], '1', 'each() expected key');
        $this->assertSame($currentPair['value'], $file2, 'each() expected value');

        // check that array pointer has been advanced
        $this->assertEquals($iterator->key(), 'special', '-> expected position');
        $this->assertEquals(key($iterator), 'special', 'expected position');
        $this->assertEquals(current($iterator)->getDepotContents(), 'third-special', 'expected file contents');
        $this->assertEquals($iterator->current()->getDepotContents(), 'third-special', '-> expected file contents');

        next($iterator);
        $iterator->next();
        $this->assertEquals($iterator->key(), '3', '-> key() expected key');
        $this->assertSame(current($iterator), $file5, 'current() expected value');

        $this->assertFalse($iterator->next(), 'expected out of bounds');
    }

    /**
     * Test invoke().
     */
    public function testInvoke()
    {
        $iterator = new FieldedIterator;

        $file1 = new File;
        $file1->setContentCache('f1');
        $iterator[] = $file1;

        $file2 = new File;
        $file2->setContentCache('f2');
        $iterator[] = $file2;

        $file3 = new File;
        $file3->setContentCache('f3');
        $iterator['special'] = $file3;

        $fileDepotContentsArray = $iterator->invoke('getDepotContents');
        $this->assertEquals(implode('-', $fileDepotContentsArray), 'f1-f2-f3', 'expected invoke result');
    }

    /**
     * Test exceptions with invoke().
     */
    public function testInvokeException()
    {
        $iterator = new FieldedIterator;

        $file1 = new File;
        $iterator[] = $file1;

        $file2 = new File;
        $iterator[] = $file2;

        // test exception if method doesnt exist
        try {
            $result = $iterator->invoke('nonexistentMethod99');
        } catch (\InvalidArgumentException $expected) {
            return;
        }

        $this->fail(
            'An expected exception InvalidArgumentException has not been raised '
            . 'when trying invoke nonexistent method.'
        );
    }

    /**
     * Test retrieve the first iterator item.
     */
    public function testFirst()
    {
        $iterator = new FieldedIterator;

        for ($i = 0; $i < 10; $i++) {
            $file = new File;
            $file->setFilespec('//depot/' . $i);
            $iterator[] = $file;
        }

        $this->assertTrue($iterator->first()->getFilespec() == '//depot/0');
        $this->assertTrue($iterator->last()->getFilespec() == '//depot/9');
    }

    /**
     * Test appending to an iterator.
     */
    public function testAppend()
    {
        $iterator   = new FieldedIterator;
        $iterator[] = new File;
        try {
            $iterator[] = 123;
            $this->fail();
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Test searching feature of iterator.
     */
    public function testSearch()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null fields, null query, null options',
                'fields'    => null,
                'query'     => null,
                'options'   => null,
                'expected'  => array()
            ),
            array(
                'label'     => __LINE__ .': null fields, query, null options',
                'fields'    => null,
                'query'     => 'A',
                'options'   => null,
                'expected'  => array()
            ),
            array(
                'label'     => __LINE__ .': fields, query, null options',
                'fields'    => array('bar'),
                'query'     => 'A',
                'options'   => null,
                'expected'  => array('A', 'a')
            ),
            array(
                'label'     => __LINE__ .': fields, query, null options',
                'fields'    => array('bar'),
                'query'     => 'A',
                'options'   => array(FieldedIterator::FILTER_CONTAINS),
                'expected'  => array('A')
            ),
            array(
                'label'     => __LINE__ .': field mismatch, query, null options',
                'fields'    => array('baz'),
                'query'     => 'A',
                'options'   => null,
                'expected'  => array()
            ),
            array(
                'label'     => __LINE__ .': multiple fields, query, null options',
                'fields'    => array('foo', 'baz'),
                'query'     => '3',
                'options'   => array(FieldedIterator::FILTER_CONTAINS),
                'expected'  => array('C', 'c')
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            // test the contents prior to filtering
            $results = $this->getTestIterator()->search($test['fields'], $test['query'], $test['options']);
            $actual = $results->sortBy('bar')->invoke('get', array('bar'));
            $this->assertSame($test['expected'], $actual, "$label - expected search results");
        }
    }

    /**
     * Test filtering feature of iterator.
     */
    public function testFilter()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null fields, null values, null options',
                'values'    => null,
                'options'   => null,
                'expected'  => array(),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, null values, inverse filter',
                'values'    => null,
                'options'   => array(FieldedIterator::FILTER_INVERSE),
                'expected'  => array('A', 'B', 'C', 'a', 'b', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, simple match values, null options',
                'values'    => array('A', 'C'),
                'options'   => null,
                'expected'  => array('A', 'C'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, simple match values, inverse filter',
                'values'    => array('A', 'C'),
                'options'   => array(FieldedIterator::FILTER_INVERSE),
                'expected'  => array('B', 'a', 'b', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, regex match values, null options',
                'values'    => array('(3|5|6)'),
                'options'   => array(FieldedIterator::FILTER_REGEX),
                'expected'  => array('C', 'b', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, regex match values, inverse filter',
                'values'    => array('(3|5|6)'),
                'options'   => array(FieldedIterator::FILTER_REGEX, FieldedIterator::FILTER_INVERSE),
                'expected'  => array('A', 'B', 'a'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, simple match values, no case',
                'values'    => array('a'),
                'options'   => array(FieldedIterator::FILTER_NO_CASE),
                'expected'  => array('A', 'a'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, simple match values, no case inverse filter',
                'values'    => array('a'),
                'options'   => array(FieldedIterator::FILTER_NO_CASE, FieldedIterator::FILTER_INVERSE),
                'expected'  => array('B', 'C', 'b', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, regex match values, no case',
                'values'    => array('/b/'),
                'options'   => array(FieldedIterator::FILTER_REGEX, FieldedIterator::FILTER_NO_CASE),
                'expected'  => array('B', 'b'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, regex match values, no case inversed filter',
                'values'    => array('/B/'),
                'options'   => array(
                    FieldedIterator::FILTER_REGEX,
                    FieldedIterator::FILTER_NO_CASE,
                    FieldedIterator::FILTER_INVERSE
                ),
                'expected'  => array('A', 'C', 'a', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, contains match values',
                'values'    => array('0'),
                'options'   => array(FieldedIterator::FILTER_CONTAINS),
                'expected'  => array('B', 'a', 'b'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, contains match values, inverted filter',
                'values'    => array('0'),
                'options'   => array(FieldedIterator::FILTER_CONTAINS, FieldedIterator::FILTER_INVERSE),
                'expected'  => array('A', 'C', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, match all values #1',
                'values'    => array('/3/', '/e/'),
                'options'   => array(FieldedIterator::FILTER_MATCH_ALL, FieldedIterator::FILTER_REGEX),
                'expected'  => array('C', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, match all values #2',
                'values'    => array('/3/', '/e/', '/c/'),
                'options'   => array(FieldedIterator::FILTER_MATCH_ALL, FieldedIterator::FILTER_REGEX),
                'expected'  => array('c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, match all values #3, no case',
                'values'    => array('/3/', '/e/', '/c/'),
                'options'   => array(
                    FieldedIterator::FILTER_MATCH_ALL,
                    FieldedIterator::FILTER_REGEX,
                    FieldedIterator::FILTER_NO_CASE
                ),
                'expected'  => array('C', 'c'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, match all values #4, no case inverted filter',
                'values'    => array('/3/', '/e/', '/c/'),
                'options'   => array(
                    FieldedIterator::FILTER_MATCH_ALL,
                    FieldedIterator::FILTER_REGEX,
                    FieldedIterator::FILTER_NO_CASE,
                    FieldedIterator::FILTER_INVERSE
                ),
                'expected'  => array('A', 'B', 'a', 'b'),
                'copy'      => false,
            ),
            array(
                'label'     => __LINE__ .': null fields, null values, with copy',
                'values'    => null,
                'options'   => array(FieldedIterator::FILTER_COPY),
                'expected'  => array(),
                'copy'      => true,
            ),
            array(
                'label'     => __LINE__ .': null fields, simple match values, with copy',
                'values'    => array('A'),
                'options'   => array(FieldedIterator::FILTER_COPY),
                'expected'  => array('A'),
                'copy'      => true,
            ),
            array(
                'label'     => __LINE__ .': null fields, boolean values',
                'values'    => array(false),
                'options'   => array(FieldedIterator::FILTER_COPY),
                'expected'  => array(false),
                'copy'      => true,
                'extraData' => array(true, false, true),
            ),
            array(
                'label'     => __LINE__ .': null fields, object values',
                'values'    => array('@'),
                'options'   => array(FieldedIterator::FILTER_COPY),
                'expected'  => array('@'),
                'copy'      => true,
                'extraData' => array(new \stdClass, '@', new \stdClass),
            ),
            array(
                'label'     => __LINE__ .': null fields, nested values, implode enabled',
                'values'    => array('Z'),
                'options'   => array(FieldedIterator::FILTER_IMPLODE, FieldedIterator::FILTER_CONTAINS),
                'expected'  => array(array('@', 'Y', 'Z')),
                'copy'      => false,
                'extraData' => array(9, array('@', 'Y', 'Z'), new \stdClass),
            ),
            array(
                'label'     => __LINE__ .': null fields, nested values, implode disabled',
                'values'    => array('Z'),
                'options'   => array(FieldedIterator::FILTER_CONTAINS),
                'expected'  => array(),
                'copy'      => false,
                'extraData' => array(9, array('@', 'Y', 'Z'), new \stdClass),
            ),
            array(
                'label'     => __LINE__ .': null fields, null value',
                'values'    => array(null),
                'options'   => array(FieldedIterator::FILTER_CONTAINS),
                'expected'  => array(1),
                'copy'      => false,
                'extraData' => array(new \stdClass, 1, null),
            ),
            array(
                'label'     => __LINE__ .': null fields, \'null\' value',
                'values'    => array(null),
                'options'   => array(FieldedIterator::FILTER_CONTAINS),
                'expected'  => array(),
                'copy'      => false,
                'extraData' => array(new \stdClass, 1, 'null'),
            )
            // see testSearch for tests involving specified/multiple fields
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            // test the contents prior to filtering
            $extraData = null;
            $original = array('A', 'B', 'C', 'a', 'b', 'c');
            if (array_key_exists('extraData', $test)) {
                $extraData = $test['extraData'];
                array_unshift($original, $extraData[1]);
            }
            $iterator = $this->getTestIterator($extraData);
            $actual = $iterator->sortBy('bar')->invoke('get', array('bar'));
            $this->assertSame($original, $actual, "$label - expected initial items");

            // test the content after filtering
            $copy = $iterator->filter(null, $test['values'], $test['options']);
            $actual = $copy->sortBy('bar')->invoke('get', array('bar'));
            $this->assertSame($test['expected'], $actual, "$label - expected final items");

            // verify modification, or lack thereof, to original iterator
            if ($test['copy']) {
                $actual = $iterator->sortBy('bar')->invoke('get', array('bar'));
                $this->assertSame($original, $actual, "$label - expected no iterator change");
            } else {
                $expected = $actual;
                $actual = $iterator->sortBy('bar')->invoke('get', array('bar'));
                $this->assertSame($expected, $actual, "$label - expected iterator change");
            }
        }
    }

    /**
     * Test sorting feature of iterator.
     */
    public function testSort()
    {
        $tests = array(
            array(
                'label'         => __LINE__ .': default sort',
                'field'         => 'bar',
                'options'       => array(),
                'expected'      => array('A', 'B', 'C', 'a', 'b', 'c'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': alpha sort #1',
                'field'         => 'bar',
                'options'       => array(FieldedIterator::SORT_ALPHA),
                'expected'      => array('A', 'B', 'C', 'a', 'b', 'c'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': alpha sort #2',
                'field'         => 'baz',
                'options'       => array(FieldedIterator::SORT_ALPHA),
                'expected'      => array('test 1', 'test 10', 'test 3', 'test 40', 'test 500', 'test 6'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': reverse sort',
                'field'         => 'bar',
                'options'       => array(FieldedIterator::SORT_DESCENDING),
                'expected'      => array('c', 'b', 'a', 'C', 'B', 'A'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': case-insensitive sort',
                'field'         => 'bar',
                'options'       => array(FieldedIterator::SORT_NO_CASE),
                'expected'      => array('a', 'a', 'b', 'b', 'c', 'c'),
                'expectedLower' => true,
            ),
            array(
                'label'         => __LINE__ .': reversed case-insensitive sort',
                'field'         => 'bar',
                'options'       => array(FieldedIterator::SORT_NO_CASE, FieldedIterator::SORT_DESCENDING),
                'expected'      => array('c', 'c', 'b', 'b', 'a', 'a'),
                'expectedLower' => true,
            ),
            array(
                'label'         => __LINE__ .': numeric sort',
                'field'         => 'foo',
                'options'       => array(FieldedIterator::SORT_NUMERIC),
                'expected'      => array(1, 1, 2, 2, 3, 3),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': reversed numeric sort',
                'field'         => 'foo',
                'options'       => array(FieldedIterator::SORT_NUMERIC, FieldedIterator::SORT_DESCENDING),
                'expected'      => array(3, 3, 2, 2, 1, 1),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': natural sort',
                'field'         => 'baz',
                'options'       => array(FieldedIterator::SORT_NATURAL),
                'expected'      => array('test 1', 'test 3', 'test 6', 'test 10', 'test 40', 'test 500'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': natural, case-insensitive sort',
                'field'         => 'baz',
                'options'       => array(FieldedIterator::SORT_NATURAL, FieldedIterator::SORT_NO_CASE),
                'expected'      => array('test 1', 'test 3', 'test 6', 'test 10', 'test 40', 'test 500'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': reversed natural sort',
                'field'         => 'baz',
                'options'       => array(FieldedIterator::SORT_NATURAL, FieldedIterator::SORT_DESCENDING),
                'expected'      => array('test 500', 'test 40', 'test 10', 'test 6', 'test 3', 'test 1'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': key/value natural sort #1',
                'field'         => 'baz',
                'options'       => array(FieldedIterator::SORT_NATURAL => true),
                'expected'      => array('test 1', 'test 3', 'test 6', 'test 10', 'test 40', 'test 500'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': key/value natural sort #2',
                'field'         => array(
                    array('baz', array(FieldedIterator::SORT_NATURAL)),
                 ),
                'options'       => array(),
                'expected'      => array(
                    array('test 1', 'test 3', 'test 6', 'test 10', 'test 40', 'test 500'),
                ),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': fixed sort - all specified',
                'field'         => 'bar',
                'options'       => array(FieldedIterator::SORT_FIXED => array('B', 'a', 'C', 'A', 'b', 'c')),
                'expected'      => array('B', 'a', 'C', 'A', 'b', 'c'),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': fixed sort - some specified',
                'field'         => 'bar',
                'options'       => array(FieldedIterator::SORT_FIXED => array('B', 'a', 'C')),
                'expected'      => array('B', 'a', 'C', 'A', 'b', 'c'),
                'expectedLower' => false,
            ),

            array(
                'label'         => __LINE__ .': nested sort',
                'field'         => array('foo', 'baz'),
                'options'       => array(FieldedIterator::SORT_NATURAL),
                'expected'      => array(
                    array(1, 1, 2, 2, 3, 3),
                    array('test 1', 'test 40', 'test 10', 'test 500', 'test 3', 'test 6'),
                ),
                'expectedLower' => false,
            ),
            array(
                'label'         => __LINE__ .': nested sort, separate options',
                'field'         => array(
                    'foo' => array(FieldedIterator::SORT_DESCENDING),
                    'baz' => array(FieldedIterator::SORT_NATURAL)
                ),
                'options'       => array(),
                'expected'      => array(
                    array(3, 3, 2, 2, 1, 1),
                    array('test 3', 'test 6', 'test 10', 'test 500', 'test 1', 'test 40'),
                ),
                'expectedLower' => false,
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            $iterator = $this->getTestIterator()->sortBy($test['field'], $test['options']);

            $invokeList = $test['field'];
            $expectedList = $test['expected'];
            if (!is_array($test['field'])) {
                $invokeList = (array) $test['field'];
                $expectedList = array($expectedList);
            }

            foreach ($invokeList as $invokeWith => $value) {
                $expected = array_shift($expectedList);

                if (is_integer($invokeWith)) {
                    $invokeWith = is_array($value) ? $value[0] : $value;
                }

                $actual = $iterator->invoke('get', array($invokeWith));
                if ($test['expectedLower']) {
                    $actual = array_map('strtolower', $actual);
                }
                $this->assertSame($expected, $actual, "$label - $invokeWith - expected order");
            }
        }
    }

    /**
     * Test sorting the same iterator twice.
     */
    public function testSortTwice()
    {
        // test default (alpha, asc) sort.
        $iterator = $this->getTestIterator()->sortBy('bar');
        $this->assertSame(
            array('A', 'B', 'C', 'a', 'b', 'c'),
            $iterator->invoke('get', array('bar')),
            "Expected alphabetical, ascending order."
        );

        // test natural sort (using same iterator).
        $iterator->sortBy(
            'baz',
            array(FieldedIterator::SORT_NATURAL)
        );
        $this->assertSame(
            array('test 1', 'test 3', 'test 6', 'test 10', 'test 40', 'test 500'),
            $iterator->invoke('get', array('baz')),
            "Expected natural, ascending order."
        );
    }

    /**
     * Test toArray()
     */
    public function testToArray()
    {
        $iterator = $this->getTestIterator()->sortBy('bar');
        $expected = array('A', 'B', 'C', 'a', 'b', 'c');
        $this->assertSame(
            $expected,
            $iterator->invoke('get', array('bar')),
            "Expected alphabetical, ascending order."
        );
        $this->assertFalse(is_array($iterator), 'Iterator should not be an array.');

        // shallow test
        $array = $iterator->toArray(true);
        $this->assertTrue(is_array($array), 'After toArray shallow, should have an array.');
        $actual = array();
        foreach ($array as $item) {
            $actual[] = $item->get('bar');
        }
        $this->assertSame($expected, $actual, 'After toArray shallow, contents should match');

        // deep test
        $this->assertSame(
            array(
                array(
                    'foo'   => 1,
                    'bar'   => 'A',
                    'baz'   => 'test 1'
                ),
                array(
                    'foo'   => 2,
                    'bar'   => 'B',
                    'baz'   => 'test 10'
                ),
                array(
                    'foo'   => 3,
                    'bar'   => 'C',
                    'baz'   => 'test 3'
                ),
                array(
                    'foo'   => 1,
                    'bar'   => 'a',
                    'baz'   => 'test 40'
                ),
                array(
                    'foo'   => 2,
                    'bar'   => 'b',
                    'baz'   => 'test 500'
                ),
                array(
                    'foo'   => 3,
                    'bar'   => 'c',
                    'baz'   => 'test 6'
                ),
            ),
            array_values($iterator->toArray()),
            'after toArray deep, contents should match'
        );
    }

    /**
     * Test sort w. unexpected options.
     */
    public function testSortBadOptions()
    {
        $tests = array(
            array(
                'label'     => __LINE__ .': null field, null options',
                'field'     => null,
                'options'   => null,
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot sort. Sort options must be an array.'
                ),
            ),
            array(
                'label'     => __LINE__ .': null field, non-array options',
                'field'     => null,
                'options'   => 'bob',
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot sort. Sort options must be an array.'
                ),
            ),
            array(
                'label'     => __LINE__ .': field w/non-array options',
                'field'     => array('bob' => 'bob'),
                'options'   => array(),
                'error'     => array(
                    'InvalidArgumentException' => 'Cannot sort. Invalid sort field(s) given.'
                ),
            ),
            array(
                'label'     => __LINE__ .': field, unknown option',
                'field'     => 'foo',
                'options'   => array('bob'),
                'error'     => array(
                    'InvalidArgumentException' => 'Unexpected sort option(s) encountered.'
                ),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];

            try {
                $iterator = $this->getTestIterator()->sortBy($test['field'], $test['options']);
                $this->fail("$label - Unexpected success.");
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\PHPUnit\Framework\ExpectationFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                list($class, $error) = each($test['error']);
                $this->assertEquals(
                    $class,
                    get_class($e),
                    "$label - expected exception class: ". $e->getMessage()
                );
                $this->assertEquals(
                    $error,
                    $e->getMessage(),
                    "$label - expected exception message"
                );
            }
        }
    }

    /**
     * Test sorting with a callback function.
     */
    public function testSortCallback()
    {
        $iterator = $this->getTestIterator();
        $callback = function ($a, $b) {
            return strnatcasecmp($a->get('baz'), $b->get('baz'));
        };

        // ensure it requires a callback.
        try {
            $iterator->sortByCallback(null);
            $this->fail('Expected exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }

        // ensure sort works.
        $iterator->sortByCallback($callback);

        $this->assertSame(
            array('test 1', 'test 3', 'test 6', 'test 10', 'test 40', 'test 500'),
            $iterator->invoke('get', array('baz'))
        );
    }

    /**
     * Exercise starts-with filter option.
     */
    public function testFilterStartsWith()
    {
        $iterator = $this->getTestIterator();
        $this->assertSame(
            2,
            $iterator->filter(null, 'test 1', array('STARTS_WITH'))->count()
        );
    }

    /**
     * Get a iterator to test sorting.
     *
     * @param   array|null  $extraData  An optional extra data array to include in the iterator.
     * @return  FieldedIterator         the test iterator
     */
    protected function getTestIterator($extraData = null)
    {
        $data = array(
            array(1, 'A', 'test 1'),
            array(2, 'B', 'test 10'),
            array(3, 'C', 'test 3'),
            array(1, 'a', 'test 40'),
            array(2, 'b', 'test 500'),
            array(3, 'c', 'test 6'),
        );

        // facility to add some extra data to the iterator,
        // which is used in testFilter.
        if (isset($extraData)) {
            $data[] = $extraData;
        }

        // randomize input data.
        shuffle($data);

        $iterator = new FieldedIterator;
        foreach ($data as $entry) {
            $model = new Implementation;
            $model->set(
                array(
                    'foo' => $entry[0],
                    'bar' => $entry[1],
                    'baz' => $entry[2]
                )
            );
            $iterator[] = $model;
        }

        return $iterator;
    }
}
