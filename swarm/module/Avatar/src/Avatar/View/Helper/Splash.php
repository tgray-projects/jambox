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

class Splash extends AbstractHelper
{
    const   DEFAULT_COUNT = 6;

    /**
     * Renders a image tag and optional link for the given project's splash.
     *
     * @param   string|UserModel|null   $user       a user id or user object (null for anonymous)
     * @param   bool                    $class      optional - class to add to the image
     * @param   bool                    $urlOnly    optional - just return the url to the image, no extra html
     * @param   bool                    $fluid      optional - match avatar size to the container
     */
    public function __invoke($project = null, $class = null, $urlOnly = false, $fluid = true)
    {
        $view     = $this->getView();
        $services = $view->getHelperPluginManager()->getServiceLocator();
        $config   = $services->get('config');

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

        $id   = $project instanceof ProjectModel ? $project->getId()   : $user->getId();
        $name = $project instanceof ProjectModel ? $project->getName() : null;

        // determine the url to use for this project's splash based on the configured pattern
        // if no pattern is configured, fallback to a default image
        $url = $view->url('attachments', array('attachment' => $project->get('splash')));

        if ($urlOnly) {
            return $url;
        }

        // build the actual img tag we'll be using
        $fluid    = $fluid ? 'fluid' : '';
        $class    = $view->escapeHtmlAttr(trim('splash ' . $class));
        $alt      = $view->escapeHtmlAttr($name);
        $html     = '<img width="' . $config['avatar']['splashWidth'] . '" height="'
            . $config['avatar']['splashHeight'] . '" alt="' . $alt . '"'
            . ' src="' . $url . '" data-user="' . $view->escapeHtmlAttr($project->get('creator')) . '"'
            . ' class="' . $class . '">';

        $html = '<div class="splash-wrapper ' . $fluid . '">' . $html . '</div>';

        return $html;
    }
}
