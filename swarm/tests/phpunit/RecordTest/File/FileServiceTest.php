<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Record\File;

use P4Test\TestCase;
use P4\Spec\Protections as P4Protections;

class FileServiceTest extends TestCase
{
    public $storage;

    public function setUp()
    {
        parent::setUp();
        $this->storage = new FileService($this->p4);
        $this->storage->setConfig(array('base_path' => '//depot/swarm_storage'));
    }

    public function testAbsolutize()
    {
        $this->assertEquals($this->storage->absolutize('test.txt'), '//depot/swarm_storage/test.txt');

        $this->storage->setConfig(array('base_path' => '//.swarm-test'));
        $this->assertEquals($this->storage->absolutize('test.txt'), '//.swarm-test/test.txt');

        $this->assertEquals(
            $this->storage->absolutize('//my_depot/my_path/my_file.txt'),
            '//my_depot/my_path/my_file.txt'
        );

        $this->storage->setConfig(array('base_path' => '//.swarm-test/'));
        $this->assertEquals($this->storage->absolutize('test.txt'), '//.swarm-test/test.txt');

        $this->assertEquals(
            $this->storage->absolutize('//my_depot/my_path/my_file.txt'),
            '//my_depot/my_path/my_file.txt'
        );
    }

    public function testWriteAndReadAndDelete()
    {
        $this->storage->write('test.txt', 'testing');

        $this->assertEquals($this->storage->read('test.txt'), 'testing');

        $this->storage->delete('test.txt');

        $this->setExpectedException(
            'P4\File\Exception\NotFoundException',
            'Cannot fetch file \'' . $this->storage->absolutize('test.txt') . '\'. File does not exist.'
        );

        $this->storage->read('test.txt');
    }

    public function testWriteFromFile()
    {
        $tmp_file = DATA_PATH . '/TMP_FILE.txt';
        $contents = 'Testing 123';

        // we don't have to clean this up at the end because we test "move" last
        file_put_contents($tmp_file, $contents);

        // test writeFromFile in COPY mode
        $this->storage->writeFromFile('file_test.txt', $tmp_file);
        $this->assertTrue(file_exists($tmp_file), 'Temporary file should have been copied, not moved/deleted');

        $this->assertTrue(
            $this->storage->read('file_test.txt') === $contents,
            'Failed to retrieve data written from file'
        );

        $this->storage->delete('file_test.txt');

        // test writeFromFile in MOVE mode
        $this->storage->writeFromFile('file_test2.txt', $tmp_file, true);
        $this->assertFalse(file_exists($tmp_file), 'Temporary file should have been moved/deleted');

        $this->assertTrue(
            $this->storage->read('file_test2.txt') === $contents,
            'Failed to retrieve data written from file'
        );

        $this->storage->delete('file_test2.txt');
    }

    public function testIsWritable()
    {
        // test that super can write to the storage location
        $this->assertTrue($this->storage->isWritable('attachments'));

        // test that writes to non-existent depots are rejected
        $this->assertFalse($this->storage->isWritable('//no_such_depot/swarm'));

        // test how empty protections affect things
        $protections = P4Protections::fetch($this->p4);
        $protections->setProtections(array())->save();

        $userP4 = \P4\Connection\Connection::factory(
            $this->getP4Params('port'),
            'nonadmin',
            null,
            ''
        );
        // actually create the user
        $userForm = array(
            'User'     => 'nonadmin',
            'Email'    => 'nonadmin@testhost',
            'FullName' => 'Test User',
            'Password' => '',
        );
        $userP4->run('user', '-i', $userForm);

        $storage = new FileService($userP4);
        $storage->setConfig(array('base_path' => '//depot/swarm_storage'));

        // test the empty-protections-table functionality

        // should return false for non-existent depots
        $this->assertFalse($storage->isWritable('//no_such_depot/swarm'));

        // should return true for valid depots
        $this->assertTrue($storage->isWritable('attachments'));
    }
}
