<?php
/**
 * Perforce Workshop
 *
 * @copyright   2016 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Admin\View\Helper;

use Zend\View\Helper\AbstractHelper;

class AdminToolbar extends AbstractHelper
{
    /**
     * Returns the markup for an admin toolbar.
     *
     * @return  string              markup for the project toolbar
     */
    public function __invoke()
    {
        $view        = $this->getView();
        $services    = $view->getHelperPluginManager()->getServiceLocator();
        $event       = $services->get('Application')->getMvcEvent();
        $route       = $event->getRouteMatch()->getMatchedRouteName();

        // declare admin links
        $links = array(
            array(
                'label'  => 'Overview',
                'url'    => $view->url('admin'),
                'active' => $route === 'admin',
                'class'  => 'overview-link'
            ),
        );

        // render list of links
        $list = '';
        foreach ($links as $link) {
            $list .= '<li class="' . ($link['active'] ? 'active' : '') . '">'
                  .  '<a href="' . $link['url'] . '" class="' . $link['class'] . '">'
                  . $view->te($link['label'])
                  . '</a>'
                  .  '</li>';
        }

        // render project toolbar
        return '<div class="project-navbar navbar">'
             . ' <div class="navbar-inner">'
             . '  <div class="brand">'
             . 'Admin'
             . '  </div>'
             . '  <ul class="nav">' . $list . '</ul>'
             . ' </div>'
             . '</div>';
    }
}
