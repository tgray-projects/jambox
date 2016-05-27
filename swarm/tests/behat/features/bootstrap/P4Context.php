<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

use P4\ClientPool\ClientPool;
use P4\Connection\Connection;
use P4\Uuid\Uuid;
use P4\Spec\Triggers;
use P4\Spec\Protections;

class P4Context extends AbstractContext
{
    protected $superP4;
    protected $adminP4;
    protected $userP4;
    protected $p4users = array (
        'super' => array (
            'User'     => 'swarm-super',
            'Email'    => 'super@testhost',
            'FullName' => 'Super User',
            'Password' => 'testsuper'
            ),
        'admin' => array(
            'User'     => 'swarm-admin',
            'Email'    => 'admin@testhost',
            'FullName' => 'Admin User',
            'Password' => 'testadmin'
            ),
        'non-admin' => array(
            'User'     => 'non-admin',
            'Email'    => 'non-admin@testhost',
            'FullName' => 'NonAdmin User',
            'Password' => 'testnon-admin'
            )
     );

    protected $p4Params = array();
    protected $uuid;
    protected $p4BaseDir;
    protected $url;

    protected $configParams = array();
    public function __construct(array $parameters = null)
    {
        $this->configParams = $parameters;
    }

    /**
     * @return array  P4d Connection details for p4 super user
     */
    public function getSuperUserConnection()
    {
        return $this->superP4;
    }

    /**
     * @return array  P4d Connection details for p4 admin user
     */
    public function getAdminUserConnection()
    {
        return $this->adminP4;
    }

    /**
     * @return array  P4d Connection details for p4 non-admin user
     */
    public function getNonAdminUserConnection()
    {
        return $this->userP4;
    }

    /**
     * @return string  Unique id generated for each test scenario
     */
    public function getUUID()
    {
        return $this->uuid;
    }

    /**
     * Return a valid P4 user
     *
     * @param   $user     string   P4 user
     * @return  array              Returns a valid P4 user if defined in  $p4users
     * @throws  \Exception         Thrown if $user is not a valid P4 user defined in $p4users
     */
    public function getP4User($user)
    {
        if (!isset($this->p4users[$user])) {
            throw new \Exception("Invalid P4 user: {$user}");
        }
        return $this->p4users[$user];
    }

    /**
     * P4d & Swarm host setup method that gets runs before each Scenario (or Scenario Outline example)
     * A minimum version of p4d can be specified when running a particular scenario
     * All runs of that scenario with p4d version >= min version should pass successfully
     *
     * @param null $minVersion     Minimum version of p4d binary against which the scenario run must pass
     *
     * @Given /^I setup p4d server connection$/
     * @Given /^I setup p4d server connection with minimum version "(?P<version>[^"]*)"$/
     */
    public function setUp($minVersion = null)
    {
        $this->uuid = new Uuid;

        // prefix swarm host with 'pid' of executing test so that it can be easily identified with failure data
        // The UUID is also used as the Swarm license token and hence it can only contain
        // aplha-numeric characters and a hyphen
        $this->uuid = getmypid() . '-' . $this->uuid;
        $this->url  =  $this->configParams['base_url'];

        // prints unique uuid, used to uniquely identify each Scenario, on console for better test debugging
        $this->printDebug("UUID: " . $this->uuid);

        // set up P4d connections and Swarm data path for executing Scenario
        $this->p4BaseDir  = $this->configParams['data_dir'] . '/' . $this->uuid;
        $this->prepareP4Connections($minVersion);
        $this->createP4Connections();
        $this->setupTriggers();

        // ensure we can cleanup properly when done.
        exec('chmod -R a+wr ' . escapeshellarg($this->p4BaseDir));

        // We identify the unique test run by its UUID passed in to apache through the cookie named 'SwarmDataPath'
        $this->setSwarmCookie();
    }

    /**
     * @param   null        $minVersion  Minimum version of p4d binary against which the scenario run must pass
     * @return  string      $p4d         The location of p4d binary under
     * @throws  \Exception               If p4d binary is not found under "BASE_PATH . '/tests/p4-bin"
     */
    public function detectP4d($minVersion = null)
    {
        // capture the p4d version from the behat test run, specified in the config file
        $version = $this->configParams['p4d_version'];

        if (isset($minVersion)) {
            if (floatval($version) < floatval($minVersion)) {
                // If p4d run version less than min. version specified in scenario,
                // then run that particular scenario at the minimum specified version
                $version = $minVersion;
            }
        }

        $p4d = $this->configParams['p4d_dir'] . '/p4d_r' . $version;

        // Copy over p4d version if it does not exist in behat/p4d directory
        // The correct OS is detected for the P4D binary
        if (!is_file($p4d)) {
            if (preg_match('/Darwin/i', PHP_OS)) {
                // 64-bit MacOSX version of p4d
                $file = BASE_PATH . '/tests/p4-bin/bin.darwin90x86_64/p4d_r' . $version;
            } else {
                // 64-bit linux version of p4d
                $file = BASE_PATH . '/tests/p4-bin/bin.linux26x86_64/p4d_r' . $version;
            }

            if (!is_file($file)) {
                throw new \Exception("p4d binary \"$file\" does not exist");
            }
            copy($file, $p4d);
            // Ensure everyone has execute permissions. Setting uid is necessary here because without it
            // tests which attempt to access the server's db files fail with a CommandException as permissions
            // are denied.
            chmod($p4d, 04111);
        }
        return $p4d;
    }

    /**
     * Sets up paths and basic depot infrastructure.
     *
     * @param   null       $minVersion  Minimum version of p4d binary against which the scenario run must pass
     * @throws \Exception               Throws exception if starting config file is not found.
     */
    public function prepareP4Connections($minVersion)
    {
        $p4d = $this->detectP4d($minVersion);

        // ensure failures directory has been created for our p4d log
        @mkdir($this->configParams['failures_dir'] . '/' . $this->uuid, 04777);

        // Prepare connection params and create p4 connection
        // The p4d instance will run against the 'rsh' port and will have its log stored under
        // "failures/<UUID>" directory. This log will be persisted only if scenario fails
        $this->p4Params = array(
            'baseDir'          => $this->p4BaseDir,
            'serverRoot'       => $this->p4BaseDir . '/server',
            'clientRootAdmin'  => $this->p4BaseDir . '/clientAdmin',
            'clientRootSuper'  => $this->p4BaseDir . '/clientSuper',
            'clientsRoot'      => $this->p4BaseDir . '/clients',
            'port'             => 'rsh:' . $p4d . ' -iqr ' . $this->p4BaseDir . '/server'
                 . ' -J off '
                 . '-vtrack=0 -vserver.locks.dir=disabled '
                 . ' -L ' . $this->configParams['failures_dir'] . '/' .$this->uuid . '/p4d_log',
            'client'          => 'test-client',
            'group'           => 'test-group',
            'mail'            =>  $this->p4BaseDir . '/mail'
        );

        $directories  = array(
            $this->p4BaseDir,
            $this->p4Params['serverRoot'],
            $this->p4Params['clientRootAdmin'],
            $this->p4Params['clientRootSuper'],
            $this->p4Params['clientsRoot'],
            $this->p4Params['mail'],
        );

        // Create directories and assign permissions
        // umask() is needed to ensure that created directory gets 'w' permission for group and other users
        $old_mask = umask(0);
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                if ($directory == $this->p4Params['baseDir']) {
                    mkdir($directory, 0777, true);
                } else {
                    // For any sub-directory created under $p4BaseDir (e.g. server/client/clients), set Uid
                    // This way, the user should own all files that get created within those directories
                    mkdir($directory, 0777, true);
                    chmod($directory, 04777);
                }
            }
        }
        umask($old_mask);

        // creating the data/config.php file for swarm admin user
        $this->generateSwarmConfig();
    }

    /*
     * Function needed to setup the data/config.php file for the test. It can also
     * read in a custom config array and replace the default config.php with the custom one
     *
     * @param array|null $config Array containing values needed to setup the config.php file
     */
    protected function generateSwarmConfig($config = null)
    {
        if (is_file($this->p4BaseDir . '/config.php')) {
            // delete the default config.php created for the test
            unlink($this->p4BaseDir . '/config.php');
        }
        // defining the default test config.php array
        $defaultConfig = array(
            'avatars' => array(
                'http_url'  => false,
                'https_url' => false
                ),
            'environment'   => array(
                'hostname'  => $this->configParams['base_url'],
            ),
            'p4' => array(
                'port'      => $this->p4Params['port'],
                'user'      => $this->p4users['admin']['User'],
                'password'  => $this->p4users['admin']['Password'],
            ),
            // default Swarm log levels set to 5
            // Can be set to a max of '7' if a greater debugging need arises
            'log' => array(
                'priority'  => 5
            ),
            // path to mail dir
            'mail' => array(
                'transport' => array(
                    'path'  => $this->p4Params['mail']
                )
            )
        );
        // Construct the merged array needed for config.php if additional Swarm config values are passed in
        // If keys are same, values from the passed in config array would trump the default config
        if (isset($config)) {
            $defaultConfig = array_merge($defaultConfig, $config);
        }
        // Create the config.php file based on above defined array
        file_put_contents($this->p4BaseDir . '/config.php', "<?php\nreturn ");
        file_put_contents($this->p4BaseDir . '/config.php', var_export($defaultConfig, true) . ';', FILE_APPEND);
    }

    /**
     * The apache web user creates files/dirs under data that the user running
     * the test cannot remove without 'sudo' (e.g. log/cache/sessions)
     * Therefore, the tearDown process creates a script accessible to the web
     * user which contains information on the files and paths that the web user
     * has created and instructions to remove them, then then calls the script
     * via a web request, then removes the script.
     * This is done in the tearDown method so this script is not present for
     * the duration of the test, as incorrect invocation could lead to unstable
     * test results.
     *
     * IMP: To persist the data directory for debugging purposes, COMMENT OUT the
     * '@AfterScenario' tag on the line below, else it will be removed after each scenario
     *
     * @AfterScenario
     */
    public function tearDown($event)
    {
        // 1. Setting the p4d log under failures dir with 'set uid' bit,so that user can own it
        // this would allow the user owned php script to delete this log file
        $file = $this->configParams['failures_dir'] . '/' . $this->uuid . '/p4d_log';
        chmod($file, 04707);

        // 2. Deleting the directory containing p4d log under failures dir, if there is no failure
        // in the scenario (e.g. failures/<UUID> where UUID=86227_30142599-48af-3290-608b-8589c14e6ce4)
        // This ensures that failures dir. will contain sub-dirs for failing scenarios only
        if (FeatureMinkContext::$stepFailed == false) {
            @unlink($file);
            @rmdir($this->configParams['failures_dir'] . '/' . $this->uuid);
        }

        // 3. Run the cleanup script to delete the data directory for the particular scenario
        // (e.g. data/<UUID> where  UUID=86227_30142599-48af-3290-608b-8589c14e6ce4)
        // The cleanup script is stored under 'public' dir. and gets run by the web server user
        if (is_dir($this->p4Params['baseDir'])) {
            $cleanupScript = 'cleanup-' . $this->uuid . '.php';
            file_put_contents(
                BASE_PATH . '/public/' . $cleanupScript,
                "<?php\n@exec('rm -rf " . $this->p4Params['baseDir'] . "');\n"
            );

            file_get_contents($this->url . '/' . $cleanupScript);
            // unlink file stored under 'public directory'
            unlink(BASE_PATH . '/public/' . $cleanupScript);

            // If data directory still exists after it is cleaned up by Web service user, then let the script
            // user clean it up ( for files and dirs where web user does not have delete permissions)
            if (is_dir($this->p4Params['baseDir'])) {
                $this->removeDirectory($this->p4Params['baseDir']);
            }
        }
    }

    /**
     * Create a Perforce connection for testing. The perforce connection will
     * connect using a p4d started with the -i (run for inetd) flag.
     *
     * We create a super, admin and non-admin user connection.
     *
     * Also, sets the admin connection as the default connection and $this->p4
     * ensuring the bulk of our work is done with those permissions (to more
     * accurately mirror our suggested deployment configuration).
     *
     * @param   string|null $type allow caller to force the API implementation.
     * @throws \Exception
     */
    public function createP4Connections($type = null)
    {
        $clientsRoot      = $this->p4Params['clientsRoot'];
        $clientRootAdmin  = $this->p4Params['clientRootAdmin'];
        $clientRootSuper  = $this->p4Params['clientRootSuper'];
        $serverRoot       = $this->p4Params['serverRoot'];
        $port             = $this->p4Params['port'];
        $client           = $this->p4Params['client'];

        if (!is_dir($serverRoot)) {
            throw new \Exception('Unable to create new server.');
        }

        // create super user connection.
        $p4 = Connection::factory(
            $port,
            $this->p4users['super']['User'],
            $client,
            $this->p4users['super']['Password'],
            null,
            $type
        );

        // set server into Unicode mode if a charset was set (or set to something other than 'none')
        if (USE_UNICODE_P4D) {
            exec(P4D_BINARY . ' -xi -r ' . $serverRoot, $output, $status);

            if ($status != 0) {
                die("error (" . $status . "): problem setting server into Unicode mode:\n" . $output);
            }
        }

        // give the connection a client manager
        $clients = new ClientPool($p4);
        $clients->setMax(10)->setRoot($clientsRoot)->setPrefix(getmypid() . '-');
        $p4->setService('clients', $clients);

        // create P4 super user.
        $p4->run('user', '-i', $this->p4users['super']);
        $p4->run('login', array(), $this->p4users['super']['Password']);

        // establish protections.
        // A newly instantiated P4 server considers the first user to invoke
        // 'p4 protects' to be a superuser. The below operations make only the
        // this user a superuser, and subsequent users will be 'normal' users.
        $result  = $p4->run('protect', '-o');
        $protect = $result->getData(0);
        $p4->run('protect', '-i', $protect);

        // create client
        $clientForm = array(
            'Client'    => $client,
            'Owner'     => $this->p4users['super']['User'],
            'Root'      => $clientRootSuper,
            'View'      => array('//depot/... //' . $client . '/...')
        );
        $p4->run('client', '-i', $clientForm);

        // pull out the parent created super connection for later use
        $superP4 = $this->superP4 = $p4;

        // create the admin user and store its connection
        $adminP4 = $this->p4 = Connection::factory(
            $port,
            $this->p4users['admin']['User'],
            null,
            $this->p4users['admin']['Password']
        );
        $adminP4->run('user', '-i', $this->p4users['admin']);

        // add 'admin' protections for our new admin user
        $protections = Protections::fetch($this->superP4);
        $protections->addProtection('admin', 'user', $this->p4users['admin']['User'], '*', '//...');
        $protections->save();

        // clear the client from the super p4 and set a client for the admin user
        $superP4->setClient(null);
        $clientForm = array(
            'Client'    => $client,
            'Owner'     => $this->p4users['admin']['User'],
            'Root'      => $clientRootAdmin,
            'View'      => array('//depot/... //' . $client . '/...')
        );
        $superP4->run('client', array('-d', $client));
        $adminP4->run('client', '-i', $clientForm);
        $adminP4->setClient($client);
        Connection::setDefaultConnection($adminP4);

        // lastly create the non-admin standard user account
        $userP4 = Connection::factory(
            $port,
            $this->p4users['non-admin']['User'],
            null,
            ''
        );

        // actually create the regular user
        $userP4->run('user', '-i', $this->p4users['non-admin']);

        $this->userP4  = $userP4;
        $this->adminP4 = $adminP4;
    }

    /**
     * Sets trigger token by creating file in data/queue/tokens/
     * Copies default script to /public folder and alters it to include
     * the token.
     * Sets up the perforce triggers using p4php, referencing the tests's
     * trigger script.
     *
     * @throws \Exception            Throws Exception if the default trigger script
     *                              for these tests was not found in the base path.
     */
    public function setupTriggers()
    {
        // add swarm triggers using the superuser's p4 connection
        // 0-length file, name is token
        mkdir($this->p4BaseDir . '/queue');
        mkdir($this->p4BaseDir . '/queue/tokens');
        // Creating the Swarm token file with a Swarm license value same as UUID of the test
        file_put_contents($this->p4BaseDir . '/queue/tokens/'. $this->uuid, null);

        // Copy and update trigger script to contain reference to this test's token.
        $script = file_get_contents(BASE_PATH . '/p4-bin/scripts/swarm-trigger.sh');
        if ($script === false) {
            throw new \Exception("Could not read default Swarm trigger script.");
        }

        // Modifying the default trigger script to set the Swarm Host and Token
        // We also modify the wget and curl commands so that the trigger script is made aware of the unique
        // Swarm Data Path for each scenario ( created under the behat/data directory)
        // This is set through the 'SwarmDataPath' cookie  generated by the test scenario
        $scriptArray = explode("\n", $script);
        $setHost     = false;
        $setToken    = false;
        $setWget     = false;
        $setCurl     = false;
        foreach ($scriptArray as $index => $line) {
            if (substr(trim($line), 0, 10) == 'SWARM_HOST') {
                $scriptArray[$index] = "SWARM_HOST=\"$this->url\"";
                $setHost             = true;
            } elseif (substr(trim($line), 0, 11) == 'SWARM_TOKEN') {
                $scriptArray[$index] = "SWARM_TOKEN=\"$this->uuid\"";
                $setToken            = true;
            } elseif (substr(trim($line), 0, 12) == 'wget --quiet') {
                $command             = $scriptArray[$index];
                $command             = preg_replace(
                    '/wget --quiet/',
                    'wget --quiet --header="Cookie: SwarmDataPath=${SWARM_TOKEN}"',
                    $command
                );
                $scriptArray[$index] = $command;
                $setWget             = true;
            } elseif (substr(trim($line), 0, 13) == 'curl --silent') {
                $command            = $scriptArray[$index];
                $command            = preg_replace(
                    '/curl --silent/',
                    'curl --silent --cookie "SwarmDataPath=${SWARM_TOKEN}"',
                    $command
                );
                $scriptArray[$index] = $command;
                $setCurl             = true;
            }
            if ($setHost && $setToken && $setWget && $setCurl) {
                break;  // exit foreach loop
            }
        }

        $triggerScript = $this->p4BaseDir . '/script-triggers.sh';
        file_put_contents($triggerScript, implode("\n", $scriptArray));
        exec('chmod a+x ' . $triggerScript);

        // Executing the trigger script to get the list of default Swarm triggers
        // Testing with any additional triggers would require the triggers to be defined in the test setup
        exec("$triggerScript -o", $triggers);
        $result   = array_map('trim', $triggers);

        // set triggers in perforce
        $triggers = Triggers::fetch($this->superP4);
        $triggers->setTriggers($result)->save();

        // force a reconnect as triggers seem to require it
        $triggers->getConnection()->disconnect();
    }

    /**
     * Function to instantiate workers to process activities within the queue
     */
    public function instantiateWorker()
    {
        $uuid = $this->getP4Context()->getUUID();

        // Create header context with the SwarmDataPath cookie
        $opts = array(
            'http' => array(
                'method' => "GET",
                'header' => "Cookie: SwarmDataPath=$uuid\r\n"
            )
        );
        $context = stream_context_create($opts);

        // Instantiating & retiring a worker to process the tasks in swarm queue
        file_get_contents($this->configParams['base_url'] . '/queue/worker?retire=1', false, $context);
        file_get_contents($this->configParams['base_url'] . '/queue/worker?retire=2', false, $context);
        file_get_contents($this->configParams['base_url'] . '/queue/worker?retire=3', false, $context);
    }

    /**
     * Use the super-user connection to create a non-admin user with the given username
     *
     * @param string  $username
     */
    public function createRegularUser($username)
    {
        $userConfiguration = array(
            "User"     => "$username",
            "Email"    => "$username@testhost",
            "FullName" => "$username",
            "Password" => "$username"
        );

        $superP4 = $this->superP4;
        $superP4->run('user', array('-f', '-i'), $userConfiguration);
    }
}
