<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace AvatarTest\Controller;

use ModuleTest\TestControllerCase;
use Zend\Stdlib\Parameters;
use Users\Model\User;
use Projects\Model\Project;
use P4\Spec\Depot;
use P4\Spec\Client;

class IndexControllerTest extends TestControllerCase
{
    /**
     * Test to ensure image id is returned appropriately.
     */
    public function testValid()
    {
        // create project to test with
        $project = new Project($this->p4);
        $project->set(
            array(
                'id'      => 'prj',
                'avatar'  => 0,
                'splash'  => 1,
            )
        )->save();

        $this->dispatch('/project/prj/image/avatar');
        $this->assertRoute('projectImage');
        $this->assertRouteMatch('avatar', 'avatar\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(200);

        $this->resetApplication();
        $this->dispatch('/project/prj/image/splash');
        $this->assertRoute('projectImage');
        $this->assertRouteMatch('avatar', 'avatar\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test to ensure 404 is returned with no image
     */
    public function testValidProjectNoImage()
    {
        // create project to test with
        $project = new Project($this->p4);
        $project->set(
            array(
                'id'      => 'prj'
            )
        )->save();

        $this->dispatch('/project/prj/image/avatar');
        $this->assertRoute('projectImage');
        $this->assertRouteMatch('avatar', 'avatar\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(404);

        $this->resetApplication();
        $this->dispatch('/project/prj/image/splash');
        $this->assertRoute('projectImage');
        $this->assertRouteMatch('avatar', 'avatar\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(404);
    }

    /**
     * Ensure a 404 is returned if the project does not exist
     */
    public function testInvalidProject()
    {
        $this->dispatch('/project/prj/image/avatar');
        $this->assertRoute('projectImage');
        $this->assertRouteMatch('avatar', 'avatar\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(404);

        $this->resetApplication();
        $this->dispatch('/project/prj/image/splash');
        $this->assertRoute('projectImage');
        $this->assertRouteMatch('avatar', 'avatar\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(404);
    }
}
