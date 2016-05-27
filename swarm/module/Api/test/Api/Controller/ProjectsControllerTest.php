<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ApiTest\Controller;

use ModuleTest\TestControllerCase;
use P4\Spec\User;
use Projects\Model\Project;
use Zend\Json\Json;
use Zend\Stdlib\Parameters;

class ProjectsControllerTest extends TestControllerCase
{
    public function testProjectList()
    {
        $this->markTestSkipped();

        // prepare some users for our project
        $user = new User;
        $user->setId('foo')->set('FullName', 'foo')->set('Email', 'test@host')->save();
        $user = new User;
        $user->setId('bar')->set('FullName', 'bar')->set('Email', 'test@host')->save();

        // grab the current projects list and confirm it is empty
        $this->dispatch('/api/v1/projects');
        $body     = $this->getResponse()->getBody();
        $actual   = Json::decode($body, true);
        $expected = array('projects' => array());

        $this->assertResponseStatusCode(200);
        $this->assertEquals($expected, $actual);
        $this->resetApplication();

        // create a project using the regular route (rather than posting to API, not yet implemented)
        $postData = new Parameters(
            array(
                'description' => 'My Project',
                'name'        => 'prj123',
                'members'     => array('bar', 'foo')
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/projects/add');
        $this->resetApplication();

        // fetch the projects list again (this time it shouldn't be empty)
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_GET);

        $this->dispatch('/api/v1/projects');

        $body     = $this->getResponse()->getBody();
        $actual   = Json::decode($body, true);
        $expected = array(
            'projects' => array(
                array(
                    'id'            => 'prj123',
                    'branches'      => array(),
                    'deleted'       => false,
                    'description'   => 'My Project',
                    'emailFlags'    => array(),
                    'followers'     => array(),
                    'jobview'       => '',
                    'members'       => array('bar', 'foo'),
                    'name'          => 'prj123',
                    'owners'        => array(),
                )
            )
        );

        $this->assertResponseStatusCode(200);
        $this->assertSame($expected, $actual);
    }

    public function testProjectsListAdvanced()
    {
        // prepare some users for our project
        $user = new User;
        $user->setId('foo')->set('FullName', 'foo')->set('Email', 'test@host')->save();
        $user = new User;
        $user->setId('bar')->set('FullName', 'bar')->set('Email', 'test@host')->save();

        // create project to test with
        $project = new Project($this->p4);
        $project->set(
            array(
                'id'          => 'prj123',
                'name'        => 'Project 123',
                'description' => 'My Project',
                'emailFlags'  => array(),
                'members'     => array('bar', 'foo'),
                'owners'      => array('foo'),
                'branches'    => array(
                    array(
                        'id'         => 'test',
                        'name'       => 'Test',
                        'paths'      => array('//depot/prj123/test/...'),
                        'moderators' => array('alice', 'bob'),
                    )
                )
            )
        )->save();

        // fetch the projects list again (this time it shouldn't be empty)
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_GET);

        $this->dispatch('/api/v1/projects');

        $body     = $this->getResponse()->getBody();
        $actual   = Json::decode($body, true);
        $expected = array(
            'projects' => array(
                array(
                    'id'            => 'prj123',
                    'branches'      => array(
                        array(
                            'id'         => 'test',
                            'name'       => 'Test',
                            'paths'      => array('//depot/prj123/test/...'),
                            'moderators' => array('alice', 'bob'),
                        )
                    ),
                    'deleted'       => false,
                    'description'   => 'My Project',
                    'emailFlags'    => array(),
                    'followers'     => array(),
                    'jobview'       => null,
                    'members'       => array('bar', 'foo'),
                    'name'          => 'Project 123',
                    'owners'        => array('foo'),
                )
            )
        );

        $this->assertResponseStatusCode(200);
        $this->assertSame($expected, $actual);
    }

    public function testProjectListLimitFields()
    {
        $this->markTestSkipped();

        // create a project using the regular route (rather than posting to API, not yet implemented)
        $postData = new Parameters(
            array(
                'description' => 'My Project',
                'name'        => 'prj123',
                'members'     => array('admin')
            )
        );
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        $this->dispatch('/projects/add');
        $this->resetApplication();

        // fetch the projects list again (this time it shouldn't be empty)
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_GET);

        $this->dispatch('/api/v1/projects?fields=id,description,name');

        $body     = $this->getResponse()->getBody();
        $actual   = Json::decode($body, true);
        $expected = array(
            'projects' => array(
                array(
                    'id'          => 'prj123',
                    'description' => 'My Project',
                    'name'        => 'prj123',
                )
            )
        );

        $this->assertResponseStatusCode(200);
        $this->assertSame($expected, $actual);
    }
}
