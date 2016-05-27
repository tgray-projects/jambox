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

/**
 * This test replaces the skipped tests from the Api module that do not work with the Workshop's changes.
 */
class ApiProjectsControllerTest extends TestControllerCase
{
    public function testProjectList()
    {

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
                    'id' => 'nonadmin-prj123',
                    'avatar' => null,
                    'branches' => array (),
                    'creator' => 'nonadmin',
                    'deleted' => false,
                    'description' => 'My Project',
                    'emailFlags'    => array(),
                    'followers' => array (),
                    'jobview' => 'project=nonadmin-prj123',
                    'members' => array ('bar', 'foo'),
                    'name' => 'prj123',
                    'owners' => array (),
                    'splash' => null
                )
            )
        );

        $this->assertResponseStatusCode(200);
        $this->assertSame($expected, $actual);
    }

    public function testProjectListLimitFields()
    {
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
                    'id'          => 'nonadmin-prj123',
                    'description' => 'My Project',
                    'name'        => 'prj123',
                )
            )
        );

        $this->assertResponseStatusCode(200);
        $this->assertSame($expected, $actual);
    }
}
