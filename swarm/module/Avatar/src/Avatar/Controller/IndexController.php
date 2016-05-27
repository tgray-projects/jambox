<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Avatar\Controller;

use Projects\Model\Project;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

/**
 * Class IndexController
 * @package Avatar\Controller
 *
 */
class IndexController extends AbstractActionController
{
    public function projectAction()
    {
        $services   = $this->getServiceLocator();
        $p4Admin    = $services->get('p4_admin');
        $projectId  = $this->getEvent()->getRouteMatch()->getParam('project');
        $type       = $this->getEvent()->getRouteMatch()->getParam('type');

        if (!Project::exists($projectId, $p4Admin)) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        try {
            $project = Project::fetch($projectId, $p4Admin);
        } catch (Exception $e) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        // return 404 for old, stored values - only use attachments from now on
        $attachmentId = $project->get($type);
        if (!is_numeric($attachmentId)) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        return new JsonModel(
            array(
                'id' => $attachmentId
            )
        );
    }
}
