<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'router' => array(
        'routes' => array(
            'forkProject' => array(
                'type'      => 'Zend\Mvc\Router\Http\Segment',
                'options'   => array(
                    'route'     => '/project[s]/:project/fork/:branch',
                    'defaults'  => array(
                        'controller' => 'Fork\Controller\Index',
                        'action'     => 'forking',
                        'project'    => null,
                        'branch'     => null,
                    )
                )
            ),
            'parentProject' => array(
                'type'      => 'Zend\Mvc\Router\Http\Segment',
                'options'   => array(
                    'route'     => '/project[s]/:project/parent',
                    'defaults'  => array(
                        'controller' => 'Fork\Controller\Index',
                        'action'     => 'parent',
                        'project'    => null,
                    )
                )
            )
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'Fork\Controller\Index' => 'Fork\Controller\IndexController',
        ),
    )
);
