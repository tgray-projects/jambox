<?php
/**
 * This the swarm page that contains login information.
 */
namespace Pages;

class LoginPage extends \Pages\SwarmPage {

    /**
     * A list of known page resources for use in testing.  Contains the type
     * of identifier and the identifier itself.
     *
     * @var type array(string, string)
     */

    // lookup by css by default, with string param
    // if non-string, assume this information
    // if string, then do css lookup
    public $locators = array(
        'navbarLogin'     => 'div.navbar-site a[href="/login/"]',
        'navbarLogout'    => 'div.navbar-site ul.user ul.dropdown-menu a[href="/logout/"]',
        'navbarUser'      => 'div.navbar-site ul.user li a.dropdown-toggle',
        'username'        => '#user',
        "password"        => '#password',
        'loginDialog'     => 'div.modal.login-dialog',
        "loginFormSubmit" => 'div.modal.login-dialog form [type=submit]',
        "loginDialogFormError"  => 'div.modal.login-dialog form .alert',

        "addProject"      => 'div.project-add a[href="/project/add"]'
    );

    public $url = '';
    public $title = 'Swarm - Activity';

    /**
     * Clicks the login link in the nav bar to open the login dialog.
     * Asserts whether or not the dialog is displayed.
     *
     * @return \Pages\LoginPage
     */
    public function openLoginDialog() {
        $this->navbarLogin->click();

        $page = $this;
        $loginTest = function() use ($page) {
            // Return false if error notice is displayed.
            // @todo compare vs $page->__get($page->locators['loginDialogFormError']);
            $elements = $page->test->fetchElementsBy('css selector', $page->locators['loginDialogFormError']);
            if (!empty($elements)) {
                return false;
            }

            return $page->loginDialog->displayed();
        };

        $this->test->spinAssert("Login was not completed as expected.", $loginTest);
        return $this;
    }

    /**
     * Submits the login dialog and asserts based on whether or not we expect
     * the login form to succeed.
     *
     * @param boolean $expectedSuccess  Whether or not we expect this login attempt to succeed.
     * @return \Pages\LoginPage
     */
    public function submitLoginDialog($expectedSuccess = true) {
        $this->loginFormSubmit->click();

        $page = $this;
        $loginTest = function() use ($page, $expectedSuccess) {
            // Return false if error notice is unexpectedly displayed.
            $elements = $page->test->fetchElementsBy('css selector', $page->locators['loginDialogFormError']);
            if (!empty($elements)) {
                return !$expectedSuccess;
            }

            // Otherwise check to ensure the user is logged in.
            // We cannot check authenticated class on body, because page is not
            // always reloaded on login.
            if ($expectedSuccess) {
                return $page->navbarUser->text() == $page->username->value();
            } else {
                return $page->navbarUser->text() != $page->username->value();
            }
        };

        $this->test->spinAssert("Login was not completed as expected.", $loginTest);

        return $this;
    }

    public function logout() {
        if (!$this->navbarUser->text()) {
            throw new Exception('User is not logged in, cannot log out.');
        }

        $this->navbarLogout->click();

        return $this;
    }

    public function projectAdd()
    {
        $this->addProject->click();

        return new ProjectFormPage($this->test);
    }
}