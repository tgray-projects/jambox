<?php
/**
 * Base class for all controller test cases.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ModuleTest;

use P4\Connection\ConnectionInterface;
use P4\Spec\Protections;
use P4Test\TestCase;
use Zend\Dom\Query as DomQuery;
use Zend\Mvc\ResponseSender\SendResponseEvent;
use Zend\Mvc\Service\ServiceManagerConfig;
use Zend\ServiceManager\ServiceManager;
use Zend\Stdlib\Parameters;

class TestControllerCase extends TestCase
{
    protected $application;
    protected $configuration;
    protected $superP4;
    protected $userP4;

    /**
     * Extends parent by setting up the application and initializing the site.
     */
    public function setUp()
    {
        // run parent to set up perforce connection and prepare directories
        parent::setUp();

        // disable console to behave like we communicate over http
        \Zend\Console\Console::overrideIsConsole(false);

        // initialize the application
        $this->initApplication();
    }

    /**
     * Set application config file. Useful for testing of this class.
     *
     * @param   string  $configuration  path to the application config file
     */
    public function setConfiguration($configuration)
    {
        $this->configuration = (string) $configuration;
    }

    /**
     * Return application configuration.
     *
     * @return  array   application configuration
     */
    public function getConfiguration()
    {
        $config = $this->configuration ?: array(
            'modules' => array_map(
                'basename',
                array_map('dirname',  glob(BASE_PATH . '/module/*/Module.php'))
            ),
            'module_listener_options' => array(
                'module_paths' => array(BASE_PATH . '/module')
            ),
        );

        return is_string($config) ? include $config : $config;
    }

    /**
     * Return the mvc application instance. It will also initialize
     * the application if it was not done before.
     *
     * @return  \Zend\Mvc\Application  application instance
     */
    public function getApplication()
    {
        if (!$this->application) {
            $this->initApplication();
        }

        return $this->application;
    }

    /**
     * Reset the application.
     */
    public function resetApplication()
    {
        $this->application = null;
    }

    /**
     * Dispatch to a given url.
     *
     * @param   string  $url        url to dispatch to
     * @param   bool    $autoCsrf   optional - by default auto includes CSRF token as needed
     * @return  string  raw output likely the same as getResponse()->getContent()
     */
    public function dispatch($url, $autoCsrf = true)
    {
        // set url on the request and run the application
        $request = $this->getRequest();
        $request->setUri($url);

        // handle query parameters (if any)
        $uriQuery = $request->getUri()->getQueryAsArray();
        if ($request->getQuery()->count() == 0 && $uriQuery) {
            $request->setQuery(new Parameters($uriQuery));
        }

        // if autoCsrf is enabled and this isn't a get; include the correct token
        if ($autoCsrf && !$request->isGet()) {
            $request->getPost()->set('_csrf', $this->application->getServiceManager()->get('csrf')->getToken());
        }

        // run the application and capture the response in the output buffer
        ob_start();
        $this->getApplication()->run();
        return ob_get_clean();
    }

    /**
     * Get the request instance.
     *
     * @return  \Zend\Stdlib\RequestInterface   request instance
     */
    public function getRequest()
    {
        return $this->getApplication()->getRequest();
    }

    /**
     * Get the application response.
     *
     * @return  \Zend\Stdlib\ResponseInterface  mvc-event response
     */
    public function getResponse()
    {
        return $this->getApplication()->getResponse();
    }

    /**
     * Get the application result (usualy what is returned by the controller).
     *
     * @return  mixed   mvc-event result
     */
    public function getResult()
    {
        $event = $this->getApplication()->getMvcEvent();
        return $event->getResult();
    }

    /**
     * Evaluate if the given module was dispatched.
     *
     * @param   string  $moduleName     name of the module to check
     * @param   string  $message        optional message
     * @throws  \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertModule($moduleName, $message = '')
    {
        $this->addToAssertionCount(1);

        $controllerClass   = $this->getMatchedControllerClass();
        $matchedModule     = current(explode('\\', $controllerClass));
        $moduleName        = strtolower($moduleName);
        $matchedModule     = strtolower($matchedModule);
        if ($moduleName !== $matchedModule) {
            $this->fail(
                sprintf(
                    "Failed asserting module name was '%s', actual module is '%s'\n%s",
                    $moduleName,
                    $matchedModule,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate if the given controller was dispatched.
     *
     * @param   string  $controllerClass    name of the controller class to check
     * @param   string  $message            optional message
     * @throws  \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertController($controllerClass, $message = '')
    {
        $this->addToAssertionCount(1);

        $controllerClass   = strtolower($controllerClass);
        $matchedController = strtolower($this->getMatchedControllerClass());
        if ($controllerClass !== $matchedController) {
            $this->fail(
                sprintf(
                    "Failed asserting controller class was '%s', actual controller is '%s'\n%s",
                    $controllerClass,
                    $matchedController,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate if the given action was dispatched.
     *
     * @param   string  $actionName     name of the action to check
     * @param   string  $message        optional message
     * @throws  \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertAction($actionName, $message = '')
    {
        $this->addToAssertionCount(1);

        $routeMatch    = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $actionName    = strtolower($actionName);
        $matchedAction = strtolower($routeMatch->getParam('action'));
        if ($actionName !== $matchedAction) {
            $this->fail(
                sprintf(
                    "Failed asserting action was '%s', actual action is '%s'\n%s",
                    $actionName,
                    $matchedAction,
                    $message
                )
            );
        }
    }

    /**
     * Convenient function for evaluating what module & controller & action were
     * matched by the router in one method call.
     *
     * @param   string  $moduleName         name of the module to check
     * @param   string  $controllerClass    name of the controller class to check
     * @param   string  $actionName         name of the action to check
     * @param   string  $message            optional message
     */
    public function assertRouteMatch($moduleName, $controllerClass, $actionName, $message = '')
    {
        $this->assertModule($moduleName, $message);
        $this->assertController($controllerClass, $message);
        $this->assertAction($actionName, $message);
    }

    /**
     * Evaluate if the given route was used.
     *
     * @param  string   $routeName  route name to check for
     * @param  string   $message    optional message
     */
    public function assertRoute($routeName, $message = '')
    {
        $this->addToAssertionCount(1);

        $routeMatch       = $this->getApplication()->getMvcEvent()->getRouteMatch();
        $routeName        = strtolower($routeName);
        $matchedRouteName = strtolower($routeMatch->getMatchedRouteName());
        if ($routeName !== $matchedRouteName) {
            $this->fail(
                sprintf(
                    "Failed asserting matched route was '%s', actual route is '%s'\n%s",
                    $routeName,
                    $matchedRouteName,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate if the status code from the response matches the given value.
     *
     * @param   int     $statusCode     status code to check
     * @param   string  $message        optional message
     * @throws \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertResponseStatusCode($statusCode, $message = '')
    {
        $this->addToAssertionCount(1);

        $matchedCode = $this->getResponse()->getStatusCode();
        if ($statusCode !== $matchedCode) {
            $this->fail(
                sprintf(
                    "Failed asserting response code is %d, actual value is %d\n%s",
                    $statusCode,
                    $matchedCode,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate the path to verify that it exists in the response body.
     *
     * @param   string  $path   path to check
     * @throws  \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertQuery($path, $message = '')
    {
        $this->addToAssertionCount(1);

        $match = $this->query($path);
        if (count($match) <= 0) {
            $this->fail(
                sprintf(
                    "Failed asserting node denoted by %s exists\n%s",
                    $path,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate the path to verify that it doesn't exist in the response body.
     *
     * @param   string  $path   path to check
     * @throws  \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertNotQuery($path, $message = '')
    {
        $this->addToAssertionCount(1);

        $match = $this->query($path);
        if (count($match) > 0) {
            $this->fail(
                sprintf(
                    "Failed asserting node denoted by %s does not exist\n%s",
                    $path,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate the path to verify that it occurs in the response body exactly
     * the given numer of times.
     *
     * @param   string  $path   path to check
     * @throws  \PHPUnit_Framework_ExpectationFailedException
     */
    public function assertQueryCount($path, $count, $message = '')
    {
        $this->addToAssertionCount(1);

        $match = $this->query($path);
        if (count($match) !== $count) {
            $this->fail(
                sprintf(
                    "Failed asserting node denoted by %s occurs exactly %d times\n%s",
                    $path,
                    $count,
                    $message
                )
            );
        }
    }

    /**
     * Evaluate the dom node specified by the path to verify that it contains the given content.
     * @param   string  $path       dom path to check
     * @param   string  $content    content to check for dom node value
     * @param   string  $message    optional message
     */
    public function assertQueryContentContains($path, $content, $message = '')
    {
        $this->addToAssertionCount(1);

        $nodeList = $this->query($path);
        $found    = false;

        $nodeList->rewind();
        while (!$found && $nodeList->valid()) {
            $found = strpos($nodeList->current()->nodeValue, $content) !== false;
            $nodeList->next();
        }

        if (!$found) {
            $this->fail(
                sprintf(
                    "Failed asserting node denoted by %s contains %s\n%s",
                    $path,
                    $content,
                    $message
                )
            );
        }
    }

    /**
     * Perform a CSS selector query. Return number of occurences the path
     * exists in the response body.
     *
     * @param   string  $path       path to check for
     * @return  \Zend\Dom\NodeList  list of dom nodes with the given path
     */
    protected function query($path)
    {
        $dom = new DomQuery($this->getResponse()->getBody());
        return $dom->execute($path);
    }

    /**
     * Return class name of the controller matched during the route event.
     *
     * @return  string  matched controller class name
     */
    protected function getMatchedControllerClass()
    {
        $application       = $this->getApplication();
        $routeMatch        = $application->getMvcEvent()->getRouteMatch();
        $controller        = $routeMatch->getParam('controller');
        $controllerManager = $application->getServiceManager()->get('ControllerLoader');
        return get_class($controllerManager->get($controller));
    }

    /**
     * Initialize the application - set the ServiceManager, load modules and
     * bootstrap it. It then takes the aggregated application/modules config,
     * substitutes values for testing and sets it back to the ServiceManager.
     * Also marks the request object to denote the testing environment.
     */
    protected function initApplication()
    {
        // load modules with default application configuration
        $configuration  = $this->getConfiguration();
        $serviceManager = new ServiceManager(new ServiceManagerConfig());
        $serviceManager->setService('ApplicationConfig', $configuration);
        $serviceManager->get('ModuleManager')->loadModules();

        // configure service manager for testing
        $this->configureServiceManager($serviceManager);

        // mark request to denote we are in testing environment
        $application     = $serviceManager->get('Application');
        $request         = $application->getRequest();
        $request->isTest = true;

        // mark response event to denote we are testing
        $responseListener = $serviceManager->get('SendResponseListener')->getEventManager();
        $responseListener->attach(
            SendResponseEvent::EVENT_SEND_RESPONSE,
            function ($event) {
                $event->setParam('isTest', true);
            }
        );

        // bootstrap application
        $application = $serviceManager->get('Application');
        $application->bootstrap();

        $this->application = $application;
    }

    /**
     * Create a Perforce connection for testing. The perforce connection will
     * connect using a p4d started with the -i (run for inetd) flag.
     *
     * Extends parent to ensure we create a super, admin and plain user connection
     * (parent only makes super).
     *
     * Also, sets the admin connection as the default connection and $this->p4
     * ensuring the bulk of our work is done with those permissions (to more
     * accurately mirror our suggested deployment configuration).
     *
     * @param   string|null     $type   allow caller to force the API
     *                                  implementation.
     * @return  P4\Connection\ConnectionInterface   a Perforce API implementation
     */
    public function createP4Connection($type = null)
    {
        // let parent take care of the super user connection creation super
        // user will have the id defined by p4Params, tester by default
        parent::createP4Connection($type);

        // pull out the parent created super connection for later use
        $superP4 = $this->superP4 = $this->p4;

        // create the admin user and store its connection
        $adminP4 = $this->p4 = \P4\Connection\Connection::factory(
            $this->getP4Params('port'),
            'admin',
            null,
            ''
        );
        $userForm = array(
            'User'     => 'admin',
            'Email'    => 'admin@testhost',
            'FullName' => 'Admin User',
            'Password' => ''
        );
        $adminP4->run('user', '-i', $userForm);

        // add 'admin' protections for our new admin user
        $protections = Protections::fetch($this->superP4);
        $protections->addProtection('admin', 'user', 'admin', '*', '//...');
        $protections->save();

        // clear the client from the super p4 and set a client on the new admin $this->p4
        $superP4->setClient(null);
        $clientForm = array(
            'Client'    => $this->p4Params['client'],
            'Owner'     => 'admin',
            'Root'      => $this->p4Params['clientRoot'] . '/adminuser',
            'View'      => array('//depot/... //' . $this->p4Params['client'] . '/...')
        );
        $superP4->run('client', array('-d', $this->p4Params['client']));
        $adminP4->run('client', '-i', $clientForm);
        $adminP4->setClient($this->p4Params['client']);
        \P4\Connection\Connection::setDefaultConnection($adminP4);

        // lastly create the nonadmin standard user account
        $userP4 = \P4\Connection\Connection::factory(
            $this->getP4Params('port'),
            'nonadmin',
            null,
            ''
        );

        // actually create the user
        $userForm = array(
            'User'     => 'nonadmin',
            'Email'    => 'nonadmin@testhost',
            'FullName' => 'Test User',
            'Password' => ''
        );
        $userP4->run('user', '-i', $userForm);

        $this->userP4 = $userP4;
    }

    /**
     * Extend parrent to pass the super user connection as $this->p4 now refers to admin connection.
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
        return parent::connectWithAccess($user, $paths, $p4Super ?: $this->superP4);
    }

    /**
     * Configure service manager for testing environment.
     *
     * @param   ServiceManager  $serviceManager     service manager instance
     */
    protected function configureServiceManager(ServiceManager $serviceManager)
    {
        // allow overriding
        $allowOverride = $serviceManager->getAllowOverride();
        $serviceManager->setAllowOverride(true);

        // set p4 factory to return p4 connection for testing
        if (!$this->p4 instanceof \P4\Connection\ConnectionInterface
            || !$this->userP4 instanceof \P4\Connection\ConnectionInterface
            || !$this->superP4 instanceof \P4\Connection\ConnectionInterface
        ) {
            $this->createP4Connection();

            // now that we have a non-admin user, ensure only admins can
            // access keys to allow us to actually exercise things.
            // this is only supported on 13.1+ servers so we do it selectively.
            if ($this->superP4->isServerMinVersion('2013.1')) {
                $this->superP4->run('configure', array('set', 'dm.keys.hide=1'));
            }
        }

        // (re)create the cache, client pool, and translator services for the admin account
        $adminP4 = $this->p4;
        $adminP4->setService(
            'cache',
            function ($p4) {
                $cache = new \Record\Cache\Cache($p4);
                $cache->setCacheDir(DATA_PATH . '/cache');
                return $cache;
            }
        );
        $adminP4->setService(
            'clients',
            function ($p4) {
                $clients = new \P4\ClientPool\ClientPool($p4);
                $clients->setMax(10)->setRoot(DATA_PATH . '/clients')->setPrefix('test-');
                return $clients;
            }
        );
        $adminP4->setService(
            'translator',
            function ($p4) use ($serviceManager) {
                return $serviceManager->get('translator');
            }
        );

        // setup the super connection to use the same cache and translator and have a client pool
        $superP4 = $this->superP4;
        $superP4->setService(
            'cache',
            function () use ($adminP4) {
                return $adminP4->getService('cache');
            }
        );
        $superP4->setService(
            'clients',
            function ($p4) {
                $clients = new \P4\ClientPool\ClientPool($p4);
                $clients->setMax(10)->setRoot(DATA_PATH . '/clients')->setPrefix('test-');
                return $clients;
            }
        );
        $superP4->setService(
            'translator',
            function () use ($adminP4) {
                return $adminP4->getService('translator');
            }
        );

        // (re)create the client pool and borrow the admin account's cache and translator services for the user account
        $userP4 = $this->userP4;
        $userP4->setService(
            'clients',
            function ($p4) {
                $clients = new \P4\ClientPool\ClientPool($p4);
                $clients->setMax(10)->setRoot(DATA_PATH . '/clients')->setPrefix('test-');
                return $clients;
            }
        );
        $userP4->setService(
            'cache',
            function () use ($adminP4) {
                return $adminP4->getService('cache');
            }
        );
        $userP4->setService(
            'translator',
            function () use ($adminP4) {
                return $adminP4->getService('translator');
            }
        );

        // configure the application service manager to use our test connections
        $serviceManager->setFactory(
            'p4',
            function () use ($userP4) {
                return $userP4;
            }
        );
        $serviceManager->setFactory(
            'p4_admin',
            function () use ($adminP4) {
                return $adminP4;
            }
        );
        $serviceManager->setFactory(
            'p4_super',
            function () use ($superP4) {
                return $superP4;
            }
        );
        $serviceManager->setFactory(
            'p4_user',
            function () use ($userP4) {
                return $userP4;
            }
        );

        // pretend the non-admin user is logged in
        $serviceManager->setFactory(
            'auth',
            function () {
                $storage = new \Zend\Authentication\Storage\NonPersistent;
                $storage->write(array('id' => 'nonadmin'));
                return new \Zend\Authentication\AuthenticationService($storage);
            }
        );

        // configure mail transport to write messages to disk
        $path   = DATA_PATH . '/mail';
        $config = $serviceManager->get('config');
        $config['mail']['transport'] = array('path' => $path);
        $serviceManager->setService('config', $config);
        @mkdir($path);
    }
}
