<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage\View\Helper;

use Projects\Model\Project as ProjectModel;
use Zend\View\Helper\AbstractHelper;

class ProjectLink extends AbstractHelper
{
    /**
     * Outputs the creatorId / project name and linkifies it if the project and user exists
     *
     * @param   string  $projectId   the id of the project to output and, if able, link to
     * @param   bool    $eparate     optional; if set, creator id is not broken out as a separate user link
     */
    public function __invoke($projectId, $joinCreator = false)
    {
        $view     = $this->getView();
        $services = $view->getHelperPluginManager()->getServiceLocator();
        $p4Admin  = $services->get('p4_admin');

        if (!ProjectModel::exists($projectId, $p4Admin)) {
            return $projectId;
        }

        $project = ProjectModel::fetch($projectId, $p4Admin);

        $label = '';
        if ($project->hasField('creator')) {
            $label = $view->UserLink($project->get('creator')) . ' / ';
        }

        $label .= '<a href="'
            . $view->url('project', array('project' => $projectId))
            . '" class="project-name">'
            . $view->escapeHtml($project->getName())
            . '</a>';


        return $label;
    }
}
