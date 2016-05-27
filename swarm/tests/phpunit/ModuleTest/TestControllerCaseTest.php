<?php
/**
 * Test for the TestControllerCase class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ModuleTest;

use Zend\Stdlib\Parameters;
use Zend\Mvc\MvcEvent;

class TestControllerCaseTest extends TestControllerCase
{
    /**
     * Extends parent by supplying custom application config.
     */
    public function setUp()
    {
        // supply our own config to load modules we will be testing on
        $this->setConfiguration(__DIR__ . '/assets/application.config.php');

        parent::setUp();
    }

    /**
     * Test getApplication() method.
     */
    public function testGetApplication()
    {
        $application = $this->getApplication();
        $this->assertInstanceOf('\Zend\Mvc\Application', $application);
    }

    /**
     * Ensure that test application is properly configured.
     */
    public function testApplicationConfiguration()
    {
        $application    = $this->getApplication();
        $serviceManager = $application->getServiceManager();

        // ensure admin p4 factory is set to return p4 connection created by the test
        $this->assertSame($this->p4, $serviceManager->get('p4_admin'));
    }

    /**
     * Test dispatch() method.
     */
    public function testDispatch()
    {
        // attach all events to check whose of them were triggered by dispatch()
        $triggeredEvents = new \ArrayIterator;
        $eventManager    = $this->getApplication()->getEventManager();
        $events          = array(
            MvcEvent::EVENT_BOOTSTRAP,
            MvcEvent::EVENT_DISPATCH,
            MvcEvent::EVENT_DISPATCH_ERROR,
            MvcEvent::EVENT_FINISH,
            MvcEvent::EVENT_RENDER,
            MvcEvent::EVENT_ROUTE
        );
        foreach ($events as $event) {
            $eventManager->attach(
                $event,
                function () use ($event, $triggeredEvents) {
                    $triggeredEvents[] = $event;
                }
            );
        }

        // dispatch
        $response = $this->dispatch('/test');

        // ensure we got expected output
        $this->assertSame('Test Successful.', $response);

        // verify that anticipated events were triggered (in given order)
        $this->assertSame(
            array(
                MvcEvent::EVENT_ROUTE,
                MvcEvent::EVENT_DISPATCH,
                MvcEvent::EVENT_RENDER,
                MvcEvent::EVENT_FINISH
            ),
            $triggeredEvents->getArrayCopy()
        );
    }

    /**
     * Verify functionality of the assertModule() method.
     */
    public function testAssertModule()
    {
        $this->dispatch('/test');

        $this->assertModule('Foo');
        $this->assertModule('foo');
        $this->assertModule('FOO');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertModule('Bar');
    }

    /**
     * Verify functionality of the assertController() method.
     */
    public function testAssertController()
    {
        $this->dispatch('/test');

        $this->assertController('Foo\Controller\IndexController');
        $this->assertController('foo\controller\indexcontroller');
        $this->assertController('foo\controller\indexController');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertController('index');
    }

    /**
     * Verify functionality of the assertController() method.
     */
    public function testAssertAction()
    {
        $this->dispatch('/test');

        $this->assertAction('test');
        $this->assertAction('Test');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertAction('testAction');
    }

    public function testAssertRouteMatch()
    {
        $this->dispatch('/test');

        $this->assertRouteMatch(
            'foo',
            'foo\controller\indexcontroller',
            'test'
        );

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertRouteMatch(
            'foo',
            'foo\controller\indexcontroller',
            'foo'
        );
    }

    /**
     * Verify functionality of the assertRoute() method.
     */
    public function testAssertRoute()
    {
        $this->dispatch('/test');

        $this->assertRoute('foo-test');
        $this->assertRoute('Foo-Test');
        $this->assertRoute('Foo-TEST');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertRoute('footest');
    }

    /**
     * Verify functionality of the assertResponseStatusCode() method.
     */
    public function testAssertResponseStatusCode200()
    {
        $this->dispatch('/test');

        $this->assertResponseStatusCode(200);

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertResponseStatusCode(202);
    }

    /**
     * Verify functionality of the assertResponseStatusCode() method with redirect.
     */
    public function testAssertResponseStatusCode302()
    {
        $this->dispatch('/redirect');

        $this->assertResponseStatusCode(302);

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Verify functionality of the assertQuery() method.
     */
    public function testAssertQuery()
    {
        $this->dispatch('/data');

        $this->assertQuery('.foo-index');
        $this->assertQuery('.foo-index .get-data');
        $this->assertQuery('.foo-index .post-data');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertQuery('.foo-index p');
    }

    /**
     * Verify functionality of the assertNotQuery() method.
     */
    public function testAssertNotQuery()
    {
        $this->dispatch('/data');

        $this->assertNotQuery('.foo-index1');
        $this->assertNotQuery('.foo-index .get-data1');
        $this->assertNotQuery('.foo-index .post-data1');
        $this->assertNotQuery('.foo-index .post-data p');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertNotQuery('.foo-index .get-data');
    }

    /**
     * Verify functionality of the assertQueryCount() method.
     */
    public function testAssertQueryCount()
    {
        $this->getRequest()->setQuery(
            new Parameters(
                array(
                    'myParam1'   => 'x',
                    'myParam2'   => 'yy',
                    'myParam3'   => 'xyz'
                )
            )
        );

        $this->dispatch('/data');

        $this->assertQueryCount('.foo-index', 1);
        $this->assertQueryCount('.foo-index .get-data .get-myParam', 0);
        $this->assertQueryCount('.foo-index .get-data .get-myParam1', 1);
        $this->assertQueryCount('.foo-index .get-data .get', 3);
        $this->assertQueryCount('.foo-index .get', 3);
        $this->assertQueryCount('.get', 3);
        $this->assertQueryCount('.foo-index .post-data .post', 0);
        $this->assertQueryCount('.foo-index .post', 0);
        $this->assertQueryCount('.post', 0);

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertQueryCount('.foo-index .get-data .get', 2);
    }

    /**
     * Verify functionality of the assertQueryContentContains() method.
     */
    public function testAssertQueryContentContains()
    {
        $this->getRequest()->setQuery(
            new Parameters(
                array(
                    'myParam1'   => 'x',
                    'myParam2'   => 'yy',
                    'myParam3'   => 'xyz'
                )
            )
        );
        $this->getRequest()->setPost(
            new Parameters(
                array(
                    'myPost1'   => 'foo',
                    'myPost2'   => 'bar',
                    'myPost3'   => 'baz'
                )
            )
        );

        $this->dispatch('/data');

        $this->assertQueryContentContains('.foo-index .get-data .get-myParam1', 'x');
        $this->assertQueryContentContains('.foo-index .get-myParam2', 'yy');
        $this->assertQueryContentContains('.get-myParam3', 'xyz');
        $this->assertQueryContentContains('.foo-index .post-data .post-myPost1', 'foo');
        $this->assertQueryContentContains('.post-data .post-myPost2', 'bar');
        $this->assertQueryContentContains('.post-myPost3', 'baz');

        $this->setExpectedException('PHPUnit_Framework_AssertionFailedError');
        $this->assertQueryContentContains('.post-myPost3', 'bazz');
    }
}
