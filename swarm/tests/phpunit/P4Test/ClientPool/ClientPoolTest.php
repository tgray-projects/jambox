<?php
/**
 * Tests for the client pool.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\ClientPool;

use P4\Spec\Depot;
use P4Test\TestCase;
use P4\Spec\Client;
use P4\ClientPool\ClientPool;

class ClientPoolTest extends TestCase
{
    public function testBasicFunction()
    {
        new ClientPool;
    }

    public function testBasicGet()
    {
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setRoot(DATA_PATH . '/clients');

        // get a client and verify re-requesting gives the same one
        $id1 = $clients->grab();
        $this->assertSame(
            $id1,
            $clients->grab(),
            'expected to get the same client on two requests'
        );
    }

    public function testViewCreation()
    {
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setRoot(DATA_PATH . '/clients');

        // get a client and verify re-requesting gives the same one
        $id1 = $clients->grab();
        $this->assertSame(
            array(
                 array(
                     'depot'  => '//depot/...',
                     'client' => '//' . $id1 . '/depot/...'
                 )
            ),
            Client::fetch($id1, $this->p4)->get('View'),
            'expected matching view'
        );
    }

    /**
     * @expectedException \P4\ClientPool\Exception
     */
    public function testNoFolderPermissions()
    {
        // ensure the clients folder exists and we lack write access to it
        $path = DATA_PATH . '/clients';
        mkdir($path);
        chmod($path, 0100);

        $this->assertSame(array(), glob($path . '/*'));

        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setRoot(DATA_PATH . '/clients');

        // expect the blocking grab to toss our expected exception
        $clients->grab();
    }

    /**
     * @expectedException \P4\ClientPool\Exception
     */
    public function testNoFilePermissions()
    {
        // ensure the clients folder exists and we lack write access to the lock files
        $path = DATA_PATH . '/clients';
        mkdir($path);
        touch($path . '/00000000-0000-0000-0000-000000000001' . ClientPool::LOCK_EXTENSION);
        chmod($path . '/00000000-0000-0000-0000-000000000001' . ClientPool::LOCK_EXTENSION, 0100);
        touch($path . '/00000000-0000-0000-0000-000000000002' . ClientPool::LOCK_EXTENSION);
        chmod($path . '/00000000-0000-0000-0000-000000000002' . ClientPool::LOCK_EXTENSION, 0100);
        touch($path . '/00000000-0000-0000-0000-000000000003' . ClientPool::LOCK_EXTENSION);
        chmod($path . '/00000000-0000-0000-0000-000000000003' . ClientPool::LOCK_EXTENSION, 0100);

        // test a single file to verify we indeed cannot open it
        $this->assertTrue(
            @fopen($path . '/00000000-0000-0000-0000-000000000001' . ClientPool::LOCK_EXTENSION, 'c') === false
        );

        // setup the client pool to test blocking and non-blocking grab
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setRoot(DATA_PATH . '/clients');

        // expect the blocking grab to toss our expected exception
        $clients->grab();
    }

    public function testGettingMax()
    {
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setRoot(DATA_PATH . '/clients');

        // get a client and verify re-requesting gives the same one
        $id1 = $clients->grab();
        $this->assertSame(
            $id1,
            $clients->grab(),
            'expected to get the same client on two requests'
        );

        // get another client by passing reuse=false
        $id2 = $clients->grab(false);
        $this->assertFalse(
            $id1 == $id2,
            'expected different id for client 2! ' . $id2
        );

        $id3 = $clients->grab(false);
        $this->assertFalse(
            $id1 == $id3 || $id2 == $id3,
            'expected different id for client 3! ' . $id3
        );

        $this->assertSame(
            false,
            $clients->grab(false, false),
            'Expected false on non blocking request for client 4'
        );

        $this->assertSame(
            $id1,
            $clients->grab(),
            'Expected first client to be returned when several available and re-using'
        );
    }

    public function testTwoCopies()
    {
        $clients1 = new ClientPool($this->p4);
        $clients1->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');

        $clients2 = new ClientPool($this->p4);
        $clients2->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');

        $id1 = $clients1->grab();
        $id2 = $clients2->grab();

        $this->assertTrue(
            strpos($id1, $clients1->getPrefix()) === 0,
            'expected valid looking id1'
        );
        $this->assertTrue(
            strpos($id2, $clients2->getPrefix()) === 0,
            'expected valid looking id2'
        );

        $this->assertTrue(
            $id1 != $id2,
            'expected two different ids'
        );
    }

    public function testRelease()
    {
        $clients1 = new ClientPool($this->p4);
        $clients1->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');

        $clients2 = new ClientPool($this->p4);
        $clients2->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');

        $id1 = $clients1->grab();
        $id2 = $clients2->grab();

        $this->assertTrue(
            strpos($id1, $clients1->getPrefix()) === 0,
            'expected valid looking id1'
        );
        $this->assertTrue(
            strpos($id2, $clients2->getPrefix()) === 0,
            'expected valid looking id2'
        );
        $this->assertTrue(
            $id1 != $id2,
            'expected two different ids'
        );

        $clients2->release();
        $this->assertSame(
            $id2,
            $clients1->grab(false),
            'expected id2 to be freed up for ClientPool1 to get'
        );
    }

    public function testSimpleHistory()
    {
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');

        $starting = $this->p4->getClient($this->p4);
        $clients->grab();
        $this->assertFalse($starting == $this->p4->getClient(), 'expected client to change');
        $this->assertTrue(strpos($this->p4->getClient(), 'test-') == 0, 'expected test prefix');

        $clients->release();
        $this->assertSame($starting, $this->p4->getClient(), 'expected original client to be restored');
    }

    public function testDeeperHistory()
    {
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');

        $starting = $this->p4->getClient($this->p4);
        $clients->grab();
        $this->assertFalse($starting == $this->p4->getClient($this->p4), 'expected client to change');
        $this->assertTrue(strpos($this->p4->getClient(), 'test-') == 0, 'expected test prefix');

        $grab1 = $this->p4->getClient();
        $clients->grab(false);
        $this->assertFalse($grab1 == $this->p4->getClient(), 'expected client to change2');
        $this->assertTrue(strpos($this->p4->getClient(), 'test-') == 0, 'expected test prefix2');

        $grab2 = $this->p4->getClient();
        $clients->grab(false);
        $this->assertFalse($grab2 == $this->p4->getClient(), 'expected client to change3');
        $this->assertTrue(strpos($this->p4->getClient(), 'test-') == 0, 'expected test prefix3');

        $clients->release();
        $this->assertSame($grab2, $this->p4->getClient(), 'expected original grab2 to be restored');

        $clients->release();
        $this->assertSame($grab1, $this->p4->getClient(), 'expected original grab1 to be restored');

        $clients->release();
        $this->assertSame($starting, $this->p4->getClient(), 'expected original to be restored');
    }

    public function testStreamReset()
    {
        // create a stream depot and stream //stream/main
        $depot = $this->p4->run('depot', array('-o', 'stream'))->getData(-1);
        $this->p4->run('depot', '-i', array('Type' => 'stream') + $depot);
        $stream = array('Type' => 'mainline') + $this->p4->run('stream', array('-o', '//stream/main'))->getData(-1);
        $this->p4->run('stream', '-i', $stream)->getData(-1);
        $this->p4->disconnect();    // required for 2010.2 p4d to pick up the new depot

        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');
        $id = $clients->grab();
        $clients->reset(true, '//stream/main');

        $this->assertSame(
            '//stream/main',
            Client::fetch($id, $this->p4)->get('Stream')
        );
    }

    /**
     * @expectedException \P4\Connection\Exception\CommandException
     */
    public function testBadDepotStreamReset()
    {
        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');
        $clients->grab();
        $clients->reset(true, '//madeup/madeup');
    }

    /**
     * @expectedException \P4\Connection\Exception\CommandException
     */
    public function testBadStreamReset()
    {
        $depot = $this->p4->run('depot', array('-o', 'stream'))->getData(-1);
        $this->p4->run('depot', '-i', array('Type' => 'stream') + $depot);

        $clients = new ClientPool($this->p4);
        $clients->setMax(3)->setPrefix('test-')->setRoot(DATA_PATH . '/clients');
        $clients->grab();
        $clients->reset(true, '//stream/madeup');
    }
}
