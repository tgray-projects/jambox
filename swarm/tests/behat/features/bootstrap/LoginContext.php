<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

class LoginContext extends AbstractContext
{
    const ELEMENT_LOGIN_LINK    = 'Log in';
    const ELEMENT_USERNAME      = 'user';
    const ELEMENT_PASSWORD      = 'password';
    const ELEMENT_LOGIN_BUTTON  = '.btn.btn-mlarge.btn-primary';
    const ELEMENT_LOGGED_IN     = '.nav.pull-right.user';

    /**
     * Login a P4 user to Swarm
     * By default, the P4 user will have admin permissions
     *
     * @When /^I login to swarm$/
     * @When /^I login to swarm as "(?P<user>[^"]*)" user$/
     */
    public function iLoginToSwarm($user = 'admin')
    {
        $session = $this->getSession();
        $page = $session->getPage();

        // capture user details based on $user
        $p4user = $this->getP4Context()->getP4User($user);

        // A user can login to swarm by being on any public url , not just the homepage
        // Hence, we needn't explicitly visit a url to login ( since a session is already instantiated in setup)
        $page->clickLink(self::ELEMENT_LOGIN_LINK);

        // The login dialog (to enter username & password) must be waited for after we click on 'loginlink'
        // The login dialog is not associated with a page 'url', and hence we cannot test waiting for the
        // url as is done in FeatureMinkContext::waitUntilPageLoads() method.
        $this->waitForLoginDialog($p4user);

        // fill in login dialog
        $page->fillField(self::ELEMENT_USERNAME, $p4user['User']);
        $page->fillField(self::ELEMENT_PASSWORD, $p4user['Password']);
        $this->getMinkContext()->findElementByType(self::ELEMENT_LOGIN_BUTTON, 'css')->press();

        // Verify that user has successfully logged-in
        $this->verifyLoginPage($p4user);
    }

    /**
     * Helper method to verify that login dialog is seen on page
     */
    protected function waitForLoginDialog($p4user)
    {
        if ($this->getMinkContext()->usingSelenium2()) {
            // wait for the DOM element to load, if using the selenium driver
            $this->getMinkContext()->waitUntilPageElementLoads('#' . self::ELEMENT_USERNAME, 'css');
        } else {
            // we insert a very small sleep ( for non-selenium runs)
            sleep(2);
        }
    }

    /**
     * Helper method to verify that a P4 user has successfully been logged into swarm
     * For non-selenium driver runs, the response is a JSON object which we parse to verify that user
     * has successfully logged in. We assert that response is valid and contains user-id of '$p4user'
     * When using selenium2 driver, we can verify that a user has logged in by asserting that the navigation
     * menu "ELEMENT_LOGGED_IN" on the page's header contains the user-id of '$p4user' instead of text 'Log In'
     *
     * @param $p4user       string        P4 User
     * @return bool         true          Return true if the assertion conditions are met
     * @throws \Exception                 If the "ELEMENT_LOGGED_IN" menu does not contain user-id after 25 secs.
     */
    protected function verifyLoginPage($p4user)
    {
        if ($this->getMinkContext()->usingSelenium2()) {
            // [if] the scenario is run using the selenium2 driver

            // max time to wait for 'ELEMENT_LOGGED_IN' menu item to contain user-id of $p4user' = 25 seconds
            $seconds = 25;
            $start = time();

            // keep looping at every 50ms intervals till the condition is true ( up to a max of 25 seconds)
            do {
                try {
                    // verify if the assertion is true, else sleep for 50ms
                    assertContains(
                        $p4user['User'],
                        $this->getMinkContext()->findElementByType(self::ELEMENT_LOGGED_IN, 'css')->getText()
                    );
                    return true;
                } catch (\Exception $e) {
                    // half second sleep
                    usleep(500 * 1000);
                }
            } while ((time() - $start) < $seconds);
            throw new \Exception("Failed asserting element \"{self::ELEMENT_LOGGED_IN}\" contains {$p4user['User']}");
        } else {
            // [else] the scenario is run using the goutte driver

            // verify that JSON response is valid,
            // else throw invalid response exception
            assertContains(
                "\"isValid\":true",
                $this->getSession()->getPage()->getContent(),
                "Invalid response on swarm user login"
            );
            // verify that JSON response contains the user-id of '$p4user'
            // else throw invalid response exception
            assertContains(
                $p4user['User'],
                $this->getSession()->getPage()->getContent(),
                "Invalid response on swarm user login"
            );
            return true;
        }
    }
}
