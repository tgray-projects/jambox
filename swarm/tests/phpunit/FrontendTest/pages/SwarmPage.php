<?php
namespace Pages;

class SwarmPage {
    // these constants are used with locators to look up page elements
    // by default, you can reference things by CSS.
    const CLASS_NAME = 'className';
    const CSS        = 'css';
    const ID         = 'id';
    const NAME       = 'name';
    const XPATH      = 'xPath';
    const LINK       = 'linkText';
    const TAG        = 'tag';

    // the expected page url and title for verification purposes.
    public $url = '';
    public $title = '';

    /**
     * A list of known page resources for use in testing.  Contains the type
     * of identifier and the identifier itself.  See LoginPage.php for an example.
     *
     * @var type array(string, string)
     */
    public $locators = array();

    /**
     * Constructor keep a copy of the webdriver test being executed for reference.
     *
     * @param \Tests\SwarmTest $test  The WebDriver test.
     */
    public function __construct($test) {
        // allow pages to set a custom url
        $this->url = $test->url . $this->url;
        $this->test = $test;

        $this->open()->validate();
    }

    /**
     * Returns requested page element.  Valid elements are those listed in
     * $locators, as well as 'title'.  Note that title returns the actual page
     * title fetched via WebDriver, not the expected page title.
     *
     * @todo    Clean up title handling?
     *
     * @param   String $property    The requested page element's friendly name.
     * @return  PHPUnit_Extensions_Selenium2TestCase_Element || string The value of the element.
     * @throws  Exception           Throws an exception if the locator is invalid.
     */
    function __get($property) {
        // only work with defined locators; this prevents tests that don't
        // update the page information.
        if (array_key_exists($property, $this->locators)) {
            $locator = $this->locators[$property];

            // if locator is of the form 'locatorName' => '.css lookup'
            if (is_string($locator)) {
                return call_user_func(array($this->test, 'byCss'), $locator);
            }
            // if the locator is of the form 'locatorName' => array('type', 'xPath') or similar.
            else if (is_array($locator)) {
                list($type, $string) = $this->locators[$property];
                // this calls the $test->xPath, $test->id, etc method
                return call_user_func(
                    array($this->test, $this->getLocatorFunction($type)),
                    $string
                );
            } else {
                throw new Exception ('Invalid locator provided for ' . $property . '.');
            }
        } else if ($property == 'title') {
            return $this->test->title();
        }

        return $this->$property;
    }

    /**
     * Sets a property on the page.  Only specified locators are valid.
     *
     * @param string $property  The name of the specified property to set.
     * @param string $value     The value to set the property to.
     * @return \WebDriver\SwarmPage Returns $this for chaining.
     * @throws Exception        Throws an exception if an invalid locator is provided.
     */
    public function __set($property, $value) {
        // only work with defined locators; this prevents tests that don't
        // update the page information.
        if (array_key_exists($property, $this->locators)) {

            $locator = $this->locators[$property];

            // operates similar to __get functionality.
            if (is_string($locator)) {
                $element = call_user_func(array($this->test, 'byCss'), $locator);
            } else if (is_array($locator)) {
                list($type, $string) = $this->locators[$property];
                $element = call_user_func(
                    array($this->test, $this->getLocatorFunction($type)),
                    $string
                );
            } else {
                throw new Exception ('Invalid locator provided for ' . $property . '.');
            }

            // set the value.
            $element->value($value);
        } else {
            // if not a valid locator, allow tests to store page-related
            // data in the page object.
            $this->$property = $value;
        }

        return $this;
    }

    /**
     * Opens the page in the browser.
     *
     * @return \WebDriver\SwarmPage
     */
    public function open() {
        $this->test->url($this->url);
        return $this;
    }

    /**
     * Validates the page by comparing the expected url to the actual url
     * and the expected title to the actual title.
     *
     * @return \WebDriver\SwarmPage
     */
    public function validate() {
        $this->test->assertTrue(
            $this->test->url() == $this->url,
            "Page url " . $this->test->url() . " does not match expected url " . $this->url . ".\n"
        );
        if ($this->title != '') {
            $this->test->assertTrue(
                $this->test->title() == $this->title,
                "Page title " . $this->test->title() . " does not match expected title " . $this->title . ".\n"
            );
        }

        return $this;
    }

    /**
     * Helper function to authenticate a user based on provided credentials.
     * @todo Sets authentication via cookie/session.
     *
     * @param string $username  The username to authenticate with.
     * @param string $password  The password to authenticate with.
     * @param boolean $success  Whether or not success is expected.
     * @return \SwarmPage
     */
    public function loginAs($username, $password = '') {
        if (array_key_exists($username, $this->test->p4users)) {
            $user = $this->test->p4users[$username];
            $username = $user['User'];
            $password = $user['Password'];
        }

        $this->open($this->test->url . 'login');
        $this->user = $username;
        $this->password = $password;
        $submit = $this->test->byCss('div.login-dialog form [type=submit]')->click();

        return $this;
    }

    /**
     * Stringbashes the locator function name out of the short form above.
     * @param String    $type   The type of locator
     * @return String           The function name to call to use the specified locator.
     */
    public function getLocatorFunction($type) {
        return 'by' . ucfirst($type);
    }
}