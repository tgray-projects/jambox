<?php
/**
 * Container for all module tests.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ModuleTest;

class TestRunner
{
    /**
     * Build up a test suite containing all module tests.
     */
    public static function suite()
    {
        $suite = new \PHPUnit_Framework_TestSuite('All Module Tests');

        // save working directory
        $cwd = getcwd();

        $modulesDir = BASE_PATH . '/module';
        $modules    = array_diff(scandir($modulesDir), array('.', '..'));
        foreach ($modules as $module) {
            $configFile = $modulesDir . '/' . $module . '/test/phpunit.xml';
            if (file_exists($configFile)) {
                chdir(dirname($configFile));
                $config = \PHPUnit_Util_Configuration::getInstance($configFile);
                $config->handlePHPConfiguration();
                $suite->addTest($config->getTestSuiteConfiguration());
            }
        }

        // restore working directory
        chdir($cwd);

        return $suite;
    }
}
