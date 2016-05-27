<?php
/**
 * Bootstrap a Swarm Frontend test.  This is predominantly copied from
 * ../../P4Test/TestBootstrap.php, but that file defines DATA_PATH, which must
 * be undefined for our purposes.  Because of this, we cannot extend the
 * P4Test/TestBootstrap.php file.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

// set error reporting to the level to code must comply.
error_reporting(E_ALL & ~E_STRICT);

// define path constants
defined('BASE_PATH')
    || define('BASE_PATH', realpath(__DIR__ . '/../../../../'));

defined('ASSETS_PATH')
    || define('ASSETS_PATH', __DIR__ . '/assets');

// define p4 location
if (!defined('P4_BINARY') && getenv('SWARM_P4_BINARY')) {
    define('P4_BINARY', getenv('SWARM_P4_BINARY'));
}
// use p4 from PATH if it did not get set above
if (!defined('P4_BINARY')) {
    define('P4_BINARY', 'p4');
}

// abort if sausage path is not defined
if (!defined('SAUSAGE_PATH') && getenv('SAUSAGE_PATH')) {
    define('SAUSAGE_PATH', getenv('SAUSAGE_PATH'));
} else if (getenv('SAUSAGE_PATH') === false) {
    die("Could not find Sausage - please set SAUSAGE_PATH environment variable with your Sausage install path.\n");
}

// abort, we need full path for p4d binary
if (!defined('P4D_BINARY') && getenv('P4D_BINARY') && is_readable(getenv('P4D_BINARY'))) {
    define('P4D_BINARY', getenv('P4D_BINARY'));
} else {
    die("Could not find p4d - please set P4D_BINARY environment variable with the full path to p4d.\n");
}

if (!defined('SWARM_TEST_HOST') && getenv('SWARM_TEST_HOST')) {
    define('SWARM_TEST_HOST', getenv('SWARM_TEST_HOST'));
}
else {
    die("Please set SWARM_TEST_HOST environment variable with the name of the Swarm host to use for testing.\n");
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
$path = array(BASE_PATH . '/library', BASE_PATH . '/tests/automated/tests', get_include_path());
set_include_path(implode(PATH_SEPARATOR, $path));

// setup autoloading.
require_once BASE_PATH . '/library/Zend/Loader/AutoloaderFactory.php';
Zend\Loader\AutoloaderFactory::factory(
    array(
        'Zend\Loader\StandardAutoloader' => array(
            'namespaces' => array(
                'P4'         => BASE_PATH . '/library/P4',
                'Zend'       => BASE_PATH . '/library/Zend',
                'Pages'      => BASE_PATH . '/tests/phpunit/FrontendTest/pages',
                'Tests'      => BASE_PATH . '/tests/phpunit/FrontendTest/tests',
            )
        )
    )
);

// bring in the Sauce and Sausage classes
require SAUSAGE_PATH . '/vendor/autoload.php';

// set perforce environment variables to allow for test parallelization
// temporarily disabled because data path is defined in SwarmTest.php
// @todo - revisit when parallelization is addressed
//if (!putenv('P4TICKETS=' . DATA_PATH . '/p4tickets.txt')) {
//    echo "WARNING: Cannot set P4TICKETS\n";
//}

// ignore P4IGNORE
putenv('P4IGNORE=');

// set default timezone to suppress PHP warnings.
date_default_timezone_set(@date_default_timezone_get());
