<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

use Behat\Behat\Event\SuiteEvent;

class FeatureContext extends AbstractContext
{
    protected static $dataDir;
    protected static $p4dDir;
    protected static $failuresDir;

    /**
     * @param array $parameters Context parameters passed in from behat config file
     */
    public function __construct(array $parameters)
    {
        self::$p4dDir      = $parameters['p4d_dir'];
        self::$dataDir     = $parameters['data_dir'];
        self::$failuresDir = $parameters['failures_dir'];
        $this->useContext('mink', new FeatureMinkContext($parameters));
    }

    /**
     * The 'BeforeSuite' steps are executed once before any test scenario gets run
     *
     * @BeforeSuite
     */
    public static function beforeSuite(SuiteEvent $event)
    {
        // if data directory does not exist - create it
        if (!is_dir(self::$dataDir)) {
            mkdir(self::$dataDir, 0777);
        }

        // if failures directory does not exist - create it with the setuid bit set so any files or
        // subdirectories are created as the user running the tests
        if (!is_dir(self::$failuresDir)) {
            mkdir(self::$failuresDir, 04777);
        }

        // if p4d directory does not exist - create it
        // SetUid for directory so that p4d binaries under dir are owned by user
        if (!is_dir(self::$p4dDir)) {
            mkdir(self::$p4dDir, 04777);
        }
        // auto-delete all failures that are older than 2 weeks (14 days)
        $dirs  = scandir(self::$failuresDir);

        $now   = time();
        foreach ($dirs as $dir) {
            if (is_file($dir) || $dir == '.' || $dir == '..') {
                continue;
            }

            $dir = self::$failuresDir . '/' . $dir;
            // delete directory if it is older than 14 days
            if ($now - filemtime($dir) >= (60 * 60 * 24 * 14)) {
                foreach (scandir($dir) as $file) {
                    $file = $dir . '/' . $file;
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
                @rmdir($dir);
            }
        }
    }
}
