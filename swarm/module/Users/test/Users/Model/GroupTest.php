<?php
/**
 * Tests for the group config model.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace UsersTest\Model;

use P4\Log\Logger;
use P4Test\TestCase;
use Record\Cache\Cache;
use Users\Model\Group;
use Zend\Log\Logger as ZendLogger;
use Zend\Log\Writer\Mock as MockLog;

class GroupTest extends TestCase
{
    /**
     * Extend parent to additionally init modules we will use.
     */
    public function setUp()
    {
        parent::setUp();

        \Zend\Loader\AutoloaderFactory::factory(
            array(
                 'Zend\Loader\StandardAutoloader' => array(
                     'namespaces' => array(
                         'Users'  => BASE_PATH . '/module/Users/src/Users'
                     )
                 )
            )
        );
    }

    /**
     * Test model creation.
     */
    public function testBasicFunction()
    {
        new Group($this->p4);
    }

    /**
     * Test caching
     */
    public function testCache()
    {
        // make test groups
        $group = new Group($this->p4);
        $group->setId('test1')->setUsers(array('user1'))->save();
        $group->setId('test2')->setUsers(array('user2'))->addSubGroup('test1')->save();

        $cache = new Cache($this->p4);
        $cache->setCacheDir(DATA_PATH . '/cache');
        $this->p4->setService('cache', $cache);

        // calling either exists, fetch or fetchAll should prime the cache.
        $this->assertTrue($cache->getItem('groups') == null);
        $this->assertTrue(Group::exists('test1', $this->p4));
        $this->assertTrue($cache->getItem('groups') !== null);

        // array reader should work as well
        $file = $cache->getFile('groups');
        $reader = new \Record\Cache\ArrayReader($file);
        $reader->openFile();
        $this->assertTrue(count($reader) > 0);
        $reader->closeFile();

        // subsequent calls should run no commands.
        // verify this by peeking at the log
        $original = Logger::hasLogger() ? Logger::getLogger() : null;
        $logger   = new ZendLogger;
        $mock     = new MockLog;
        $logger->addWriter($mock);
        Logger::setLogger($logger);

        $this->assertTrue(Group::exists('test2', $this->p4));
        $this->assertSame(0, count($mock->events));

        // test fetching
        $group = Group::fetch('test1', $this->p4);
        $this->assertSame('test1', $group->getId());
        $this->assertSame($this->p4, $group->getConnection());

        // test various fetch all options
        $groups = Group::fetchAll(null, $this->p4);
        $this->assertSame(array('test1', 'test2'), $groups->invoke('getId'));

        $groups = Group::fetchAll(array(Group::FETCH_MAXIMUM => 1), $this->p4);
        $this->assertSame(array('test1'), $groups->invoke('getId'));

        $groups = Group::fetchAll(array(Group::FETCH_BY_USER => 'user1'), $this->p4);
        $this->assertSame(array('test1'), $groups->invoke('getId'));

        $groups = Group::fetchAll(array(Group::FETCH_BY_USER => 'user1', Group::FETCH_INDIRECT => true), $this->p4);
        $this->assertSame(array('test1', 'test2'), $groups->invoke('getId'));

        $groups = Group::fetchAll(array(Group::FETCH_BY_NAME => 'test1'), $this->p4);
        $this->assertSame(array('test1'), $groups->invoke('getId'));

        $members = Group::fetchMembers('test2', array(Group::FETCH_INDIRECT => true), $this->p4);
        $this->assertSame(array('user2', 'user1'), $members);

        $members = Group::fetchMembers('test2', array(), $this->p4);
        $this->assertSame(array('user2'), $members);

        $this->assertSame(0, count($mock->events));

        // restore original logger if there is one.
        Logger::setLogger($original);
    }

    public function testRecursiveSubGroups()
    {
        // make test groups
        $group = new Group($this->p4);
        $group->setId('test1')->setUsers(array('user1'))->addSubGroup('test2')->save();
        $group->setId('test2')->setUsers(array('user2'))->addSubGroup('test1')->save();

        // verify member fetching works without indirect (so should be safe regardless)
        $this->assertSame(
            array('user1'),
            Group::fetchMembers('test1', array(), $this->p4)
        );

        // and with indirect (so endless looping is a risk)
        $this->assertSame(
            array('user1', 'user2'),
            Group::fetchMembers('test1', array(Group::FETCH_INDIRECT => true), $this->p4)
        );
    }
}
