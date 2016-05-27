<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'router' => array(
        'routes' => array(
            'api' => array(
                'type' => 'literal',
                'options' => array(
                    'route' => '/api',
                ),
                'may_terminate' => false,
                'child_routes' => array(
                    'version' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route' => '/[:version/]version[/]',
                            'constraints' => array('version' => 'v1(\.1)?'),
                            'defaults' => array(
                                'controller' => 'Api\Controller\V1\Index',
                                'action'     => 'version'
                            ),
                        ),
                    ),
                    'activity' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'may_terminate' => true,
                        'options' => array(
                            'route' => '/:version/activity[/]',
                            'constraints' => array('version' => 'v1(\.1)?'),
                            'defaults' => array(
                                'controller' => 'Api\Controller\V1\Activity',
                            ),
                        ),
                    ),
                    'projects' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'may_terminate' => true,
                        'options' => array(
                            'route' => '/:version/projects[/]',
                            'constraints' => array('version' => 'v1(\.1)?'),
                            'defaults' => array(
                                'controller' => 'Api\Controller\V1\Projects',
                            ),
                        ),
                    ),
                    'reviews' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route' => '/:version/reviews[/:id][/]',
                            'constraints' => array('version' => 'v1(\.1)?'),
                            'defaults' => array(
                                'controller' => 'Api\Controller\V1\Reviews',
                            ),
                        ),
                    ),
                    'reviews/changes' => array(
                        'type' => 'Zend\Mvc\Router\Http\Segment',
                        'options' => array(
                            'route' => '/:version/reviews/:id/changes[/]',
                            'constraints' => array('version' => 'v1(\.1)?'),
                            'defaults' => array(
                                'controller' => 'Api\Controller\V1\Reviews',
                                'action'     => 'addChange',
                            ),
                        ),
                    ),
                    'notfound' => array(
                        'type' => 'Zend\Mvc\Router\Http\Regex',
                        'priority' => -100,
                        'options' => array(
                            'regex' => '/(?P<path>.*)|$',
                            'spec'  => '/%path%',
                            'defaults' => array(
                                'controller' => 'Api\Controller\V1\Index',
                                'action'     => 'notFound',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Api\Controller\V1\Activity' => 'Api\Controller\V1\ActivityController',
            'Api\Controller\V1\Index'    => 'Api\Controller\V1\IndexController',
            'Api\Controller\V1\Projects' => 'Api\Controller\V1\ProjectsController',
            'Api\Controller\V1\Reviews'  => 'Api\Controller\V1\ReviewsController',
        ),
    ),
);
