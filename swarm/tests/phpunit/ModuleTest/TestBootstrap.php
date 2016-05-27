<?php
/**
 * Bootstrap a Swarm module test.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

// leverage P4Test bootstrap
require_once __DIR__ . '/../P4Test/TestBootstrap.php';

// run in development mode
putenv('SWARM_MODE=development');

// add module-test namespace
Zend\Loader\AutoloaderFactory::factory(
    array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'ModuleTest' => BASE_PATH . '/tests/phpunit/ModuleTest',
                'Record'     => BASE_PATH . '/library/Record'
            )
        )
    )
);
