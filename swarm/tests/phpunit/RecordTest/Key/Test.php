<?php
/**
 * Tests for the Key model.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace RecordTest\Key;

use RecordTest\Key\KeyMock;
use P4Test\TestCase;
use P4\Key\Key;
use P4\Log\Logger;
use Zend\Log\Logger as ZendLogger;
use Zend\Log\Writer\Mock as MockLog;

class Test extends TestCase
{
    public function testBasicFunction()
    {
        $model = new KeyMock($this->p4);
    }

    public function testFetchAllEmpty()
    {
        $models = KeyMock::fetchAll(array(), $this->p4);

        $this->assertInstanceOf('P4\Model\Fielded\Iterator', $models);
        $this->assertSame(
            array(),
            $models->toArray(),
            'expected matching result on empty fetch'
        );
    }

    public function testSaveAndFetchAndExists()
    {
        $model = new KeyMock($this->p4);
        $model->set('test', '1');
        $model->save();
        $model = new KeyMock($this->p4);
        $model->set('test', '2');
        $model->save();

        $models = KeyMock::fetchAll(array(), $this->p4);
        $this->assertSame(
            2,
            count($models),
            'expected matching number of records'
        );

        $this->assertSame(
            1,
            reset($models)->getId(),
            'expected matching first id'
        );
        $this->assertSame(
            2,
            next($models)->getId(),
            'expected matching second id'
        );

        $this->assertTrue(
            KeyMock::exists(1, $this->p4),
            'expected key 1 to exist'
        );
        $this->assertTrue(
            KeyMock::exists(2, $this->p4),
            'expected key 2 to exist'
        );
        $this->assertFalse(
            KeyMock::exists(3, $this->p4),
            'expected key 3 to not exist'
        );

        $this->assertSame(
            array(1),
            KeyMock::exists(array(1), $this->p4)
        );
        $this->assertSame(
            array(1),
            KeyMock::exists(array(1, 5, 9), $this->p4)
        );
        $this->assertSame(
            array(1, 2),
            KeyMock::exists(array(1, 5, 9, 2, 100), $this->p4)
        );
    }

    public function testCustomId()
    {
        $model = new KeyMock($this->p4);
        $model->setId('test1');
        $model->set('test', '1');
        $model->save();
        $model = new KeyMock($this->p4);
        $model->setId('test2');
        $model->set('test', '2');
        $model->save();

        $models = KeyMock::fetchAll(array(), $this->p4);
        $this->assertSame(
            2,
            count($models),
            'expected matching number of records'
        );

        $this->assertSame(
            'test1',
            reset($models)->getId(),
            'expected matching first id'
        );
        $this->assertSame(
            'test2',
            next($models)->getId(),
            'expected matching second id'
        );
    }

    public function testDelete()
    {
        $model = new KeyMock($this->p4);
        $model->set('test',    '1');
        $model->set('type',    'foo');
        $model->set('streams', array('one', 'two'));
        $model->save();

        $result = $this->p4->run('search', array('1001=' . strtoupper(bin2hex('foo'))));
        $this->assertSame(1, count($result->getData()));
        $result = $this->p4->run('search', array('1002=' . strtoupper(bin2hex('one'))));
        $this->assertSame(1, count($result->getData()));
        $result = $this->p4->run('search', array('1002=' . strtoupper(bin2hex('two'))));
        $this->assertSame(1, count($result->getData()));
        $result = $this->p4->run('counters', array('-u'));
        $this->assertSame(2, count($result->getData()));

        $model->delete();

        $result = $this->p4->run('search', array('1001=' . strtoupper(bin2hex('foo'))));
        $this->assertSame(0, count($result->getData()));
        $result = $this->p4->run('search', array('1002=' . strtoupper(bin2hex('one'))));
        $this->assertSame(0, count($result->getData()));
        $result = $this->p4->run('search', array('1002=' . strtoupper(bin2hex('two'))));
        $this->assertSame(0, count($result->getData()));
        $result = $this->p4->run('counters', array('-u'));
        $this->assertSame(1, count($result->getData()));
    }

    public function testFetchAllWithGaps()
    {
        // add an entry, should get id 1
        $model = new KeyMock($this->p4);
        $model->set('test', '1')
              ->save();

        // increment the count twice without adding anything
        $key = new Key($this->p4);
        $key->setId(KeyMock::KEY_COUNT);
        $key->increment();
        $key->increment();

        // add what should be entry 4
        $model = new KeyMock($this->p4);
        $model->set('test', '4')
              ->save();

        // add what should be entry 5
        $model = new KeyMock($this->p4);
        $model->set('test', '5')
              ->save();

        $result = KeyMock::fetchAll(array(KeyMock::FETCH_MAXIMUM => 2), $this->p4);
        $this->assertInstanceOf('P4\Model\Fielded\Iterator', $result);
        $this->assertSame(
            2,
            count($result),
            'expected to get two results'
        );
    }

    public function testEditDeIndexes()
    {
        $streams = array('streama', 'streamb', 'streamc');
        $model   = new KeyMock($this->p4);
        $model->set('test',    '1')
              ->set('type',    'woozle')
              ->set('streams', $streams)
              ->addStream('streamd')
              ->save();

        $result = KeyMock::fetchAll(array('streams' => 'streamd'), $this->p4);
        $this->assertSame(
            1,
            count($result),
            'Expected one result when indexed for streamd'
        );

        $model = KeyMock::fetch(1, $this->p4);

        // ensure it only takes four commands to update the record as we:
        // - read out the current values
        // - delete the current 'streams' index
        // - write a new 'streams' index
        // - write out the new values
        // we want to verify the 'type' index isn't touched which would add 2 commands
        $original = Logger::hasLogger() ? Logger::getLogger() : null;
        $logger   = new ZendLogger;
        $mock     = new MockLog;
        $logger->addWriter($mock);
        Logger::setLogger($logger);

        $model->set('streams', $streams)
              ->save();

        // verify only 4 commands were run
        $this->assertSame(4, count($mock->events));

        // restore original logger if there is one.
        Logger::setLogger($original);

        // double check the data and index's were correctly updated
        $this->assertSame(
            count($streams),
            count($model->get('streams')),
            'expected one stream would have been unset'
        );

        $result = KeyMock::fetchAll(array('streams' => 'streamd'), $this->p4);
        $this->assertSame(
            0,
            count($result),
            'Expected no result after save without streamd'
        );

        $result = KeyMock::fetchAll(array('streams' => 'streama'), $this->p4);
        $this->assertSame(
            1,
            count($result),
            'Expected to still get a hit for streama'
        );
    }

    public function testEmptySearch()
    {
        $model = new KeyMock($this->p4);
        $model->set('test', '1')
              ->set('type', 'woozle')
              ->save();

        $model = new KeyMock($this->p4);
        $model->set('test', '2')
              ->set('type', 'woozle')
              ->addStream('stream')
              ->save();

        $result = KeyMock::fetchAll(array('streams' => false), $this->p4);
        $this->assertSame(
            1,
            count($result),
            'Expected one result when searching for false stream'
        );

        $this->assertSame(
            '1',
            current($result)->get('test'),
            'expected correct entry'
        );

        // verify passing empty string just returns everything
        $result = KeyMock::fetchAll(array('streams' => ''), $this->p4);
        $this->assertSame(
            2,
            count($result),
            'Expected two result when searching for empty string stream'
        );
    }

    public function searchProvider()
    {
        return array(
            // verify 'type' indices are working correctly
            'by-type'       => array(array('type'    => 'type'),                        array()),
            'by-typea'      => array(array('type'    => 'type-a'),                      array('1', '3')),
            'by-typeb'      => array(array('type'    => 'type-b'),                      array('2', '4')),

            // verify 'stream' indices are working
            'by-streamj'    => array(array('streams' => 'just'),                        array()),
            'by-streamjb'   => array(array('streams' => 'just-b'),                      array('2')),
            'by-streama'    => array(array('streams' => 'a'),                           array('1', '2', '3', '4')),
            'by-streamb'    => array(array('streams' => 'b'),                           array('2', '4')),
            'by-streamc'    => array(array('streams' => 'c'),                           array('3', '4')),
            'by-streamd'    => array(array('streams' => 'd'),                           array('4')),

            // verify combining type and stream gives expected results
            'by-jb-and-tb'  => array(array('streams' => 'just-b', 'type' => 'type-b'),  array('2')),
            'by-b-and-tb'   => array(array('streams' => 'b', 'type' => 'type-b'),       array('2', '4')),
            'by-a-and-tb'   => array(array('streams' => 'a', 'type' => 'type-b'),       array('2', '4')),
            'by-a-and-ta'   => array(array('streams' => 'a', 'type' => 'type-a'),       array('1', '3')),

            // verify seaching by array-value
            'by-atype'      => array(array('type' => array()),                          array()),
            'by-atype'      => array(array('type' => array('type')),                    array()),
            'by-atypea'     => array(array('type' => array('type-a')),                  array('1', '3')),
            'by-atypeab'    => array(array('type' => array('type-a', 'type-b')),        array('1', '2', '3', '4')),
            'by-asbc'       => array(array('streams' => array('b', 'c')),               array('2', '3', '4')),
            'by-atb-ascd'   => array(
                array('type' => 'type-b', 'streams' => array('c', 'd')),
                array('4')
            ),
            'by-ascd-atn'   => array(
                array('streams' => array('c', 'd'), 'type' => null),
                array('3', '4')
            ),
            'by-ascd-atb'   => array(
                array('streams' => array('c', 'd'), 'type' => 'type-b'),
                array('4')
            ),
            'by-ascdje-atab'=> array(
                array('streams' => array('c', 'd', 'just-a'), 'type' => array('type-a', 'type-b')),
                array('1', '3', '4')
            ),
            'by-ascfoo-atn' => array(
                array('streams' => array('c', 'foo'), 'type' => array('no-a', 'no-b')),
                array()
            ),
            'by-ascfoo-atan'=> array(
                array('streams' => array('c', 'foo'), 'type' => array('type-a', 'no-a')),
                array('3')
            ),
            'by-sa-ata'     => array(
                array('streams' => 'a', 'type' => array('type-a')),
                array('1', '3')
            ),
            'by-asa-ta'     => array(
                array('streams' => array('a'), 'type' => 'type-a'),
                array('1', '3')
            ),
            'by-asa-ata'    => array(
                array('streams' => array('a'), 'type' => array('type-a')),
                array('1', '3')
            ),

            // try max, after and combinations
            'by-max'        => array(array('maximum' => '2'),                           array('1', '2')),
            'by-after'      => array(array('after'   => 3),                             array('4', '5')),
            'by-max-after'  => array(array('after'   => 1, 'maximum' => '2'),           array('2', '3')),

            // try max and streams/type
            'by-sa-m0'      => array(array('streams' => 'a', 'maximum' => '0'),         array('1', '2', '3', '4')),
            'by-sb-m0'      => array(array('streams' => 'b', 'maximum' => '0'),         array('2', '4')),
            'by-sa-m1'      => array(array('streams' => 'a', 'maximum' => '1'),         array('1')),
            'by-sa-m2'      => array(array('streams' => 'a', 'maximum' => '2'),         array('1', '2')),
            'by-ta-m1'      => array(array('type'    => 'type-a', 'maximum' => '1'),    array('1')),
            'by-ta-m2'      => array(array('type'    => 'type-a', 'maximum' => '2'),    array('1', '3')),

            // try after and streams/type
            'by-sa-a0'      => array(array('streams' => 'a', 'after' => '0'),           array()),
            'by-sa-a1'      => array(array('streams' => 'a', 'after' => '1'),           array('2', '3', '4')),
            'by-sa-a2'      => array(array('streams' => 'a', 'after' => '2'),           array('3', '4')),
            'by-sa-a3'      => array(array('streams' => 'a', 'after' => '3'),           array('4')),
            'by-sb-a3'      => array(array('streams' => 'b', 'after' => '3'),           array()),
            'by-sa-a4'      => array(array('streams' => 'a', 'after' => '4'),           array()),
            'by-ta-a1'      => array(array('type'    => 'type-a', 'after' => '1'),      array('3')),

            // try max, after and streams
            'by-s1-a1-m0'   => array(array('streams' => 'a', 'after' => '1', 'maximum' => 0),   array('2', '3', '4')),
            'by-sb-a1-m0'   => array(array('streams' => 'b', 'after' => '1', 'maximum' => 0),   array()),
            'by-sa-a1-m1'   => array(array('streams' => 'a', 'after' => '1', 'maximum' => 1),   array('2')),
            'by-sa-a1-m2'   => array(array('streams' => 'a', 'after' => '1', 'maximum' => 2),   array('2', '3')),

            // verify search by keyword and search keywords fields
            'words-1'       => array(array('keywords' => 'xyz'), array()),
            'words-2'       => array(array('keywords' => 'up LOW'), array('5')),
            'words-3'       => array(array('keywords' => 'Low'), array('3', '5')),
            'words-4'       => array(array('keywords' => 'bar'), array('5')),
            'words-5'       => array(array('keywords' => 'bar', 'keywordsFields' => array('streams')), array()),
            'words-6'       => array(
                array('keywords' => 'bar', 'keywordsFields' => array('noexist', 'words')),
                array('5')
            ),
            'words-7'       => array(array('keywords' => 'Bar', 'keywordsFields' => array('streams')), array('5')),
        );
    }

    /**
     * @dataProvider searchProvider
     */
    public function testIndexAndSearch($options, $expected)
    {
        // we'll save each model with a set of starting values then update it to use the
        // intended values and re-save. this forces re-indexing to occur further ensuring
        // its functioning correctly.
        $chaff = array(
            'type'    => 'type-a',
            'test'    => '1',
            'streams' => array('a', 'b', 'c', 'd'),
            'words'   => 'abc XYZ foo Bar'
        );
        $clear = array(
            'type'    => null,
            'test'    => null,
            'streams' => null,
            'words'   => null
        );

        $model = new KeyMock($this->p4);
        $model->set($chaff)->save()->set($clear);
        $model->set('test', '1')
              ->set('type', 'type-a')
              ->addStream('just-a')->addStream('a')
              ->save();

        $model = new KeyMock($this->p4);
        $model->set($chaff)->save()->set($clear);
        $model->set('test', '2')
              ->set('type', 'type-b')
              ->addStream('just-b')->addStream('a')->addStream('b')
              ->save();

        // note this one skips stream b on purpose
        $model = new KeyMock($this->p4);
        $model->set($chaff)->save()->set($clear);
        $model->set('test', '3')
              ->set('type', 'type-a')
              ->set('words', 'FOO Low')
              ->addStream('just-c')->addStream('a')->addStream('c')
              ->save();

        $model = new KeyMock($this->p4);
        $model->set($chaff)->save()->set($clear);
        $model->set('test', '4')
              ->set('type', 'type-b')
              ->addStream('just-d')->addStream('a')->addStream('b')->addStream('c')->addStream('d')
              ->save();

        $model = new KeyMock($this->p4);
        $model->set($chaff)->save()->set($clear);
        $model->set('test', '5')
              ->set('type', 'type-x')
              ->set('words', 'UPPERCASE, lowercase, MiXeD, bar1')
              ->addStream('just-e')->addStream('Bar2')
              ->save();

        $models = KeyMock::fetchAll($options, $this->p4);
        $values = $ids = array();
        foreach ($models as $model) {
            $ids[]    = $model->getId();
            $values[] = $model->get('test');
        }

        $this->assertSame(
            $expected,
            $values,
            'expected matching values and order'
        );

        $expected = array_map('intval', $expected);
        $this->assertSame(
            $expected,
            $ids,
            'expected ids to match'
        );

        $this->assertSame(
            $expected,
            $models->keys(),
            'expected indexes to match id'
        );
    }

    /**
     * Test the case where the same record is fetched/saved concurrently.
     * If different fields are modified, the result should be a merge.
     */
    public function testRaceCondition()
    {
        // make a record so we can have competing edits
        $r = new KeyMock($this->p4);
        $r->set(array('type' => 1, 'streams' => 1));
        $r->save();

        $r1 = KeyMock::fetch(1, $this->p4);
        $r2 = KeyMock::fetch(1, $this->p4);

        // both r1 and r2 edit the record, but in different fields
        $r1->set('streams', 2);
        $r1->save();
        $r2->set('type', 2);
        $r2->save();

        // the stored record should reflect both updates
        // r2 should also have both updates because it was saved after r1
        $r = KeyMock::fetch(1, $this->p4);
        $this->assertSame(2, $r->get('type'));
        $this->assertSame(2, $r->get('streams'));
        $this->assertSame(2, $r2->get('type'));
        $this->assertSame(2, $r2->get('streams'));
    }
}
