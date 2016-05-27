<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

// set error reporting to the level to code must comply.
error_reporting(E_ALL & ~E_STRICT);

// define path constants
// Note that this file lies under 'tests/behat/features/bootstrap'
defined('BASE_PATH')
|| define('BASE_PATH', realpath(__DIR__ . '/../../../../'));

// define whether to place the Perforce server in unicode mode
if (!defined('USE_UNICODE_P4D') && getenv('SWARM_USE_UNICODE_P4D')) {
    define(
        'USE_UNICODE_P4D',
        strtolower(getenv('SWARM_USE_UNICODE_P4D')) == 'true' || getenv('SWARM_USE_UNICODE_P4D') == '1'
    );
}
// set to false if not set
if (!defined('USE_UNICODE_P4D')) {
    define('USE_UNICODE_P4D', false);
}

define('USE_NOISY_TRIGGERS', false);

// prepend the app library and tests directories to the include path
// so that tests can be run without manual configuration of the include path.
set_include_path(
    implode(PATH_SEPARATOR, array(BASE_PATH . '/library', BASE_PATH . '/tests/behat', get_include_path()))
);

// setup autoloading for behat tests
require_once BASE_PATH . '/library/Zend/Loader/AutoloaderFactory.php';
Zend\Loader\AutoloaderFactory::factory(
    array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'P4'         => BASE_PATH . '/library/P4',
                'Record'     => BASE_PATH . '/library/Record',
                'Zend'       => BASE_PATH . '/library/Zend',
                'BehatTests' => BASE_PATH . '/tests/behat/features/bootstrap'
            )
        )
    )
);

// ignore P4IGNORE
putenv('P4IGNORE=');

// set default timezone to suppress PHP warnings.
date_default_timezone_set(@date_default_timezone_get());

// Needed by Behat
require_once 'vendor/autoload.php';
require_once 'vendor/phpunit/phpunit/src/Framework/Assert/Functions.php';
define('BEHAT_ERROR_REPORTING', E_ERROR | E_WARNING | E_PARSE);
