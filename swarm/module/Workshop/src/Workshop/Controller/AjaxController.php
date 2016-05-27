<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Workshop\Controller;

use Projects\Model\Project;
use Record\Exception\NotFoundException;
use Zend\View\Model\JsonModel;
use Zend\Mvc\Controller\AbstractActionController;

class AjaxController extends AbstractActionController
{
    public function projectAction()
    {
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        $services    = $this->getServiceLocator();
        $config      = $services->get('config');
        $mainlines   = isset($config['projects']['mainlines']) ? (array) $config['projects']['mainlines'] : array();
        $branches    = $project->getBranches('name', $mainlines);

        return new JsonModel(
            array(
                'branches'  => $branches,
                'id'        => $project->getId()
            )
        );
    }

    /**
     * Helper method to return model of requested project or false if project
     * id is missing or invalid.
     *
     * @return  Project|false   project model or false if project id is missing or invalid
     */
    protected function getRequestedProject()
    {
        $id      = $this->getEvent()->getRouteMatch()->getParam('project');
        $p4Admin = $this->getServiceLocator()->get('p4_admin');

        // attempt to retrieve the specified project
        // translate invalid/missing id's into a 404
        try {
            return Project::fetch($id, $p4Admin);
        } catch (NotFoundException $e) {
        } catch (\InvalidArgumentException $e) {
        }

        $this->getResponse()->setStatusCode(404);
        return false;
    }
}
