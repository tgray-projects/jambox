<?php
/**
 * Tests for the Record Cache.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace RecordTest\Cache;

use P4Test\TestCase;
use Record\Cache\ArrayWriter;

class ArrayWriterTest extends TestCase
{
    public function testCreateWriteClose()
    {
        $data = array(
            1,
            2,
            3,
            'foo' => 'bar',
            'bar' => array(1, 2, 3)
        );

        $writer = new ArrayWriter(DATA_PATH . '/test');
        $writer->createFile();
        foreach ($data as $key => $value) {
            $writer->writeElement($key, $value);
        }
        $writer->closeFile();

        $this->assertSame(
            $data,
            unserialize(file_get_contents(DATA_PATH . '/test'))
        );
    }

    public function testIndexing()
    {
        $data = array(
            1,
            2,
            3,
            'foo' => 'bar',
            'bar' => array(1, 2, 3)
        );

        $writer = new ArrayWriter(DATA_PATH . '/test', true);
        $writer->createFile();
        foreach ($data as $key => $value) {
            $writer->writeElement($key, $value);
        }
        $writer->closeFile();

        $this->assertSame(
            $data,
            unserialize(file_get_contents(DATA_PATH . '/test'))
        );

        // verify byte offsets are as expected (previously verified by hand)
        $this->assertSame(
            array(
                0     => array(14, 8),
                1     => array(22, 8),
                2     => array(30, 8),
                'foo' => array(38, 20),
                'bar' => array(58, 40)
            ),
            unserialize(file_get_contents(DATA_PATH . '/test.index'))
        );
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNonDestructiveCreateNoIndex()
    {
        file_put_contents(DATA_PATH . '/test', serialize(array(1, 2, 3)));
        $writer = new ArrayWriter(DATA_PATH . '/test');
        $writer->createFile();
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testNonDestructiveCreateWithIndex()
    {
        file_put_contents(DATA_PATH . '/test', serialize(array(1, 2, 3)));
        file_put_contents(DATA_PATH . '/test.index', serialize(array(1, 2, 3)));
        $writer = new ArrayWriter(DATA_PATH . '/test', true);
        $writer->createFile();
    }

    public function testSelfHealingCreate()
    {
        // write a corrupted array
        file_put_contents(DATA_PATH . '/test', substr(serialize(array(1, 2, 3)), 1));

        // writer should clobber a corrupt file
        $writer = new ArrayWriter(DATA_PATH . '/test', true);
        $writer->createFile();
        $writer->writeElement('foo', 'bar');
        $writer->closeFile();

        $this->assertSame(array('foo' => 'bar'), unserialize(file_get_contents(DATA_PATH . '/test')));
    }
}
