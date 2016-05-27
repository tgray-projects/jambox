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
use Record\Cache\ArrayReader;
use Record\Cache\ArrayWriter;

class ArrayReaderTest extends TestCase
{
    public function testCreateRead()
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

        // attempt reading serialized array via reader
        $reader = new ArrayReader(DATA_PATH . '/test');
        $reader->openFile();
        $this->assertSame($data, iterator_to_array($reader));

        // test out array access
        $this->assertTrue(isset($reader['foo'], $reader['bar']));
        $this->assertFalse(isset($reader['foobar']));
        $this->assertSame(1, $reader[0]);
        $this->assertSame('bar', $reader['foo']);
        $this->assertSame(array(1, 2, 3), $reader['bar']);
        $this->assertSame(2, $reader['bar'][1]);

        $reader->closeFile();
    }

    public function testNoCaseLookup()
    {
        $data = array(
            'foO' => 1,
            'Foo' => 2,
            'BAR' => 3,
            2     => 4
        );

        $writer = new ArrayWriter(DATA_PATH . '/test', true);
        $writer->createFile();
        foreach ($data as $key => $value) {
            $writer->writeElement($key, $value);
        }
        $writer->closeFile();

        // attempt reading serialized array via reader
        $reader = new ArrayReader(DATA_PATH . '/test');
        $reader->openFile();
        $this->assertSame($data, iterator_to_array($reader));

        // do some case-insensitive lookups
        $this->assertSame('foO', $reader->noCaseLookup('foo'));
        $this->assertSame('foO', $reader->noCaseLookup('Foo'));
        $this->assertSame('BAR', $reader->noCaseLookup('bar'));
        $this->assertSame(false, $reader->noCaseLookup('woozle'));
        $this->assertSame(2,     $reader->noCaseLookup(2));
        $this->assertSame(false, $reader->noCaseLookup(1));

        $reader->closeFile();
    }
}
