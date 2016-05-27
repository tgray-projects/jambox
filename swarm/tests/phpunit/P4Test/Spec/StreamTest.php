<?php
/**
 * Test methods for the P4 Stream class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Stream;
use P4\Spec\Depot;

class StreamTest extends TestCase
{
    /**
     * Test a fresh in-memory Stream object.
     */
    public function testFreshObject()
    {
        $client = new Stream;
        $this->assertSame(
            null,
            $client->getUpdateDateTime(),
            'Expected update datetime'
        );
        $this->assertSame(
            null,
            $client->getAccessDateTime(),
            'Expected access datetime'
        );
    }

    /**
     * Test the string based properties both via (get|set)Value and accessor/mutator
     * Owner/Name/Parent/Type/Description
     */
    public function testAccessorsMutators()
    {
        $tests  = array(
            'Owner'         => 'theOwner',
            'Name'          => 'My Stream Name!',
            'Parent'        => '//depot/test-other',
            'Type'          => 'mainline',
            'Description'   => 'zdesc'
        );

        // test by setting via 'set'
        $stream = new Stream;
        foreach ($tests as $key => $value) {
            $stream->set($key, $value);
            $this->assertSame($value, $stream->get($key), "Using 'get' after a 'set' for $key");
            $this->assertSame($value, $stream->{'get'.$key}(), "Using accessor after a 'set'  $key");
        }

        // test by setting via mutator
        $stream = new Stream;
        foreach ($tests as $key => $value) {
            $stream->{'set'.$key}($value);
            $this->assertSame($value, $stream->get($key), "Using 'get' after mutator for $key");
            $this->assertSame($value, $stream->{'get'.$key}(), "Using accessor after mutator  $key");
        }
    }

    /**
     * test the options accessor/mutator
     */
    public function testOptions()
    {
        $stream = new Stream;
        $this->assertSame(array(), $stream->getOptions(), 'starting value');

        $stream->setOptions('foo bar');
        $this->assertSame(array('foo', 'bar'), $stream->getOptions(), 'set via string');

        $stream->setOptions(array('biz', 'bang'));
        $this->assertSame(array('biz', 'bang'), $stream->getOptions(), 'set via array');
    }

    /**
     * test the paths accessor/mutator
     */
    public function testPaths()
    {
        $stream = new Stream;
        $this->assertSame(array(), $stream->getPaths(), 'starting value');

        $stream->setPaths('foo bar');
        $this->assertSame(
            array(array('type' => 'foo', 'view' => 'bar', 'depot' => null)),
            $stream->getPaths(),
            'set via string'
        );

        $stream->setPaths(array('biz bang', 'bang boom baz'));
        $this->assertSame(
            array(
                array('type' => 'biz',  'view' => 'bang', 'depot' => null),
                array('type' => 'bang', 'view' => 'boom', 'depot' => 'baz')
            ),
            $stream->getPaths(),
            'set via array'
        );

        $stream->addPath('test', 'add');
        $this->assertSame(
            array(
                array('type' => 'biz',  'view' => 'bang', 'depot' => null),
                array('type' => 'bang', 'view' => 'boom', 'depot' => 'baz'),
                array('type' => 'test', 'view' => 'add',  'depot' => null)
            ),
            $stream->getPaths(),
            'set via array'
        );
    }

    /**
     * Verify setting an invalid value throws
     */
    public function testInvalidMutator()
    {
        $fields = array('Owner', 'Name', 'Parent', 'Type', 'Description', 'Options', 'Paths');

        $stream = new Stream;
        foreach ($fields as $field) {
            try {
                $stream->{'set'.$field}(12);
            } catch (\InvalidArgumentException $e) {
                $this->assertTrue(true, 'Caught expected exception for ' . $field);
            }
        }
    }

    /**
     * Verify exists works
     */
    public function testExists()
    {
        $this->assertFalse(Stream::exists('//streams/test'), 'pre add');

        $this->addTestDepot();
        $this->addTestStream();
        $this->assertTrue(Stream::exists('//streams/test'), 'post add');
    }

    /**
     * Test fetch all
     */
    public function testFetchAll()
    {
        $this->addTestDepot();
        $this->addTestStream('//streams/diff1', array('Owner' => 'diff'));
        $this->addTestStream('//streams/test1');
        $this->addTestStream('//streams/test2');

        $this->assertSame(
            array('//streams/diff1', '//streams/test1', '//streams/test2'),
            Stream::fetchAll()->invoke('getId'),
            'fetch all no options'
        );

        $this->assertSame(
            array('//streams/diff1'),
            Stream::fetchAll(array(Stream::FETCH_BY_FILTER => 'Owner=diff'))->invoke('getId'),
            'fetch all with owner filter'
        );

        $this->assertSame(
            array('//streams/test1', '//streams/test2'),
            Stream::fetchAll(array(Stream::FETCH_BY_PATH => '//streams/te*'))->invoke('getId'),
            'fetch all with path filter'
        );

        $this->assertSame(
            array(),
            Stream::fetchAll(array(Stream::FETCH_BY_PATH => '//p4-*/*'))->invoke('getId'),
            'fetch all with unmet path filter'
        );
    }

    /**
     * Creates a stream depot
     *
     * @return  Depot   the depot we created id will be streams
     */
    protected function addTestDepot()
    {
        $depot = new Depot;
        $depot->setId('streams')
              ->setType('stream')
              ->setMap('streams/...')
              ->save();

        // we need to disconnect when using p4-php so the new depot
        // will show up properly. this is a p4d issue not p4-php.
        $this->p4->disconnect();

        return $depot;
    }

    /**
     * Adds a stream
     *
     * @param   string  $id         optional - ID to use, defaults to //streams/test
     * @param   array   $values     optional - over-ride any of the value defaults
     * @return  Stream              the stream that was created
     */
    protected function addTestStream($id = '//streams/test', $values = array())
    {
        $stream = new Stream;
        $stream->setId($id)
               ->setName('test')
               ->setParent('none')
               ->setType('mainline')
               ->setOwner('tester')
               ->setPaths('share ...')
               ->set($values)
               ->save();

        return $stream;
    }
}
