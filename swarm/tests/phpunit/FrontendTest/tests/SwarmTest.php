<?php

namespace Tests;

use P4;
use P4\ClientPool\ClientPool;
use P4\Connection\Connection;
use P4\Uuid\Uuid;

class SwarmTest extends \Sauce\Sausage\WebDriverTestCase
{
    public $tags = array();
    public $protocol = 'http';

    public $p4users = array(
        'super' => array(
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
        'vera' => array(
            'User'     => 'vera',
            'Email'    => 'vera@testhost',
            'FullName' => 'Vera User',
            'Password' => 'testvera'
        )
    );

    /*
     * Swarm supported browsers:
     * Apple Safari 6+
     * Google Chrome 25+ (stable channel)
     * Microsoft Internet Explorer 10+
     * Mozilla Firefox 19+
     */
    public static $browsers = array(
        // OS X tests disabled because they're billed separately
        // @todo - uncomment when billing package is established
//        array(
//            'browserName' => 'safari',
//            'desiredCapabilities' => array(
//                'version'  => '6',
//                'platform' => 'OS X 10.8',
//            )
//        ),
//        array(
//            'browserName' => 'safari',
//            'desiredCapabilities' => array(
//                'version'  => '5',
//                'platform' => 'OS X 10.6',
//            )
//        ),
//        array(
//            'browserName' => 'firefox',
//            'desiredCapabilities' => array(
//                'version'  => '21',
//                'platform' => 'OS X 10.6',
//            )
//        ),
//        array(
//            'browserName' => 'chrome',
//            'desiredCapabilities' => array(
//                'platform' => 'OS X 10.8',
//                'version'  => ''
//          )
//        ),
        array(
            'browserName' => 'internet explorer',
            'desiredCapabilities' => array(
                'version'  => '10',
                'platform' => 'Windows 8',
            )
        ),
        array(
            'browserName' => 'firefox',
            'desiredCapabilities' => array(
                'version'  => '19',
                'platform' => 'Windows 7',
            )
        ),
        array(
            'browserName' => 'chrome',
            'desiredCapabilities' => array(
                'platform' => 'Windows 7',
                'version'  => ''
            )
        ),
        array(
            'browserName' => 'firefox',
            'desiredCapabilities' => array(
                'version'  => '21',
                'platform' => 'Linux',
            )
        ),
        array(
            'browserName' => 'chrome',
            'desiredCapabilities' => array(
                'platform' => 'Linux',
                'version'  => ''
          )
        )
        // run Chrome locally, not used in automated testing, but useful for
        //  debugging
//        array(
//            'browserName' => 'chrome',
//            'local' => true,
//            'sessionStrategy' => 'shared'
//        )
    );

    /**
     * setUp method runs before each test
     */
    public function setUp()
    {
        // these capabilities are passed to sauce labs
        $this->setDesiredCapabilities(
            array_merge(
                array(
                    'tags'              => $this->tags,
                    // enable this if using the parent tunnel with a subaccount
                    // by default, the jenkins build uses the swarmqa main
                    // account, so this option is not needed
                    // 'parent-tunnel'     => 'swarmqa'
                ),
                $this->getDesiredCapabilities()
            )
        );

        // Currenly per test
        // @todo: investigate moving to bootstrap so is used per test suite rather
        // than per test
        $this->uuid = new Uuid;

        // prefix with test- for easier manual deletion
        $this->uuid       = 'test-' . $this->uuid;
        $this->url        = $this->protocol . '://' . $this->uuid . "." . SWARM_TEST_HOST . '/';
        $this->setBrowserUrl($this->url);

        // set up connections and data path
        $this->p4BaseDir  = BASE_PATH . '/data/' . $this->uuid;
        $this->prepareP4Connections();
        $this->createP4Connections();
        $this->setupTriggers();

        // ensure we can cleanup properly when done.
        exec('chmod -R a+wr ' . $this->p4BaseDir);

        parent::setUp();
    }

    /**
     * Sets up paths and basic depot infrastructure.
     *
     * @return \Tests\SwarmTest     Returns $this for chaining.
     * @throws Exception            Throws exception if starting config file is not found.
     */
    public function prepareP4Connections() {
        // prepare connection params and create p4 connection
        $this->p4Params = array(
            'baseDir'     => $this->p4BaseDir,
            'serverRoot'  => $this->p4BaseDir . '/server',
            'clientRoot'  => $this->p4BaseDir . '/client',
            'clientsRoot' => $this->p4BaseDir . '/clients',
            'port'        => 'rsh:' . P4D_BINARY . ' -iqr ' . $this->p4BaseDir . '/server'
                           . ' -J off ' . '-vtrack=0 -vserver.locks.dir=disabled',
            'client'      => 'test-client',
            'group'       => 'test-group',
        );

        mkdir($this->p4BaseDir);
        mkdir($this->p4Params['serverRoot']);
        mkdir($this->p4Params['clientRoot']);
        mkdir($this->p4Params['clientsRoot']);

        // Copy and update config.php to contain reference to this test's
        // p4port.  Set config user to admin user.
        $config = file_get_contents(BASE_PATH . '/data/config.php');
        if ($config === false) {
            throw new Exception("Could not read default Swarm config file.");
        }

        $configArray = explode("\n", $config);

        foreach ($configArray as $index => $line) {
            if (substr(trim($line), 0, 6) == "'port'") {
                $configArray[$index] = "'port' => '" . $this->p4Params['port'] . "',";
            }
            else if (substr(trim($line), 0, 6) == "'user'") {
                $configArray[$index] = "'user' => '" . $this->p4users['admin']['User'] . "',";
            }
            else if (substr(trim($line), 0, 10) == "'password'") {
                $configArray[$index] = "'password' => '" . $this->p4users['admin']['Password'] . "',";
            }
        }

        file_put_contents($this->p4BaseDir . '/config.php', implode("\n", $configArray));

        return $this;
    }

    /**
     * Because the web user creates paths and data that the user running
     * the test cannot remove without sudo, cleanup is tricky.
     * Running the test under sudo is not a proper way to resolve this, and may
     * have security implications.
     * When these tests are running in an automated fashion, the user running
     * the tests may not have sudo access to run individual commands.
     *
     * Therefore, the tearDown process creates a script accessible to the web
     * user which contains information on the files and paths that the web user
     * has created and instructions to remove them, then then calls the script
     * via a web request, then removes the script.
     *
     * This is done in the tearDown method so this script is not present for
     * the duration of the test, as incorrect invocation could lead to unstable
     * test results.
     */
    public function tearDown()
    {
        $cleanupScript = 'cleanup-' . $this->uuid . '.php';
        file_put_contents(
            BASE_PATH . '/public/' . $cleanupScript,
            "<?php\n@exec('rm -rf " . $this->p4Params['baseDir'] . "');\n"
        );

        $response = file_get_contents($this->url . $cleanupScript);
        unlink(BASE_PATH . '/public/' . $cleanupScript);
        if ($response === false) {
            echo "Unable to remove test directory.  Please remove " . $this->p4Params['baseDir'] . " manually.";
        } else {
            // delete test directory, if the web user was able to clean up its stuff
            // otherwise don't remove and leave for manual cleanup
            $this->unlinkRecursive($this->p4Params['baseDir'], true);
        }

        parent::tearDown();
    }

    /**
     * Recursively delete a directory, used by tearDown.
     *
     * @param string $dir Directory name
     * @param boolean $deleteRootToo Delete specified top-level directory as well
     */
    public function unlinkRecursive($dir, $deleteRootToo = false)
    {
        if(!$dh = @opendir($dir)) {
            return;
        }
        while (false !== ($obj = readdir($dh))) {
            if($obj == '.' || $obj == '..') {
                continue;
            }

            if (!@unlink($dir . '/' . $obj)) {
                $this->unlinkRecursive($dir.'/'.$obj, true);
            }
        }

        closedir($dh);

        if ($deleteRootToo) {
            @rmdir($dir);
        }

        return;
    }

    /**
     * Similar to the parent class's method by(), and other methods of fetching
     * elements, this method fetches all matching elements, rather than a
     * single element.
     *
     * @param string $strategy      supported by JsonWireProtocol element/ command
     * @param string $value         The value to search for.
     * @return array(PHPUnit_Extensions_Selenium2TestCase_Element)  A list of elements found using the strategy and value.
     */
    public function fetchElementsBy($strategy, $value)
    {
        return $this->elements($this->using($strategy)->value($value));
    }

    /**
     * Create a Perforce connection for testing. The perforce connection will
     * connect using a p4d started with the -i (run for inetd) flag.
     *
     * We create a super, admin and plain user connection.
     *
     * Also, sets the admin connection as the default connection and $this->p4
     * ensuring the bulk of our work is done with those permissions (to more
     * accurately mirror our suggested deployment configuration).
     *
     * @param   string|null     $type   allow caller to force the API
     *                                  implementation.
     */
    public function createP4Connections($type = null)
    {
       extract($this->p4Params);

        if (!is_dir($serverRoot)) {
            throw new P4\Exception('Unable to create new server.');
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
        $clients->setMax(10)->setRoot($clientsRoot)->setPrefix('test-');
        $p4->setService('clients', $clients);

        // create super user.
        $p4->run('user', '-i', $this->p4users['super']);
        $p4->run('login', array(), $this->p4users['super']['Password']);

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
            'Owner'     => $this->p4users['super']['User'],
            'Root'      => $clientRoot . '/superuser',
            'View'      => array('//depot/... //' . $client . '/...')
        );
        $p4->run('client', '-i', $clientForm);

        // pull out the parent created super connection for later use
        $superP4 = $this->superP4 = $p4;

        // create the admin user and store its connection
        $adminP4 = $this->p4 = \P4\Connection\Connection::factory(
            $port,
            $this->p4users['admin']['User'],
            null,
            $this->p4users['admin']['Password']
        );
        $adminP4->run('user', '-i', $this->p4users['admin']);

        // add 'admin' protections for our new admin user
        $protections = P4\Spec\Protections::fetch($this->superP4);
        $protections->addProtection('admin', 'user', $this->p4users['admin']['User'], '*', '//...');
        $protections->save();

        // clear the client from the super p4 and set a client on the new admin $this->p4
        $superP4->setClient(null);
        $clientForm = array(
            'Client'    => $this->p4Params['client'],
            'Owner'     => $this->p4users['admin']['User'],
            'Root'      => $this->p4Params['clientRoot'] . '/adminuser',
            'View'      => array('//depot/... //' . $this->p4Params['client'] . '/...')
        );
        $superP4->run('client', array('-d', $this->p4Params['client']));
        $adminP4->run('client', '-i', $clientForm);
        $adminP4->setClient($this->p4Params['client']);
        P4\Connection\Connection::setDefaultConnection($adminP4);

        // lastly create the nonadmin standard user account
        $userP4 = \P4\Connection\Connection::factory(
            $port,
            $this->p4users['vera']['User'],
            null,
            ''
        );

        // actually create the regular user
        $userP4->run('user', '-i', $this->p4users['vera']);

        $this->userP4 = $userP4;
    }

    /**
     * Sets trigger token by creating file in data/queue/tokens/
     * Copies default script to /public folder and alters it to include
     * the token.
     * Sets up the perforce triggers using p4php, referencing the tests's
     * trigger script.
     *
     * @return \Tests\SwarmTest     For chaining.
     * @throws Exception            Throws Exception if the default trigger script
     *                              for these tests was not found in the base path.
     */
    public function setupTriggers() {
        // add swarm triggers using the superuser's p4 connection
        // 0-length file, name is token
        mkdir($this->p4BaseDir . '/queue');
        mkdir($this->p4BaseDir . '/queue/tokens');
        file_put_contents($this->p4BaseDir . '/queue/tokens/'. $this->uuid, null);

        // Copy and update trigger script to contain reference to this test's
        // token.
        $script = file_get_contents(BASE_PATH . '/p4-bin/scripts/swarm-trigger.sh');
        if ($script === false) {
            throw new Exception("Could not read default Swarm trigger script.");
        }

        // this script may be modified to move these settings to a different
        // line number, so parse the file until they're both set, then stop
        $scriptArray = explode("\n", $script);
        $setHost  = false;
        $setToken = false;
        foreach ($scriptArray as $index => $line) {
            if (substr(trim($line), 0, 10) == "SWARM_HOST") {
                $scriptArray[$index] = "SWARM_HOST=\"$this->url\"";
                $setHost = true;
            }
            else if (substr(trim($line), 0, 11) == "SWARM_TOKEN") {
                $scriptArray[$index] = "SWARM_TOKEN=\"$this->uuid\"";
                $setToken = true;
            }
            if ($setHost && $setToken) {
                break;  // exit foreach loop
            }
        }

        $triggerScript = $this->p4BaseDir . '/script-triggers.sh';
        file_put_contents($triggerScript, implode("\n", $scriptArray));
        exec('chmod a+x ' . $triggerScript);

        // set triggers in perforce
        $triggers      = P4\Spec\Triggers::fetch($this->superP4);
        // add %quotes% for use with triggers
        $triggerScript = '%quote%' . $triggerScript . '%quote%';
        $lines         = array(
            'swarm.job      form-commit   job    "' . $triggerScript . ' -t job      -v %formname%"',
            'swarm.user     form-commit   user   "' . $triggerScript . ' -t user     -v %formname%"',
            'swarm.userdel  form-delete   user   "' . $triggerScript . ' -t userdel  -v %formname%"',
            'swarm.group    form-commit   group  "' . $triggerScript . ' -t group    -v %formname%"',
            'swarm.groupdel form-delete   group  "' . $triggerScript . ' -t groupdel -v %formname%"',
            'swarm.change   form-commit   change "' . $triggerScript . ' -t change   -v %formname%"',
            'swarm.shelve   shelve-commit //...  "' . $triggerScript . ' -t shelve   -v %change%"',
            'swarm.commit   change-commit //...  "' . $triggerScript . ' -t commit   -v %change%"'
        );

        $triggers->setTriggers($lines)->save();

        // force a reconnect as triggers seem to require it
        $triggers->getConnection()->disconnect();

        return $this;
    }
}