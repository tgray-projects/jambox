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
            'job' => array(
                'type' => 'Application\Router\Regex',
                'options' => array(
                    'regex'    => '/jobs?(/(?P<job>.*))?',
                    'spec'     => '/jobs/%job%',
                    'defaults' => array(
                        'controller' => 'Jobs\Controller\Index',
                        'action'     => 'job',
                        'job'        => null
                    ),
                ),
            ),
            'jobs' => array(
                'type' => 'Application\Router\Regex',
                'options' => array(
                    'regex'    => '/jobs?(/(?P<job>.*))?',
                    'spec'     => '/jobs/%job%',
                    'defaults' => array(
                        'controller' => 'Jobs\Controller\Index',
                        'action'     => 'job',
                        'job'        => null
                    ),
                ),
            ),
            'can-edit-job' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/projects/:project/job/:job/check[/]',
                    'defaults' => array(
                        'controller' => 'Jobs\Controller\Index',
                        'action'     => 'canEdit',
                        'project'    => null,
                        'job'        => null,
                    ),
                ),
                'priority' => 1000,
            ),
            'job-add' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/projects/:project/job/add[/]',
                    'defaults' => array(
                        'controller' => 'Jobs\Controller\Index',
                        'action'     => 'addJob',
                        'project'    => null,
                        'job'        => 'new',
                    ),
                ),
                'priority' => 1000,
            ),
            'job-edit' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/projects/:project/job/:job/edit[/]',
                    'defaults' => array(
                        'controller' => 'Jobs\Controller\Index',
                        'action'     => 'editJob',
                        'project'    => null,
                        'job'        => null
                    ),
                ),
                'priority' => 1000,
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Jobs\Controller\Index' => 'Jobs\Controller\IndexController'
        ),
    ),
    'view_manager' => array(
        'template_map' => array(
            'jobs/index/index'      => __DIR__ . '/../view/jobs/index/job.phtml',
            'jobs/index/add-job'    => __DIR__ . '/../view/jobs/index/add-job.phtml',
            'jobs/index/edit-job'   => __DIR__ . '/../view/jobs/index/edit-job.phtml'
        ),
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        ),
    ),
);
