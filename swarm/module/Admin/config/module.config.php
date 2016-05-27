<?php
/**
 * Perforce Workshop
 *
 * @copyright   2016 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'router' => array(
        'routes' => array(
            'admin' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/admin[/]',
                    'defaults' => array(
                        'controller' => 'Admin\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            'moveFollowers' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/admin/moveFollowers/:source/:target[/]',
                    'defaults' => array(
                        'controller' => 'Admin\Controller\Index',
                        'action'     => 'moveFollowers',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Admin\Controller\Index' => 'Admin\Controller\IndexController'
        ),
    ),
    'view_helpers' => array(
        'invokables' => array(
            'adminToolbar'  => 'Admin\View\Helper\AdminToolbar',
        ),
    ),
    'view_manager' => array(
        'template_map' => array(
            'admin/index/index'         => __DIR__ . '/../view/admin/index/index.phtml',
            'admin/index/moveFollowers' => __DIR__ . '/../view/admin/index/move-followers.phtml',
        ),
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        ),
    ),
);
