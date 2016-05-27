<?php
/**
 * Tests for the job filter
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace WorkshopTest\Filter;

use P4Test\TestCase;
use \Jobs\Filter\Job as Filter;
use \Projects\Model\Project;
use P4\Spec\User;

class JobFilterTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        \Zend\Loader\AutoloaderFactory::factory(
            array(
                'Zend\Loader\StandardAutoloader' => array(
                    'namespaces' => array(
                        'Workshop'      => BASE_PATH . '/module/Workshop/src/Workshop',
                        'Projects'      => BASE_PATH . '/module/Projects/src/Projects',
                        'Users'         => BASE_PATH . '/module/Users/src/Users',
                        'Application'   => BASE_PATH . '/module/Application/src/Application',
                    )
                )
            )
        );
    }

    public function testBasicFunction()
    {
        $filter = new Filter($this->p4);
    }

    public function testFilterMode()
    {
        $filter = new Filter($this->p4);

        $filter->setMode(Filter::MODE_ADD);
        $this->assertSame($filter->getMode(), Filter::MODE_ADD);

        $filter->setMode(Filter::MODE_EDIT);
        $this->assertSame($filter->getMode(), Filter::MODE_EDIT);
    }

    public function testFilterAddValid()
    {
        $this->addProject('testprj');

        $filter = new Filter($this->p4);
        $filter->setMode(Filter::MODE_ADD);

        $definition = array(
            'job'           => 'new',
            'status'        => 'open',
            'severity'      => 'A',
            'ownedBy'       => 'foo',
            'project'       => 'testprj',
            'description'   => 'test',
            'devNotes'      => '',
            'type'          => 'Bug',
        );

        $filter->setData($definition);

        $valid = $filter->isValid();
        $messages = $filter->getMessages();
        $this->assertTrue($valid, $messages);

        $values = $filter->getValues();

        $this->assertSame(
            $definition,
            $values
        );

        $this->assertTrue($filter->isValid());
    }

    public function testFilterAddInvalid()
    {
        $this->addProject('testprj');

        $filter = new Filter($this->p4);
        $filter->setMode(Filter::MODE_ADD);

        $definition = array(
            'severity'      => 3,
            'type'          => 'Foo',
            'status'        => 'borked',
            'description'   => 'test',
            'job'           => 'new',
            'project'       => 'invalid'
        );
        $filter->setData($definition);

        $this->assertFalse($filter->isValid());
    }

    public function addProject($id)
    {
        foreach (array('foo', 'bar', 'baz') as $id) {
            $user = new User($this->p4);
            $user->setId($id)->set('FullName', $id)->set('Email', $id . '@localhost.fail')->save();
        }

        // add a project
        $projectData = array(
            'id'      => $id,
            'name'    => $id,
            'members' => array('foo', 'bar', 'xyz')
        );
        $project = new Project($this->p4);
        $project->set($projectData);
        $project->save();
        return $project;
    }
}
