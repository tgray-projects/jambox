<?php
/**
 * Bootstrap a P4 test.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

// set error reporting to the level to code must comply.
error_reporting(E_ALL & ~E_STRICT);

// define path constants
defined('BASE_PATH')
    || define('BASE_PATH', realpath(__DIR__ . '/../../../'));

defined('DATA_PATH')
    || define('DATA_PATH', BASE_PATH . '/tests/data/' . getmypid());

defined('ASSETS_PATH')
    || define('ASSETS_PATH', __DIR__ . '/assets');

// define p4d location
if (!defined('P4D_BINARY') && getenv('SWARM_P4D_BINARY')) {
    define('P4D_BINARY', getenv('SWARM_P4D_BINARY'));
}
// use p4d from PATH if it did not get set above
if (!defined('P4D_BINARY')) {
    define('P4D_BINARY', 'p4d');
}

// define whether to place the Perforce server in unicode mode
if (!defined('USE_UNICODE_P4D') && getenv('SWARM_USE_UNICODE_P4D')) {
    $swarmUseUnicode = getenv('SWARM_USE_UNICODE_P4D');
    define('USE_UNICODE_P4D', strtolower($swarmUseUnicode) == 'true' || $swarmUseUnicode == '1');
}
// set to false if not set
if (!defined('USE_UNICODE_P4D')) {
    define('USE_UNICODE_P4D', false);
}

// define whether to add noisy triggers to the Perforce server
if (!defined('USE_NOISY_TRIGGERS') && getenv('SWARM_USE_NOISY_TRIGGERS')) {
    $swarmUseNoisyTriggers = getenv('SWARM_USE_NOISY_TRIGGERS');
    define('USE_NOISY_TRIGGERS', strtolower($swarmUseNoisyTriggers) == 'true' || $swarmUseNoisyTriggers == '1');
}
// set to false if not set
if (!defined('USE_NOISY_TRIGGERS')) {
    define('USE_NOISY_TRIGGERS', false);
}

// prepend the app library and tests directories to the include path
// so that tests can be run without manual configuration of the include path.
$path = array(BASE_PATH . '/library', BASE_PATH . '/tests/phpunit', get_include_path());
set_include_path(implode(PATH_SEPARATOR, $path));

// setup autoloading.
require_once BASE_PATH . '/library/Zend/Loader/AutoloaderFactory.php';
Zend\Loader\AutoloaderFactory::factory(
    array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'Zend'       => BASE_PATH . '/library/Zend',
                'P4'         => BASE_PATH . '/library/P4',
                'P4Test'     => BASE_PATH . '/tests/phpunit/P4Test',
            )
        )
    )
);

// set perforce environment variables to allow for test parallelization
if (!putenv('P4TICKETS=' . DATA_PATH . '/p4tickets.txt')) {
    echo "WARNING: Cannot set P4TICKETS\n";
}

// ignore P4IGNORE
putenv('P4IGNORE=');

// set default timezone to suppress PHP warnings.
date_default_timezone_set(@date_default_timezone_get());
