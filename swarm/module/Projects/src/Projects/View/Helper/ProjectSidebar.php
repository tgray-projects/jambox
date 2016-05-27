<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Projects\View\Helper;

use Projects\Model\Project;
use Zend\View\Helper\AbstractHelper;

class ProjectSidebar extends AbstractHelper
{
    /**
     * Returns the markup for a project sidebar.
     *
     * @param   Project|string      $project    the project to render sidebar for
     * @return  string              markup for the project sidebar
     */
    public function __invoke($project)
    {
        $view       = $this->getView();
        $services   = $view->getHelperPluginManager()->getServiceLocator();
        $config     = $services->get('config');
        $mainlines  = isset($config['projects']['mainlines']) ? (array) $config['projects']['mainlines'] : array();
        $members    = $project->getAllMembers();
        $branches   = $project->getBranches('name', $mainlines);
        $followers  = $project->getFollowers($members);
        $user       = $services->get('user');
        $isMember   = in_array($user->getId(), $members);
        $isFollower = in_array($user->getId(), $followers);

        $html = '<div class="span3 profile-sidebar project-sidebar">'
              .   '<div class="profile-info">'
              .     '<div class="title pad2 padw3">'
              .       '<h4>' . $view->te('About') . '</h4>'
              .     '</div>'
              .     '<div class="body">';

        if ($project->getDescription()) {
            $html .= '<div class="description force-wrap pad3">'
                  .    $view->preformat($project->getDescription())
                  .  '</div>';
        }

        if (!$isMember) {
            $click = "swarm.user.follow('project', '" . $view->escapeJs($project->getId()) . "', this);";
            $html .= '<div class="privileged buttons ' . ($project->getDescription() ? 'pad1' : 'pad2') . ' padw2">'
                  .    '<button type="button" '
                  .           'class="btn btn-primary btn-block ' . ($isFollower ? 'following' : '') . '" '
                  .         'onclick="' . $click . '">'
                  .      $view->te($isFollower ? 'Unfollow' : 'Follow')
                  .    '</button>'
                  .  '</div>';
        }

        $html .=     '<div class="metrics pad2">'
              .        '<ul class="force-wrap clearfix">'
              .          '<li class="members pull-left border-box pad2 padw0">'
              .            '<span class="count">' . count($members) . '</span><br>'
              .            $view->tpe('Member', 'Members', count($members))
              .          '</li>'
              .          '<li class="followers pull-left border-box pad2 padw0">'
              .            '<span class="count">' . count($followers) . '</span><br>'
              .            $view->tpe('Follower', 'Followers', count($followers))
              .          '</li>'
              .          '<li class="branches pull-left border-box pad2 padw0">'
              .            '<span class="count">' . count($branches) . '</span><br>'
              .            $view->tpe('Branch', 'Branches', count($branches))
              .          '</li>'
              .        '</ul>'
              .      '</div>'
              .    '</div>'
              .  '</div>';

        if ($members) {
            $html .= '<div class="members profile-block">'
                  .    '<div class="title pad1 padw0">' . $view->te('Members') . '</div>'
                  .    $view->avatars($members, 5)
                  .  '</div>';
        }

        $html .= '<div class="followers profile-block ' . (!$followers ? 'hidden' : '') . '">'
              .    '<div class="title pad1 padw0">' . $view->te('Followers') . '</div>'
              .    $view->avatars($followers, 5)
              .  '</div>';

        if ($branches) {
            $html .= '<div class="branches profile-block">'
                  .    '<div class="title pad1 padw0">' . $view->te('Branches') . '</div>'
                  .      '<ul>';
            foreach ($branches as $branch) {
                $main = in_array(strtolower($branch['name']), $mainlines);
                $url  = $view->url(
                    'project-browse',
                    array('project' => $project->getId(), 'mode' => 'files', 'path' => $branch['id'])
                );
                $html .= '<li><a href="' . $url . '">'
                      .    ($main ? '<strong>' : '')
                      .    $view->escapeHtml($branch['name'])
                      .    ($main ? '</strong>' : '')
                      .  '</a></li>';
            }
            $html .=   '</ul>'
                  .  '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
