<?php
/**
 * Configuration for the Foo testing module.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'service_manager' => array(
        'factories' => array(
            'p4' => function ($serviceManager) {
                throw new \Exception(
                    "Unexpected error - p4 factory was not configured."
                );
            },
        ),
    ),
    'router' => array(
        'routes' => array(
            'data' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/data[/]',
                    'defaults' => array(
                        'controller' => 'foo-index',
                        'action'     => 'index',
                    ),
                ),
            ),
            'foo-test' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/test[/]',
                    'defaults' => array(
                        'controller' => 'foo-index',
                        'action'     => 'test',
                    ),
                ),
            ),
            'foo-redirect' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/redirect[/]',
                    'defaults' => array(
                        'controller' => 'foo-index',
                        'action'     => 'redirect',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'foo-index' => 'Foo\Controller\IndexController'
        ),
    ),
    'view_manager' => array(
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        ),
    ),
);
