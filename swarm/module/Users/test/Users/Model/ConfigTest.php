<?php
/**
 * Tests for the user config model.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace UsersTest\Model;

use P4Test\TestCase;
use Projects\Model\Project;
use Record\Cache\Cache;
use Users\Model\Config;

class ConfigTest extends TestCase
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
                        'Projects' => BASE_PATH . '/module/Projects/src/Projects',
                        'Users'    => BASE_PATH . '/module/Users/src/Users'
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
        new Config($this->p4);
    }

    /**
     * Test basic save config
     */
    public function testSaveAndFetch()
    {
        $config = new Config($this->p4);
        $config->setId('test');
        $config->addFollow('jdoe');
        $config->set('test', 'value');
        $config->set('some', 'stuff');
        $config->save();

        $config = Config::fetch('test', $this->p4);
        $this->assertSame(
            array(
                'id'      => 'test',
                'follows' => array('user' => array('jdoe')),
                'test'    => 'value',
                'some'    => 'stuff'
            ),
            $config->get()
        );
    }

    /**
     * Test follow indexing
     */
    public function testFollowCountIndexing()
    {
        // setup cache
        $cache = new Cache($this->p4);
        $cache->setCacheDir(DATA_PATH . '/cache');
        $this->p4->setService('cache', $cache);

        $config = new Config($this->p4);
        $config->setId('t1')->addFollow('jdoe')->save();

        // make a couple of test projects
        $project = new Project($this->p4);
        $project->setId('woozle')->save();
        $project->setId('wobble')->save();

        // should now be one user with a follow count of one
        $counts = Config::fetchFollowerCounts(array(), $this->p4);
        $this->assertSame(
            array(
                'user:jdoe' => array(
                    'id'    => 'jdoe',
                    'type'  => 'user',
                    'count' => 1
                )
            ),
            $counts
        );

        $config = new Config($this->p4);
        $config->setId('t2')->addFollow('jdoe')->save();

        // should now be one user with a follow count of two
        $counts = Config::fetchFollowerCounts(array(), $this->p4);
        $this->assertSame(
            array(
                'user:jdoe' => array(
                    'id'    => 'jdoe',
                    'type'  => 'user',
                    'count' => 2
                )
            ),
            $counts
        );

        // unfollow
        $config = new Config($this->p4);
        $config->setId('t1')->removeFollow('jdoe')->save();

        // should now be one user with a follow count of one again
        $counts = Config::fetchFollowerCounts(array(), $this->p4);
        $this->assertSame(
            array(
                'user:jdoe' => array(
                    'id'    => 'jdoe',
                    'type'  => 'user',
                    'count' => 1
                )
            ),
            $counts
        );

        // follow a couple more things
        $config = new Config($this->p4);
        $config->setId('t3')
               ->addFollow('pat')
               ->addFollow('woozle', 'project')
               ->addFollow('wobble', 'project')
               ->save();
        $config = new Config($this->p4);
        $config->setId('t4')
            ->addFollow('pat')
            ->addFollow('woozle', 'project')
            ->save();

        $counts = Config::fetchFollowerCounts(array(), $this->p4);
        $this->assertSame(
            array(
                'project:wobble' => array(
                    'id'    => 'wobble',
                    'type'  => 'project',
                    'count' => 1
                ),
                'project:woozle' => array(
                    'id'    => 'woozle',
                    'type'  => 'project',
                    'count' => 2
                ),
                'user:jdoe' => array(
                    'id'    => 'jdoe',
                    'type'  => 'user',
                    'count' => 1
                ),
                'user:pat' => array(
                    'id'    => 'pat',
                    'type'  => 'user',
                    'count' => 2
                )
            ),
            $counts
        );

        // try fetching counts just for projects
        $counts = Config::fetchFollowerCounts(array(Config::COUNT_BY_TYPE => 'project'), $this->p4);
        $this->assertSame(
            array(
                'project:wobble' => array(
                    'id'    => 'wobble',
                    'type'  => 'project',
                    'count' => 1
                ),
                'project:woozle' => array(
                    'id'    => 'woozle',
                    'type'  => 'project',
                    'count' => 2
                ),
            ),
            $counts
        );

    }
}
