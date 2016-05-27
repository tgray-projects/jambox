<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'avatar' => array(
        'splashHeight'  => 255,
        'splashWidth'   => 1200,
        'avatarHeight'  => 1024,
        'avatarWidth'   => 1024
    ),
    'router' => array(
        'routes' => array(
            'projectImage' => array(
                'type'      => 'Zend\Mvc\Router\Http\Segment',
                'options'   => array(
                    'route'     => '/project[s]/:project/image/:type',
                    'defaults'  => array(
                        'controller' => 'Avatar\Controller\Index',
                        'action'     => 'project',
                        'project'    => null,
                        'type'       => null,
                    )
                )
            )
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'Avatar\Controller\Index' => 'Avatar\Controller\IndexController',
        ),
    ),
    'view_helpers' => array(
        'invokables' => array(
            'projectAvatar' => 'Avatar\View\Helper\Avatar',
            'projectSplash' => 'Avatar\View\Helper\Splash'
        ),
    )
);
