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
use Record\Cache\Cache;
use Record\Cache\Exception;

class Test extends TestCase
{
    public function testBasicFunction()
    {
        $cache = new Cache($this->p4);
    }

    public function testGetSetCacheDir()
    {
        $cache = new Cache($this->p4);

        try {
            $cache->getCacheDir();
            $this->fail();
        } catch (Exception $e) {
            $this->assertTrue(true);
        }

        $cache->setCacheDir(DATA_PATH . '/cache/');
        $this->assertSame(DATA_PATH . '/cache', $cache->getCacheDir());
    }

    public function testGetSetItem()
    {
        $cache = new Cache($this->p4);
        $cache->setCacheDir(DATA_PATH . '/cache');

        // verify no such item returns null and success = false
        $success = null;
        $result  = $cache->getItem('foo', $success);
        $this->assertFalse($success);
        $this->assertNull($result);

        // put something in, verify it comes out again
        $foo = array(1, 2, 3);
        $cache->setItem('foo', $foo);
        $result = $cache->getItem('foo', $success);
        $this->assertTrue($success);
        $this->assertSame($foo, $result);
    }

    public function testInMemoryCaching()
    {
        $cache = new Cache($this->p4);
        $cache->setCacheDir(DATA_PATH . '/cache');

        // cached objects should be held in memory for speed.
        // evidenced by set/get round-tripping identical object
        $object = new \stdClass;
        $cache->setItem('test', $object);
        $this->assertSame($object, $cache->getItem('test'));

        // also evidenced by get/get returning identical object
        $cache->reset();
        $this->assertSame($cache->getItem('test'), $cache->getItem('test'));
    }

    public function testInvalidation()
    {
        $cache = new Cache($this->p4);
        $cache->setCacheDir(DATA_PATH . '/cache');

        // try basic setting/getting
        $cache->setItem('test', 'value');
        $this->assertSame('value', $cache->getItem('test'));

        // now invalidate, should be gone.
        $cache->invalidateItem('test');
        $this->assertNull($cache->getItem('test'));

        // should be gone for a new instance as well
        // this tests that on-disk lookups are affected
        $alt = new Cache($this->p4);
        $alt->setCacheDir(DATA_PATH . '/cache');
        $this->assertNull($cache->getItem('test'));

        // should still be cacheable
        $cache->setItem('test', 'newvalue');
        $this->assertSame('newvalue', $cache->getItem('test'));
    }

    public function testFileRemoval()
    {
        $cache = new Cache($this->p4);
        $cache->setCacheDir(DATA_PATH . '/cache');

        $cache->setItem('test1', 'value');
        $cache->setItem('test2', 'value');

        // write some dummy files with the same names, but different extensions
        touch($cache->getFile('test1') . '.foo');
        touch($cache->getFile('test2') . '.bar');

        // verify cache files are there on disk
        $file1 = $cache->getFile('test1');
        $file2 = $cache->getFile('test2');
        $this->assertTrue(file_exists($file1));
        $this->assertTrue(file_exists($file2));
        $this->assertTrue(file_exists($file1 . '.foo'));
        $this->assertTrue(file_exists($file2 . '.bar'));

        // invalidating items doesn't immediately remove cache files
        $cache->invalidateItem('test1')->setItem('test1', 'new-value');
        $cache->invalidateItem('test2')->setItem('test2', 'new-value');
        $this->assertTrue(file_exists($file1));
        $this->assertTrue(file_exists($file2));

        // explicitly removing them should
        $cache->removeInvalidatedFiles();
        $this->assertFalse(file_exists($file1));
        $this->assertFalse(file_exists($file2));
        $this->assertFalse(file_exists($file1 . '.foo'));
        $this->assertFalse(file_exists($file2 . '.bar'));

        // current items should remain
        $file1 = $cache->getFile('test1');
        $file2 = $cache->getFile('test2');
        $this->assertTrue(file_exists($file1));
        $this->assertTrue(file_exists($file2));
    }
}
