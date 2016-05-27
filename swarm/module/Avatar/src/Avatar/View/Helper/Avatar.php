<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Avatar\View\Helper;

use Projects\Model\Project as ProjectModel;
use Zend\View\Helper\AbstractHelper;

class Avatar extends AbstractHelper
{
    const   DEFAULT_COUNT = 6;

    /**
     * Renders a image tag and optional link for the given project's avatar.
     *
     * @param   string|ProjectModel|null    $user   a user id or user object (null for anonymous)
     * @param   string|int                  $size   the size of the avatar (e.g. 64, 128)
     * @param   bool                        $link   optional - link to the user (default=true)
     * @param   bool                        $class  optional - class to add to the image
     * @param   bool                        $fluid  optional - match avatar size to the container
     */
    public function __invoke($project = null, $size = null, $link = true, $class = null, $fluid = true)
    {
        $view     = $this->getView();
        $services = $view->getHelperPluginManager()->getServiceLocator();


        if (!$project instanceof ProjectModel) {
            $p4Admin = $services->get('p4_admin');
            if ($project && ProjectModel::exists($project, $p4Admin)) {
                $project = ProjectModel::fetch($project, $p4Admin);
                $user    = UserModel::fetch($project->get('creator'), $p4Admin);
            } else {
                $user = null;
                $link = false;
            }
        }

        $id          = $project instanceof ProjectModel ? $project->getId()   : $user;
        $name        = $project instanceof ProjectModel ? $project->getName() : null;
        $size     = (int) $size ?: '64';

        // pick a default image and color for this user
        // we do this by summing the ascii values of all characters in their id
        // then we modulo divide by 6 to get a remainder in the range of 0-5.
        if ($id) {
            $i      = (array_sum(array_map('ord', str_split($id))) % static::DEFAULT_COUNT) + 1;
            $class .= ' ai-' . $i;
            $class .= ' ac-' . $i;
            $class .= ' as-' . $size;
        }

        // determine the url to use for this project's avatar based on the configured pattern
        // if no pattern is configured, fallback to a blank gif via data uri
        $avatar = $project->get('avatar');
        $url    = ($avatar !== null && is_numeric($avatar))
            ? $view->url('attachments', array('attachment' => $avatar))
            : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        // build the actual img tag we'll be using
        $fluid    = $fluid ? 'fluid' : '';
        $class    = $view->escapeHtmlAttr(trim('project-avatar ' . $class));
        $alt      = $view->escapeHtmlAttr($name);
        $html     = '<img alt="' . $alt . '"'
            . ' src="' . $url . '" data-user="' . $view->escapeHtmlAttr($project->get('creator')) . '"'
            . ' class="' . $class . '" onerror="$(this).trigger(\'img-error\')"'
            . ' onload="$(this).trigger(\'img-load\')">';

        if ($link && $id) {
            $html = '<a href="' . $view->url('project', array('project' => $project->getId()))
                . '" title="' . $alt . '"' . ' class="avatar-wrapper avatar-link ' . $fluid . '">'
                . $html . '</a>';
        } else {
            $html = '<div class="avatar-wrapper ' . $fluid . '">' . $html . '</div>';
        }

        return $html;
    }
}
