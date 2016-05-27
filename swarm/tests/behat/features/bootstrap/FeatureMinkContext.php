<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

use Behat\Behat\Event\StepEvent;
use Behat\Mink\Element\NodeElement as MinkNodeElement;
use Behat\Mink\Driver\Selenium2Driver as Selenium2Driver;
use Behat\MinkExtension\Context\MinkContext;
use Symfony\Component\CssSelector;

class FeatureMinkContext extends MinkContext
{
    const TYPE_CSS               = 'css';
    const TYPE_FIELD             = 'field';
    const TYPE_BUTTON            = 'button';
    const TYPE_LINK              = 'link';
    const TYPE_XPATH             = 'xpath';

    const ELEMENT_LOGIN          = '.icon-user .icon-white';
    const ELEMENT_ERROR          = '.error-layout .error-code';

    protected $configParams      = array();
    public static $stepFailed    = false;

    public function __construct(array $parameters = null)
    {
        $this->configParams = $parameters;

        $contexts = isset($parameters['contexts']) ? $parameters['contexts'] : array();
        if (!count($parameters['contexts'])) {
            $this->printDebug('WARNING: No subcontexts found');
        }

        // load all contexts specified in the config (behat.yml)
        foreach ($contexts as $name => $class) {
            $class = 'BehatTests\\' . $class;
            $this->useContext($name, new $class($parameters));
        }
    }

    /**
     * Resets the browser session.
     *
     * @AfterScenario @javascript
     * @Given /^(?: |I) reset browser session$/
     */
    public function resetSession()
    {
        $this->getSession()->restart();
    }

    /**
     * Waits the specified number of seconds before executing the next step.
     *
     * @param   integer $seconds    number of seconds to wait
     *
     * @Given /^I wait for (\d+) seconds$/
     */
    public function wait($seconds)
    {
        sleep($seconds);
    }

    /**
     * Saves a screenshot and snapshot of swarm logs if a step fails.
     * In case of a scenario run with goutte driver, only swarm logs get saved
     *
     * @param   StepEvent $event behat step event generated after each gherkin step
     *
     * @AfterStep
     */
    public function afterStep(StepEvent $event)
    {
        if ($event->getResult() == StepEvent::FAILED) {
            static::$stepFailed = true ;
            if ($this->usingSelenium2()) {
                $this->iSaveAScreenshot();
                $this->iSaveTheSwarmLog();
            } else {
                $this->iSaveTheSwarmLog();
            }
        }
    }

    /**
     * Step to explicitly summarize and save the test's swarm log.
     *
     * @Given /^I save the swarm log$/
     */
    public function iSaveTheSwarmLog()
    {
        $script      = BASE_PATH . '/collateral/scripts/logsummarizer.php';
        $uuid        = $this->getP4Context()->getUUID();
        $log         = $this->configParams['data_dir']     . '/' . $uuid . '/log';
        $summarized  = $this->configParams['failures_dir'] . '/' . $uuid . '/swarm_error_log';
        $raw         = $this->configParams['failures_dir'] . '/' . $uuid . '/swarm_raw_log';

        if (!is_readable($log)) {
            // no swarm log file generated
            $this->printDebug('Failure in p4d setup. No swarm log is generated at expected location: ' . $log);
            return;
        }

        // copy the raw logs over
        copy($log, $raw);
        chmod($raw, 04707);

        // we have a log file and access to the summarizer script
        if (is_readable($script)) {
            exec(
                'php ' . implode(' ', array_map('escapeshellarg', array($script, '-f', $log, '-q'))),
                $output
            );

            if (count($output)) {
                file_put_contents($summarized, implode($output, "\n"));
                chmod($summarized, 04707);
            }
        }
    }

    /**
     * Step to explicitly save a screenshot
     *
     * @Given /^I save a screenshot$/
     */
    public function iSaveAScreenshot()
    {
        if ($this->getSession()->isStarted()) {
            $file = $this->getMinkParameter('browser_name') . '.png';
            $dir  = $this->configParams['failures_dir'] . '/' . $this->getP4Context()->getUUID();
            $this->createDir($dir);
            $this->printDebug("Screenshot saved to $dir/$file");
            $this->saveScreenshot($file, $dir);
        } else {
            $this->printDebug("Browser session not started - screenshot not possible");
        }
    }

    /**
     * Tests whether we're using the Selenium2 driver, and support JS.
     *
     * @return  bool    true if we're using the Selenium2 driver, false otherwise
     */
    public function usingSelenium2()
    {
        return $this->getSession()->getDriver() instanceof Selenium2Driver;
    }

    /**
     * Helper method that returns the HTTP status code from the current page content.
     *
     * @return  string  HTTP response code, scraped from current page content
     */
    public function getHttpStatusCode()
    {
        if ($this->usingSelenium2()) {
            // [If] The test is running with '@javascript' tag (selenium driver), we cannot directly use the
            // MinkContext::getStatusCode() method, since it is unsupported for selenium.
            // Hence we implement the logic based on scrapping the status code from the DOM
            if (!$this->findElementByType(self::ELEMENT_ERROR, 'css')) {
                return '200';
            }
            return $this->findElementByType(self::ELEMENT_ERROR, 'css')->getText();
        }

        // [Else] We use the in-built getStatusCode() method, which should be faster to invoke
        return $this->getSession()->getStatusCode();
    }

    /**
     * Executes javascript to scroll and bring element within the window visible to the simulated user.
     *
     * @param   $css string   the css selector for the element to scroll to
     */
    public function scrollPageToElement($css)
    {
        $js = "var destination = $(\"$css\").offset().top;
               $(document).scrollTop(destination);";
        $this->getSession()->getDriver()->executeScript($js);
    }

    /**
     * Checks, that current page url path is equal to expected path
     * The function performs the equality check very 250 ms, up to a certain maximum
     *
     * @param   $expectedUrl string  expected page url the browser should be on
     * @param   $seconds     int     max timeout required for the assertion
     * @return  bool         true    when expected and actual urls match within max seconds
     * @throws  \Exception           when expected and actual urls don't match after max seconds
     *
     * @Then /^(?:|I )wait to be on "(?P<expectedUrl>[^"]+)" page$/
     * @Then /^(?:|I )wait until url "(?P<expectedUrl>[^"]+)" loads$/
     */
    public function waitUntilPageUrlLoads($expectedUrl, $seconds = 25)
    {
        $expectedUrl = trim($expectedUrl, ' /');
        $start       = time();
        do {
            $url         = trim($this->getSession()->getCurrentUrl(), ' /');
            try {
                if (preg_match('/https?:\/\//', $expectedUrl) !== false) {
                    assertEquals($expectedUrl, $url);
                } else {
                    $url = '/' . (string)end(explode('/', $url));
                    assertEquals($expectedUrl, $url);
                }
                return true;
            } catch (\Exception $e) {
                // quarter second sleep
                usleep(250 * 1000);
            }
        } while ((time() - $start) < $seconds); // max 25 seconds
        throw new \Exception("Failed asserting that actual URL \"$url\" equals expected URL \"$expectedUrl\"");
    }

    /**
     * Waits until given page element is seen on page or for a max. of 25 seconds
     * The element can be searched out using DOM selectors like 'css/xpath' or using named selectors
     * Note: Will only work with '@javascript' tag on scenario (selenium2 driver)
     *
     * @param  string    $locator
     * @param  string    $selector      Type of selector for page element.
     *                                  CSS selectors  : xpath, css
     *                                  Named selectors: fieldset|field|link|button|link_or_button|content|
     *                                                   select|checkbox|radio|file|optgroup|option|table
     *
     * @param  int       $seconds       max timeout required for the assertion
     * @return bool      true           when expected and actual page elements match within max seconds
     * @throws \Exception               when expected and actual elements don't match after max seconds
     *
     * @Then /^(?:|I )wait until page selector "(?P<selector>[^"]+)" with value "(?P<locator>[^"]+)" loads$/
     */
    public function waitUntilPageElementLoads($locator, $selector = self::TYPE_CSS, $seconds = 25)
    {
        if (!$this->usingSelenium2()) {
            throw new \Exception("Method waitUntilPageElementLoads() works with Selenium2 driver only");
        }
        $start = time();
        do {
            try {
                if ($selector != self::TYPE_XPATH) {
                    $xpath = $this->getSession()->getSelectorsHandler()->selectorToXpath($selector, $locator);
                } else {
                    $xpath = $locator;
                }
                // isVisible($locator) needs $locator to be in 'xpath' format
                $this->getSession()->getDriver()->isVisible($xpath);
                return true;
            } catch (\Exception $e) {
                // half second sleep
                usleep(500 * 1000);
            }
        } while ((time() - $start) < $seconds); // max 25 seconds
        throw new \Exception("Failed asserting that element \"$locator\" is visible on page");
    }

    /**
     * Waits until there are no more active Ajax calls, or until the max timeout has been reached
     *
     * @param int $mseconds       the max timeout
     */
    public function waitUntilAjaxCallsComplete($mseconds = 5000)
    {
        $this->getSession()->wait($mseconds, '(0 === jQuery.active)');
    }

    /**
     * Helper function that returns a DOM element based on the locator
     *
     * @param   string  $locator    input id, name or label of element
     * @param   string  $type       type of DOM element being looked for:
     *                               self::TYPE_CSS:    css selector
     *                               self::TYPE_FIELD:  form field name
     *                               self::TYPE_BUTTON: for button name
     *                               self::TYPE_LINK:   link anchor text
     *                               self::TYPE_XPATH:  xpath selector
     * @return  MinkNodeElement|bool the found node, or false if an unknown type was given
     */
    public function findElementByType($locator, $type = self::TYPE_CSS)
    {
        $page = $this->getSession()->getPage();
        switch ($type) {
            case self::TYPE_CSS:
                return $page->find('css', $locator);
            case self::TYPE_FIELD:
                return $page->findField($locator);
            case self::TYPE_BUTTON:
                return $page->findButton($locator);
            case self::TYPE_LINK:
                return $page->findLink($locator);
            case self::TYPE_XPATH:
                return $page->find('xpath', $locator);
            default:
                return false;
        }
    }

    /**
     * Creates directory $dir if it does not exist.
     *
     * @param   string  $dir    directory to create
     */
    public function createDir($dir)
    {
        if (!file_exists($dir)) {
            @mkdir($dir);
        }
    }

    /**
     * Helper function to access members of the P4Context class
     *
     * @return P4Context
     */
    protected function getP4Context()
    {
        return $this->getMainContext()->getSubContext('p4');
    }
}
