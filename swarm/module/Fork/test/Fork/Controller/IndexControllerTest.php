<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ForkTest\Controller;

use ModuleTest\TestControllerCase;
use Projects\Model\Project as Project;
use P4\File\File;
use P4\Spec\Depot;
use P4\Spec\Protections;

class IndexControllerTest extends TestControllerCase
{
    public function setUp()
    {
        parent::setUp();

        // ensure there's a guest depot
        if (!Depot::exists('guest')) {
            $guest = new Depot($this->superP4);
            $guest->setId('guest')->setType('local')->setMap(DATA_PATH . '/guest/...')->save();
            $this->p4->disconnect()->connect();

            $this->p4->getService('clients')->grab();

            $protections = Protections::fetch($this->superP4);
            $protections->addProtection(
                'read',
                'user',
                '*',
                '*',
                '//guest/...'
            );
            $protections->save();
        }
    }

    public function testForkActionInvalidProject()
    {
        $this->dispatch('/projects/prjtest/fork');

        $this->assertRoute('goto');
        $this->assertRouteMatch('application', 'application\controller\indexcontroller', 'goto');
        $this->assertResponseStatusCode(404);
    }

    public function testForkActionEmptyBranch()
    {
        // add a project
        $projectData = array(
            'id'      => 'prjtest',
            'name'    => 'prjtest',
            'members' => array('foo', 'bar', 'xyz')
        );
        $project = new Project($this->p4);
        $project->set($projectData)->save();

        $this->dispatch('/projects/prjtest/fork');

        $this->assertRoute('goto');
        $this->assertRouteMatch('application', 'application\controller\indexcontroller', 'goto');
        $this->assertResponseStatusCode(404);
    }

    public function testForkActionValid()
    {
        // done to reset the client
        $this->p4->disconnect()->connect();

        // add a file
        $file = new File($this->p4);
        $file->setFilespec('//guest/foo/prjtest/main/testfile');
        $file->open();
        $file->setLocalContents('xyz123');
        $file->submit('add file to project');

        // add a project
        $projectData = array(
            'id'       => 'foo-prjtest',
            'name'     => 'prjtest',
            'members'  => array('foo', 'bar', 'xyz'),
            'creator'  => 'foo',
            'branches' => array(
                array(
                    'id' => 'main',
                    'name' => 'Main',
                    'paths' => array(
                        '//guest/foo/prjtest/main/...'
                    )
                )
            )
        );
        $project = new Project($this->p4);
        $project->set($projectData)->save();

        $protections = Protections::fetch($this->superP4);
        $protections->addProtection(
            'write',
            'group',
            'swarm-project-' . $projectData['id'],
            '*',
            $projectData['branches'][0]['paths'][0]
        );
        $protections->addProtection(
            'write',
            'user',
            'nonadmin',
            '*',
            '//guest/nonadmin/...'
        );
        $protections->save();

        $this->p4->disconnect()->connect();
        $this->userP4->disconnect()->connect();

        $this->dispatch('/projects/foo-prjtest/fork/main');

        $this->assertRoute('forkProject');
        $this->assertRouteMatch('fork', 'fork\controller\indexcontroller', 'forking');
        $this->assertResponseStatusCode(200);

        $result = json_decode($this->getResponse()->getBody(), true);
        $this->assertTrue(array_key_exists('id', $result));
        $this->assertTrue(Project::exists($result['id'], $this->p4));

        $forked = Project::fetch($result['id'], $this->p4);
        $expected = array(
            array(
                'id' => 'main',
                'name' => 'Main',
                'moderators' => array(),
                'paths' => array(
                    '//guest/nonadmin/prjtest/main/...'
                )
            )
        );
        $actual = $forked->getBranches();
        $this->assertEquals($expected, $actual);

        // verify job spec contains the new project
        $spec = \P4\Spec\Definition::fetch('job', $this->superP4);
        $this->assertTrue($spec->hasField('Project'));

        $projectField = $spec->getField('Project');
        $this->assertTrue((in_array($result['id'], $projectField['options'])));
    }

    public function testParentAction()
    {
        $parentData = array(
            'id'       => 'foo-prjtest',
            'name'     => 'prjtest',
            'members'  => array('foo'),
            'creator'  => 'foo',
        );
        $parent = new Project($this->p4);
        $parent->set($parentData)->save();
        $parent = Project::fetch($parentData['id'], $this->p4);

        $childData = array(
            'id'       => 'bar-prjtest',
            'name'     => 'prjtest2',
            'members'  => array('bar'),
            'creator'  => 'bar',
            'parent'   => 'foo-prjtest'
        );
        $child = new Project($this->p4);
        $child->set($childData)->save();
        $child = Project::fetch($childData['id'], $this->p4);

        $this->dispatch('/projects/'  . $childData['id'] .'/parent');

        $this->assertRoute('parentProject');
        $this->assertRouteMatch('fork', 'fork\controller\indexcontroller', 'parent');
        $this->assertResponseStatusCode(200);

        $result = json_decode($this->getResponse()->getBody(), true);

        $this->assertEquals($result['parentId'], $parent->getId());
        $this->assertEquals($result['parentName'], $parent->getName());

        $this->resetApplication();

        $this->dispatch('/projects/'  . $parentData['id'] .'/parent');

        $result = json_decode($this->getResponse()->getBody(), true);

        $this->assertEquals($result['parentId'], null);
        $this->assertEquals($result['parentName'], null);
    }
}
