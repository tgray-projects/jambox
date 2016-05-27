<?php
/**
 * Bootstrap the Swarm Record library.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

// leverage P4Test bootstrap
require_once __DIR__ . '/../P4Test/TestBootstrap.php';

// add module-test namespace
Zend\Loader\AutoloaderFactory::factory(
    array(
         'Zend\Loader\StandardAutoloader' => array(
             'namespaces' => array(
                 'RecordTest'   => BASE_PATH . '/tests/phpunit/RecordTest',
                 'Record'       => BASE_PATH . '/library/Record'
             )
         )
    )
);
