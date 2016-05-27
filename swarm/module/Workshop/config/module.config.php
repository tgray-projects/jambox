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
            'git-project' => array(
                'type' => 'Application\Router\Regex',
                'options' => array(
                    'regex'    => '/(?P<creator>[\w\-]+)\/(?P<projectname>[\w\-]+)',
                    'spec'     => '/projects/%creator%/%projectname%',
                    'defaults' => array(
                        'controller' => 'Projects\Controller\Index',
                        'action'     => 'project',
                        'project'    => null
                    )
                ),
                'priority' => -500
            ),
            'project-branches' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/workshop/fetchbranches[/:project][/]',
                    'defaults' => array(
                        'controller' => 'Workshop\Controller\Ajax',
                        'action'     => 'project',
                        'project'    => null
                    ),
                ),
            ),
        ),
    ),
    'input_filters' => array(
        'factories'  => array(
            'projectAddFilter'  => function ($manager) {
                    $services = $manager->getServiceLocator();
                    $filter = new \Projects\Filter\Project($services->get('p4_admin'), 'add');
                    $filter->get('name')->add(
                        array(
                            'name'      => 'Regex',
                            'options'   => array(
                                'pattern'   => '/^[\w\s\-]+$/',
                                'message'   => 'Name must contain only alphanumeric and underscore characters.',
                            )
                        )
                    );
                    return $filter;
            },
            'projectEditFilter'  => function ($manager) {
                $services = $manager->getServiceLocator();
                $filter = new \Projects\Filter\Project($services->get('p4_admin'), 'edit');
                $filter->get('name')->add(
                    array(
                        'name'      => 'Regex',
                        'options'   => array(
                            'pattern'   => '/^[\w\s\-]+$/',
                            'message'   => 'Name must contain only alphanumeric and underscore characters.',
                        )
                    )
                );
                return $filter;
            },
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'Workshop\Controller\Ajax'  => 'Workshop\Controller\AjaxController',
        ),
    ),
);
