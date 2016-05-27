<?php
/**
 * Parent class for all TestCases.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test;

use P4;
use P4\ClientPool\ClientPool;
use P4\Connection\Connection;
use P4\Connection\ConnectionInterface;
use P4\Spec\Protections as P4Protections;
use P4\Spec\User;

class TestCase extends \PHPUnit_Framework_TestCase
{
    const TEST_MAX_TRY_COUNT    = 1000;
    public $p4;
    protected $p4Params         = array();
    protected $noP4dStdErr      = false;

    /**
     * Setup test directories and a functioning perforce server.
     */
    public function setUp()
    {
        // limit the amount of memory any given test can use to 2GB
        ini_set('memory_limit', '4G');

        // get name of the testing class - replace slashes in class
        // name to avoid propagating them into directories' names
        $testClass  = str_replace('\\', '_', get_class($this));
        $testMethod = $this->getName();

        // remove existing directories to start fresh w. each test.
        $this->removeDirectory(DATA_PATH);

        // replace any sketchy characters with - to prevent file creation issues
        $testSuffix  = preg_replace('/[^\w-]/', '-',  $testClass . '-' . $testMethod);

        // create directories needed for testing
        $serverRoot  = DATA_PATH . '/server-'  . $testSuffix;
        $clientRoot  = DATA_PATH . '/clients-' . $testSuffix;
        $directories = array(
            DATA_PATH,
            $serverRoot,
            $clientRoot,
            $clientRoot . '/superuser',
            $clientRoot . '/testuser',
        );
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
        }

        // prepare connection params and create p4 connection
        $this->p4Params = array(
            'serverRoot' => $serverRoot,
            'clientRoot' => $clientRoot,
            'port'       => 'rsh:' . P4D_BINARY . ' -i -qr ' . $serverRoot . ' -J off '
                         .  '-vtrack=0 -vserver.locks.dir=disabled',
            'user'       => 'tester',
            'client'     => 'test-client',
            'group'      => 'test-group',
            'password'   => 'testing123'
        );

        // some tests can cause spurious output on stderr on mac
        // optionally wrap the rsh invocation and redirect stderr to /dev/null
        if ($this->noP4dStdErr) {
            $this->p4Params['port'] = 'rsh:bash -c "' . substr($this->p4Params['port'], 4) . ' 2> /dev/null"';
        }

        $this->createP4Connection();

        parent::setUp();
    }

    /**
     * Clean up after ourselves.
     */
    public function tearDown()
    {
        // call p4 library shutdown functions
        if (class_exists('P4\Environment\Environment', false)) {
            P4\Environment\Environment::runShutdownCallbacks();
        }

        // disconnect the p4 connection, if exists
        if (isset($this->p4)) {
            $this->p4->disconnect();
        }

        // clear default connection
        if (class_exists('P4\Connection\Connection', false)) {
            Connection::clearDefaultConnection();
        }

        // clear out shutdown callbacks
        if (class_exists('P4\Environment\Environment', false)) {
            P4\Environment\Environment::setShutdownCallbacks(null);
        }

        // forces collection of any existing garbage cycles
        // so no open file handles prevent files/directories
        // from being removed.
        gc_collect_cycles();

        // remove testing directory
        $this->removeDirectory(DATA_PATH);

        parent::tearDown();

        // if phpunit wants to use a bunch of memory after a test runs (e.g. for code coverage) so be it
        ini_set('memory_limit', -1);
    }

    /**
     * Create a Perforce connection for testing. The perforce connection will
     * connect using a p4d started with the -i (run for inetd) flag.
     *
     * @param   string|null     $type   allow caller to force the API
     *                                  implementation.
     * @return  P4\Connection\ConnectionInterface   a Perforce API implementation
     */
    public function createP4Connection($type = null)
    {
        extract($this->p4Params);

        if (!is_dir($serverRoot)) {
            throw new P4\Exception('Unable to create new server.');
        }

        // create connection.
        $p4 = Connection::factory($port, $user, $client, $password, null, $type);

        // set server into Unicode mode if a charset was set (or set to something other than 'none')
        if (USE_UNICODE_P4D) {
            exec(P4D_BINARY . ' -xi -r ' . $serverRoot, $output, $status);

            if ($status != 0) {
                die("error (" . $status . "): problem setting server into Unicode mode:\n" . $output);
            }
        }

        // add noisy triggers if requested
        if (USE_NOISY_TRIGGERS) {
            $triggers = P4\Spec\Triggers::fetch($this->p4);

            // start with the unique triggers
            $script =  "%quote%" . __DIR__ . "/assets/scripts/noisyTrigger.sh%quote%";
            $lines  = array(
                    "noisy.change-submit    change-submit   //...   \"$script change-submit\"",
                    "noisy.change-content   change-content  //...   \"$script change-content\"",
                    "noisy.change-commit    change-commit   //...   \"$script change-commit\"",
                    "noisy.fix-add          fix-add         fix     \"$script fix-add\"",
                    "noisy.fix-delete       fix-delete      fix     \"$script fix-delete\"",
                    "noisy.shelve-submit    shelve-submit   //...   \"$script shelve-submit\"",
                    "noisy.shelve-commit    shelve-commit   //...   \"$script shelve-commit\"",
                    "noisy.shelve-delete    shelve-delete   //...   \"$script shelve-delete\""
            );

            // put in in/out/save/commit/delete for various form types
            $forms = array(
                'branch', 'change', 'client', 'depot', 'group', 'job', 'label', 'spec',
                'stream', 'triggers', 'typemap', 'user'
            );
            foreach ($forms as $form) {
                $lines[] = "noisy.$form-form-in     form-in     $form   \"$script $form-form-in\"";
                $lines[] = "noisy.$form-form-out    form-out    $form   \"$script $form-form-out\"";
                $lines[] = "noisy.$form-form-save   form-save   $form   \"$script $form-form-save\"";
                $lines[] = "noisy.$form-form-commit form-commit $form   \"$script $form-form-commit\"";
                $lines[] = "noisy.$form-form-delete form-delete $form   \"$script $form-form-delete\"";

            }

            $triggers->setTriggers($lines)->save();

            // force a reconnect as triggers seem to require it
            $triggers->getConnection()->disconnect();
        }

        // give the connection a client manager
        $clients = new ClientPool($p4);
        $clients->setMax(10)->setRoot(DATA_PATH . '/clients')->setPrefix('test-');
        $p4->setService('clients', $clients);

        // create user.
        $userForm = array(
            'User'     => $user,
            'Email'    => $user . '@testhost',
            'FullName' => 'Test User',
            'Password' => $password
        );
        $p4->run('user', '-i', $userForm);
        $p4->run('login', array(), $password);

        // establish protections.
        // This looks like a no-op, but remember that fresh P4 servers consider
        // every user to be a superuser. These operations make only the configured
        // user a superuser, and subsequent users will be 'normal' users.
        $result  = $p4->run('protect', '-o');
        $protect = $result->getData(0);
        $p4->run('protect', '-i', $protect);

        // create client
        $clientForm = array(
            'Client'    => $client,
            'Owner'     => $user,
            'Root'      => $clientRoot . '/superuser',
            'View'      => array('//depot/... //' . $client . '/...')
        );
        $p4->run('client', '-i', $clientForm);

        $this->openPermissions($serverRoot, true);

        $this->p4 = $p4;

        return $this->p4;
    }

    /**
     * Recursively remove a directory and all of it's file contents.
     *
     * @param  string   $directory   The directory to remove.
     * @param  boolean  $recursive   when true, recursively delete directories.
     * @param  boolean  $removeRoot  when true, remove the root (passed) directory too
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
                                    "Can't delete '" . $file->getPathname() . "' with message ".$e->getMessage()
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
                                "Can't delete '" . $directory->getPathname() . "' with message ".$e->getMessage()
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Get Perforce config parameters
     *
     * @param  string   $param   Optional - specific Perforce parameter to get
     *
     * @return mixed    A specific Perforce parameter, or all parameters
     */
    public function getP4Params($param = null)
    {
        $params = $this->p4Params;
        if ($param) {
            return isset($params[$param]) ? $params[$param] : null;
        }
        return $params;
    }

    /**
     * Helper method to create and connect as a user with limited access to depot.
     * This will modify protections table by adding lines to grant access for the specified user
     * to only those paths specified. Access defaults to 'list', but a specific mode can be given
     * for each path by specifying the path as the key and the mode as the value.
     *
     * @param   string                  $user       user to create
     * @param   array                   $paths      list of paths to grant user access to each path can be specified as:
     *                                              ['path' => 'permission'] or ['path']
     * @param   ConnectionInterafce     $p4Super    optional - super user connection needed
     *                                              to modify protections table
     * @return  Connection              connection for the new user
     */
    public function connectWithAccess($user, array $paths, ConnectionInterface $p4Super = null)
    {
        $p4Super = $p4Super ?: $this->p4;

        // throw if user already exists
        if (User::exists($user, $this->p4)) {
            throw new \Exception("User already exists.");
        }

        // create user
        $model = new User($this->p4);
        $model->setId($user)
              ->setFullName("$user (limited access)")
              ->setEmail("$user@limited")
              ->save();

        // add paths to the permissions table
        $protectionLines = array();
        foreach ($paths as $path => $permission) {
            if ($path === (int) $path) {
                $path       = $permission;
                $permission = 'list';
            }
            $protectionLines[] = "$permission user $user * $path";
        }

        $protections = P4Protections::fetch($p4Super);
        $protections->setProtections(
            array_merge(
                $protections->getProtections(),
                array("list user $user * -//..."),
                $protectionLines
            )
        )->save();

        // return connection for the new user
        return Connection::factory(
            $this->getP4Params('port'),
            $user,
            'client-' . $user . '-test',
            '',
            null,
            null
        );
    }

    /**
     * Open up permissions (possibly recursively) on a directory. All files
     * in the directory (including the directory itself) will be given a
     * permission mask of 0777. This method checks that the owner of the
     * running PHP process owns each file before it attempts to change
     * permissions on it.
     *
     * @param  string  $directory  the directory to change permissions on.
     * @param  bool    $recursive  optional - whether to do so recursively.
     */
    protected function openPermissions($directory, $recursive = false)
    {
        $uid   = getmyuid();
        $files = new \RecursiveDirectoryIterator($directory);

        foreach ($files as $file) {
            $stat = stat($file->getPathname());
            if ($stat['uid'] != $uid) {
                // skip files we don't own
                continue;
            }
            if (!chmod($file->getPathname(), 0777)) {
                throw new \Exception(
                    "Can't set permissions on '" . $file->getPathname() . "'"
                );
            }
            if ($file->isDir() && $recursive) {
                if ($files->isDot()) {
                    continue;
                }
                $this->openPermissions($file->getPathname(), $recursive);
            }
        }

        chmod($directory, 0777);
    }
}
