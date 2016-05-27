<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */
 
return array(
    'router' => array(
        'routes' => array(
            'teampage' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/team[/]',
                    'defaults' => array(
                        'controller' => 'Teampage\Controller\Index',
                        'action'     => 'teampage',
                    ),
                ),
            )
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Teampage\Controller\Index' => 'Teampage\Controller\IndexController'
        ),
    ),
    'view_manager' => array(
        'template_map' => array(
            'teampage/index/index'  => __DIR__ . '/../view/teampage/index/teampage.phtml',
        ),
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        ),
    ),
);
