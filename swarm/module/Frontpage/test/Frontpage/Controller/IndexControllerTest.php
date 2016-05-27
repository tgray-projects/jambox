<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace FrontpageTest\Controller;

use Activity\Model\Activity;
use ModuleTest\TestControllerCase;
use P4\File\File;
use P4\Spec\Change;
use P4\Spec\Job;
use P4\Connection\Connection;
use Projects\Model\Project as Project;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;

use Zend\Stdlib\Parameters;

class IndexControllerTest extends TestControllerCase
{

    public function addTestData($projectCount, $active)
    {
        for ($i = 0; $i < $projectCount; $i++) {
            $projectNum = $i + 1;
            $projectData = array(
                'id' => 'TestProject' . $projectNum,
                'name' => 'Test Project' . $projectNum,
                'description' => 'Description of Test Project ' . $projectNum,
                'creator' => 'tester',
                'branches' => array(
                    array(
                        'id' => 'a' . $projectNum,
                        'name' => 'A' . $projectNum,
                        'paths' => '//depot/a' . $projectNum . '/...',
                        'moderators' => array()
                    ),
                ),
                'members' => array('foo', 'bar', 'xyz')
            );
            $project = new Project($this->p4);
            $project->set($projectData)->save();

            if ($i < $active) {
                // Create file, add and submit
                $file = new File;
                $file->setFilespec('//depot/a' . $projectNum .'/foo');
                $file->setLocalContents('bar');
                $file->add();
                $file->submit('test' . $projectNum);
            }
        }

    }

    public function processQueue()
    {
        $this->getRequest()->getQuery()->set('debug', 1)->set('retire', 1);
        $this->dispatch('/queue/worker');
        $this->resetApplication();
    }

    /**
     * Test the front page
     */
    public function testFrontPage()
    {
        $this->dispatch('/');
        $this->assertQueryContentContains('h4', 'Workshop Activity', 'Workshop Activity text is missing.');
        $this->assertQueryContentContains('h4', 'Recent Projects', 'Recent Projects text is missing.');
        $this->assertQueryContentContains('small', 'More >>', 'More link missing.');
    }

    /**
     * Test max active projects
     */
    public function testMaxActiveProjects()
    {
        // Add 3 active project, all 3 containing changes
        $this->addTestData(3, 3);
        $this->processQueue();
        $this->dispatch('/frontpage/projects-list/');

        $body   = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        $this->assertSame(3, count($projects));
        $this->assertSame(
            '<a href="/users/tester/">tester</a>'
            . ' / <a href="/projects/TestProject3/" class="project-name">Test Project3</a>',
            $projects[2]->name
        );
    }

    /**
     * Test no active projects
     */
    public function testNoActiveProjects()
    {
        // Add 5 projects, no changes
        $this->addTestData(5, 0);
        $this->processQueue();
        $this->dispatch('/frontpage/projects-list/');

        $body   = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        // No active projects, should return all 5 projects
        $this->assertSame(5, count($projects));
    }

    /**
     * Test less than max active projects
     */
    public function testLessThanMaxActiveProjects()
    {
        // Add 2 projects, 2 with changes
        $this->addTestData(2, 2);
        $this->processQueue();
        $this->dispatch('/frontpage/projects-list/');

        $body   = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        // There are less than max projects, all are shown (in this case 2 projects)
        $this->assertSame(2, count($projects));
    }

    /**
     * Test with deleted projects
     */
    public function testDeletedProjects()
    {
        // Add 2 projects, 2 with changes
        $this->addTestData(2, 2);
        $this->processQueue();

        // Delete one of the projects
        $services = $this->getApplication()->getServiceManager();
        $services->setService('p4_user', $services->get('p4_admin'));
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST);
        $this->dispatch('/projects/delete/TestProject1');
        $this->assertResponseStatusCode(200);

        $this->resetApplication();
        $this->dispatch('/frontpage/projects-list/');
        $body   = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        // There should be 1 project left
        $this->assertSame(1, count($projects));
    }

    /**
     * Test user projects
     */
    public function testUserProjects()
    {
        // Add projects
        $this->addTestData(5, 5);

        $projectData = array(
            'id'      => 'p4perl',
            'name'    => 'Perforce Perl API',
            'creator' => 'tester',
            'members' => array('foo', 'bar', 'xyz')
        );
        $project = new Project($this->p4);
        $project->set($projectData)->save();
        $this->processQueue();
        $this->dispatch('/frontpage/projects-list/user/user/tester');

        $body   = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        // Should only return the p4perl project
        $this->assertSame(1, count($projects));
        $this->assertSame(
            '<a href="/users/tester/">tester</a>'
            . ' / <a href="/projects/p4perl/" class="project-name">Perforce Perl API</a>',
            $projects[0]->name
        );
    }

    /*
     * Test invalid source, which will return max active projects
     */
    public function testInvalidSource()
    {
        $this->addTestData(5, 5);
        $this->processQueue();

        $this->dispatch('/frontpage/projects-list/invalid/');
        $body = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        $this->assertSame(3, count($projects));
        $this->assertResponseStatusCode(200);
    }

    /*
     * Test no source, which will return max active projects
     */
    public function testNoSource()
    {
        $this->addTestData(5, 5);
        $this->processQueue();

        $this->dispatch('/frontpage/projects-list/ /');
        $body = $this->getResponse()->getBody();
        $data   = Json::decode($body);
        $projects = $data->projectList;

        $this->assertSame(3, count($projects));
        $this->assertResponseStatusCode(200);
    }
}
