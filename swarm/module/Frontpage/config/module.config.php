<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'frontpage' => array(
        'projects' => array(
            'maximum' => 3,     // maximum number of recent projects to display in the carousel
            'minimum' => 2,     // minimum number of recent projects to display in the carousel
            'wait'    => 10,    // if minimum projects are found, loop this many times without finding a project before
                                // exiting.  used to prevent long ajax query run time.
            'pad'     => true   // if less than the maximum recent project are found, pad out with un-recent projects
        )
    ),
    'router' => array(
        'routes' => array(
            'projects-list' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/frontpage/projects-list[/:source][/count/:count][/user/:user][/]',
                    'defaults' => array(
                        'controller' => 'Frontpage\Controller\Index',
                        'action'     => 'projects'
                    ),
                ),
            ),
            'explore' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/explore/',
                    'defaults' => array(
                        'controller' => 'Users\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            'update-projects' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/updateprojects[/]',
                    'defaults' => array(
                        'controller' => 'Frontpage\Controller\Index',
                        'action'     => 'updateprojects',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Frontpage\Controller\Index' => 'Frontpage\Controller\IndexController'
        ),
    ),
    'view_helpers' => array(
        'invokables' => array(
            'frontPageActivity' => 'Frontpage\View\Helper\Activity',
            'message'           => 'Frontpage\View\Helper\Message',
            'projectLink'       => 'Frontpage\View\Helper\ProjectLink',
            'projectGrid'       => 'Frontpage\View\Helper\ProjectGrid',
            'smartTruncate'     => 'Frontpage\View\Helper\SmartTruncate',
        ),
    ),
    'view_manager' => array(
        'template_map' => array(
            'users/index/index' => __DIR__ . '/../view/frontpage/index/index.phtml',
            'layout/layout'     => __DIR__ . '/../view/layout/layout.phtml',
            'layout/toolbar'    => __DIR__ . '/../view/layout/toolbar.phtml',
        ),
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        )
    ),
);
