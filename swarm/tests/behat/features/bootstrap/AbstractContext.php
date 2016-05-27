<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

use Behat\Behat\Context\BehatContext;
use Behat\Mink\Session as MinkSession;

class AbstractContext extends BehatContext
{
    protected $configParams;
    const TEST_MAX_TRY_COUNT = 10;


    public function __construct(array $parameters)
    {
        $this->configParams = $parameters;
    }

    /**
     * Helper function to access members of the P4Context class.
     *
     * @return P4Context
     */
    protected function getP4Context()
    {
        return $this->getMainContext()->getSubContext('p4');
    }

    /**
     * Helper function to access members of the MinkContext class.
     *
     * @return FeatureMinkContext  context
     */
    protected function getMinkContext()
    {
        return $this->getMainContext()->getSubContext('mink');
    }

    /**
     * Helper function to access Mink browser session.
     *
     * @return MinkSession
     */
    protected function getSession()
    {
        return $this->getMinkContext()->getSession();
    }

    /**
     * Set unique session cookie named 'SwarmDataPath' for each distinct test scenario run
     * The value of the cookie is the unique id 'uuid' generated for each distinct scenario run
     * The cookie is set on the swarm host 'base_url' that is read from the config.yml file
     */
    protected function setSwarmCookie()
    {
        $session = $this->getSession();
        // Visit the swarm host url before the cookie is set, so that the domain for the cookie gets set
        $session->visit($this->configParams['base_url']);
        // Set the cookie
        $session->setCookie('SwarmDataPath', $this->getP4Context()->getUUID());
        // Re-visit the swarm host url, after the cookie has been set
        $session->visit($this->configParams['base_url']);
    }

    /**
     * Return the value set for the cookie 'SwarmDataPath' for a given scenario run.
     * It should be equal to the unique 'uuid' value generated for the scenario
     *
     * @return null|string  UUID value set for the SwarmDataPath cookie
     */
    protected function getSwarmCookie()
    {
        return $this->getSession()->getCookie('SwarmDataPath');
    }


    /**
     * Recursively remove a directory and all of it's file contents.
     *
     * @param  string $directory The directory to remove.
     * @param  boolean $recursive when true, recursively delete directories.
     * @param  boolean $removeRoot when true, remove the root (passed) directory too
     */
    public function removeDirectory($directory, $recursive = true, $removeRoot = true)
    {

        if (is_dir($directory)) {
            chmod($directory, 0777);
            $files = new \RecursiveDirectoryIterator($directory);
            foreach ($files as $file) {
                if ($files->isDot()) {
                    continue;
                }
                if ($file->isFile()) {
                    // on Windows, it may take some time for open file handles to
                    // be closed.  We try to unlink a file for TEST_MAX_TRY_COUNT
                    // times and then bail out.
                    $count = 0;
                    chmod($file->getPathname(), 0777);
                    while ($count <= self::TEST_MAX_TRY_COUNT) {
                        try {
                            unlink($file->getPathname());
                            break;
                        } catch (\Exception $e) {
                            $count++;
                            if ($count == self::TEST_MAX_TRY_COUNT) {
                                throw new \Exception(
                                    "Can't delete '" . $file->getPathname() . "' with message " . $e->getMessage()
                                );
                            }
                        }
                    }
                } elseif ($file->isDir() && $recursive) {
                    $this->removeDirectory($file->getPathname(), true, true);
                }
            }

            if ($removeRoot) {
                chmod($directory, 0777);
                $count = 0;
                while ($count <= self::TEST_MAX_TRY_COUNT) {
                    try {
                        rmdir($directory);
                        break;
                    } catch (\Exception $e) {
                        $count++;
                        if ($count == self::TEST_MAX_TRY_COUNT) {
                            throw new \Exception(
                                "Can't delete '" . $directory->getPathname() . "' with message " . $e->getMessage()
                            );
                        }
                    }
                }
            }
        }
    }
}

