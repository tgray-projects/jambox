<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'attachments' => array(
        'max_file_size' => null, // in bytes; will default to php upload_max_size if blank
    ),
    'router' => array(
        'routes' => array(
            'attachments' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/attachment[s]/:attachment[/][:filename]',
                    'defaults' => array(
                        'controller' => 'Attachments\Controller\Index',
                        'action'     => 'index',
                    ),
                ),
            ),
            'add-attachment' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/attachments/add[/]',
                    'defaults' => array(
                        'controller' => 'Attachments\Controller\Index',
                        'action'     => 'add',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Attachments\Controller\Index' => 'Attachments\Controller\IndexController'
        ),
    ),
);
