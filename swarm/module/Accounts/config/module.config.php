<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2013 Perforce Software. All rights reserved
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     2013.1.MAIN-TEST_ONLY/597594
 */

return array(
    'security' => array(
        // specify route id's which bypass require_login setting
        'login_exempt'  => array('signup', 'verify', 'resetPassword'),
        'prevent_login' => array(),         // specify user ids which are not permitted to login to swarm
    ),
    'accounts' => array(
        'skip_email_validation' => true,
    ),
    'router' => array(
        'routes' => array(
            'signup' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/signup[/]',
                    'defaults' => array(
                        'controller' => 'Accounts\Controller\Index',
                        'action'     => 'signup',
                    ),
                ),
            ),
            'verify' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/account/verify[/:token][/]',
                    'defaults' => array(
                        'controller' => 'Accounts\Controller\Index',
                        'action'     => 'verify',
                    ),
                ),
            ),
            'changePassword' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/account/password/change[/:user][/]',
                    'defaults' => array(
                        'controller' => 'Accounts\Controller\Password',
                        'action'     => 'change',
                    ),
                ),
            ),
            'resetPassword' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/account/password/reset[/:user][/:token][/]',
                    'defaults' => array(
                        'controller' => 'Accounts\Controller\Password',
                        'action'     => 'reset',
                    ),
                ),
            ),
            'deleteUser' => array(
                'type' => 'Zend\Mvc\Router\Http\Segment',
                'options' => array(
                    'route'    => '/account/delete[/:user][/]',
                    'defaults' => array(
                        'controller' => 'Accounts\Controller\Index',
                        'action'     => 'delete',
                    ),
                ),
            ),
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'Accounts\Controller\Index'    => 'Accounts\Controller\IndexController',
            'Accounts\Controller\Password' => 'Accounts\Controller\PasswordController'
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'p4_super' => function ($services) {
                $config  = $services->get('config') + array('p4_super' => array());
                $p4super = (array)$config['p4_super'];

                $factory = new \Application\Connection\ConnectionFactory($p4super);
                return $factory->createService($services);
            },
        )
    ),
    'view_manager' => array(
        'template_map' => array(
            'users/index/user'       => __DIR__ . '/../view/accounts/index/user.phtml',
            'users/index/login'      => __DIR__ . '/../view/accounts/index/login.phtml',
            'users/index/change'     => __DIR__ . '/../view/accounts/password/change.phtml',
            'accounts/index/delete'  => __DIR__ . '/../view/accounts/index/delete.phtml',
            'accounts/index/signup'  => __DIR__ . '/../view/accounts/index/signup.phtml'
        ),
        'template_path_stack'   => array(
            __DIR__ . '/../view',
        ),
    ),
);
