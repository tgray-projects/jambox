<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'router' => array(
        'routes' => array(
            'notify' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/contact[/]',
                    'defaults' => array(
                        'controller' => 'Notify\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Notify\Controller\Index'    => 'Notify\Controller\IndexController',
        ),
    ),
    'notify' => array(
        'to' => 'opensource@perforce.com', // the email address to send notifications to
        'subjects' => array(
            'Workshop Support Request', 'Bug Report', 'Feature Suggestion', 'General Comment'
        ),
    ),
    'view_manager' => array(
        'template_map' => array(
            'notify/index/contact'  => __DIR__ . '/../view/index/contact.phtml'
        ),
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        ),
    ),
);
