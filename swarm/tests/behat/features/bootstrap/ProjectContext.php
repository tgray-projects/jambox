<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

 namespace BehatTests;

use Behat\Behat\Context\Step;

class ProjectContext extends AbstractContext
{
    const ELEMENT_PROJECT_NAME             = 'name';
    const ELEMENT_PROJECT_MEMBERS          = 'members';
    const ELEMENT_ADD_PROJECT_BUTTON_CSS   = '.project-add a';
    const ELEMENT_SAVE_PROJECT_BUTTON      = ".btn-primary[type='submit']";
    const ELEMENT_DONE_CSS                 = '.controls .branch-button .open .close-branch-btn';
    const ELEMENT_DESCRIPTION_CSS          = '.project-sidebar .description .first-line';
    const ELEMENT_METRIC_MEMBERS_CSS       = '.project-sidebar .metrics .members .count';
    const ELEMENT_METRIC_BRANCHES_CSS      = '.project-sidebar .metrics .branches .count';
    const ELEMENT_DROPDOWN_CSS             = '.control-group-members ul.dropdown-menu';
    const ELEMENT_ADD_BRANCH_CSS           = '.control-group.branches a';
    const ELEMENT_PROJECT_MEMBER_CSS       = '.project-sidebar .members img';
    const ELEMENT_PROJECT_BRANCH_CSS       = '.project-sidebar .branches a';
    const ELEMENT_PROJECTS_SIDEBAR_ROW_CSS = '.projects-sidebar tbody tr';

    // the number of branches that have been added to the current project (this is
    // needed because the branch element classes are dependant on the branch number)
    private $branchCount                   = 0;

    /**
     *  Call after attempting to save a project and before further interaction with the UI to
     *  ensure that the ajax call has completed.
     *  The function will wait until one of two conditions is met: a new page has been opened,
     *  or the Save button has been re-enabled.
     *
     * @param   $seconds     int     max timeout required for the assertion
     *
     * @return  bool         true    when we are no longer at the Add Project page, or
     *                               when the Save button is enabled
     * @throws \Exception            when neither condition is met after max seconds
     */
    public function waitUntilSavingRequestHasReturned($seconds = 5)
    {
        $button       = $this->getMinkContext()->findElementByType(self::ELEMENT_SAVE_PROJECT_BUTTON, 'css');
        $projectPage = $this->configParams['base_url'] . "/projects/add/";
        $start        = time();

        do {
            try {
                if ($this->getSession()->getCurrentUrl() !== $projectPage ||
                    $button->hasAttribute("disabled") == false) {
                    return true;
                }
            } catch (\Exception $e) {
                usleep(1000); // 1 ms sleep
            }
        } while ((time() - $start) < $seconds); // max 5 seconds
        throw new \Exception("Failed asserting that the Save button becomes enabled.");
    }

    /**
     * Clicks the project save button and waits for the project page to load
     *
     * @Given /^I click the "Save" button on the "Add Project" page$/
     */
    public function iClickTheSaveProjectButton()
    {
        $this->getMinkContext()->findElementByType(self::ELEMENT_SAVE_PROJECT_BUTTON, 'css')->click();
        $this->waitUntilSavingRequestHasReturned();

    }

    /**
     * @Then /^I should be redirected to "\/projects\/(?P<page>[^"]*)"$/
     */
    public function iShouldBeRedirectedToProjectPage($page)
    {
        $actual = $this->getSession()->getCurrentUrl();

        $base = $this->configParams['base_url'] . "/projects/";
        $expected = $base . $page;
        $expectedEncoded = $base . urlencode($page);

        // Note: it is not enough to check against the encoded only, as getCurrentUrl() returns the
        // encoded url for the chrome and firefox drivers and the decoded url for the safari driver
        assertTrue($actual === $expected || $actual === $expectedEncoded);
    }

    /**
     * @Then /^The "Save" button should be (enabled|disabled)$/
     */
    public function theSaveButtonShouldBeEnabledOrDisabled($enabled)
    {
        $button = $this->getMinkContext()->findElementByType(self::ELEMENT_SAVE_PROJECT_BUTTON, 'css');

        if ($enabled === "enabled") {
            assertEquals(false, $button->hasAttribute("disabled"));
        } else {
            assertEquals(true, $button->hasAttribute("disabled"));
        }
    }

    /**
     * @When /^I click the "Add Project" button$/
     */
    public function iClickTheAddProjectButton()
    {
        $this->getMinkContext()->findElementByType(self::ELEMENT_ADD_PROJECT_BUTTON_CSS, 'css')->click();
    }

    /**
     * @When /^I am on a newly opened "Add Project" page$/
     */
    public function iAmOnANewlyOpenedAddProjectPage()
    {
        $this->getSession()->visit($this->configParams['base_url']."/projects/add/");
    }

    /**
     * @Then /^I should be redirected to the "Add Project" page$/
     */
    public function iShouldBeRedirectedToTheAddProjectPage()
    {
        $this->getMinkContext()->waitUntilPageUrlLoads($this->configParams['base_url'] . "/projects/add/", 5);
    }

    /**
     * @When /^I add the member "([^"]*)" to the project$/
     */
    public function iAddTheMemberToTheProject($member)
    {
        $this->iEnterInputInTheMembersField($member);
        $this->iClickOnUserInDropdownList($member);
    }

    /**
     * @When /^I click on user "([^"]*)" in the dropdown list$/
     */
    public function iClickOnUserInDropdownList($userName)
    {
        $elementCss = self::ELEMENT_DROPDOWN_CSS . " li[data-value^=\"$userName\"]";
        $this->getMinkContext()->assertElementOnPage($elementCss);
        $this->getMinkContext()->findElementByType($elementCss, 'css')->click();
    }

    /**
     * @Then /^I should see an error "([^"]*)"$/
     */
    public function iShouldSeeAnError($error)
    {
        return new Step\Then("I should see \"$error\"");
    }

    /**
     * @Given /^I have created a project named "(?P<projectName>[^"]*)"$/
     * @Given /^I have created a project named "(?P<projectName>[^"]*)" with member "(?P<projectMember>[^"]*)"&/
     */
    public function iHaveCreatedAProject($projectName, $projectMember = "swarm-admin")
    {
        return array(
            new Step\When("I am on a newly opened \"Add Project\" page"),
            new Step\When("I enter \"$projectName\" in the \"Name\" field"),
            new Step\When("I add the member \"$projectMember\" to the project"),
            new Step\When("I click the \"Save\" button on the \"Add Project\" page")
        );
    }

    /**
     * @When /^I enter a valid name and project member$/
     */
    public function iEnterAValidNameAndMember()
    {
        return array(
            new Step\When("I enter \"SampleProject\" in the \"Name\" field"),
            new Step\When("I add the member \"swarm-admin\" to the project")
        );
    }

    /**
     * @When /^I click the "Add Branch" button$/
     */
    public function iClickTheAddBranchButton()
    {
        $this->getMinkContext()->findElementByType(self::ELEMENT_ADD_BRANCH_CSS, 'css')->click();
    }

    /**
     * @When /^I add a branch with name "([^"]*)" and mapping "([^"]*)"$/
     */
    public function iAddABranchWithNameAndMapping($name, $mapping)
    {
        $nameId  = "branch-name-" . $this->branchCount;
        $pathsId = "branch-paths-" . $this->branchCount;
        $this->branchCount++;

        $this->iClickTheAddBranchButton();
        $this->getMinkContext()->fillField($nameId, $name);
        $this->getMinkContext()->fillField($pathsId, $mapping);
        $this->iClickDoneButton();
    }

    /**
     * @Then /^I should see the project "([^"]*)" listed under "Projects"$/
     */
    public function iShouldSeeTheProjectListedUnder($projectName)
    {
        $this->getMinkContext()->waitUntilPageElementLoads(self::ELEMENT_PROJECTS_SIDEBAR_ROW_CSS, "css");

        $pageName = urlencode(strtolower($projectName));
        $projectCss = self::ELEMENT_PROJECTS_SIDEBAR_ROW_CSS . ".project a.name[href='/projects/" . $pageName . "']";
        return new Step\Then("I should see an \"$projectCss\" element");
    }

    /**
     * @Then /^I should see the user list "([^"]*)"$/
     */
    public function iShouldSeeTheUserList($usersList)
    {
        $usersArray = explode(", ", $usersList);

        // check that drop-down list is visible
        $dropdownCss = self::ELEMENT_DROPDOWN_CSS . ":not([display='none'])";
        $this->getMinkContext()->assertElementOnPage($dropdownCss);

        // check that the drop-down contains each of the names in usersList
        foreach ($usersArray as $user) {
            $userCss = self::ELEMENT_DROPDOWN_CSS . " li[data-value^='$user']";
            $this->getMinkContext()->assertElementOnPage($userCss);
        }
    }

    /**
     * @Then /^I should see "([^"]*)", "([\d]+) Members", "([\d]+) Branches" under the "About" section$/
     */
    public function iShouldSeeMetricsUnderTheAboutSection($description, $members, $branches)
    {
        return array(
            new Step\Then("I should see \"$description\" in the \"" . self::ELEMENT_DESCRIPTION_CSS . "\" element"),
            new Step\Then("I should see \"$members\" in the \"" . self::ELEMENT_METRIC_MEMBERS_CSS . "\" element"),
            new Step\Then("I should see \"$branches\" in the \"" . self::ELEMENT_METRIC_BRANCHES_CSS . "\" element")

        );
    }

    /**
     * @Then /^I should see (users|branches) "(?P<list>[^"]*)" under "(?P<section>members|branches)"$/
     */
    public function iShouldSeeListUnderSection($list, $section)
    {
        $entriesArray = explode(", ", $list);

        foreach ($entriesArray as $entry) {
            if ($section === "members") {
                $this->getMinkContext()->assertElementOnPage(self::ELEMENT_PROJECT_MEMBER_CSS . "[data-user='$entry']");
            } else {
                $this->getMinkContext()->assertElementOnPage(self::ELEMENT_PROJECT_BRANCH_CSS . ":contains('$entry')");
            }
        }

    }

    /**
     * @When /^I click on the "x" next to "([^"]*)"$/
     */
    public function iClickOnTheXNextToName($userName)
    {
        // Click away from the drop-down picker to ensure it is closed. This is needed
        // because the chrome driver can't click a button if it is obscured by something else.
        $this->getMinkContext()->findElementByType("i.icon-user")->click();

        $removeButtonCss = ".multipicker-item[data-value^='$userName'] button.item-remove";
        $this->getMinkContext()->assertElementOnPage($removeButtonCss);
        $this->getMinkContext()->findElementByType($removeButtonCss, 'css')->click();
    }

    /**
     * @Given /^The list of users on the server includes "([^"]*)"$/
     */
    public function theListOfUsersOnTheServerIncludes($userList)
    {
        $usernameArray = explode(", ", $userList);

        foreach ($usernameArray as $username) {
            $this->getP4Context()->createRegularUser($username);
            $this->getP4Context()->instantiateWorker();
        }
    }

    /**
     * @When /^I click "Done" on the "Add Branch" window$/
     */
    public function iClickDoneButton()
    {
        $this->getMinkContext()->findElementByType(self::ELEMENT_DONE_CSS, 'css')->click();
    }

    /**
     * @When /^I enter "(?P<input>[^"]*)" in the "Name" field$/
     */
    public function iEnterInputInTheNameField($input)
    {
        $this->getMinkContext()->fillField(self::ELEMENT_PROJECT_NAME, $input);
    }

    /**
     * @When /^I enter "(?P<input>[^"]*)" in the "Members" field$/
     */
    public function iEnterInputInTheMembersField($input)
    {
        // wait until input is enabled
        $membersCss = "input#members:not([disabled])";
        $this->getMinkContext()->waitUntilPageElementLoads($membersCss);

        $this->getMinkContext()->fillField(self::ELEMENT_PROJECT_MEMBERS, $input);
    }

    /**
     * @Given /^I create a project "(?P<projectName>[^"]*)" with mapping "(?P<projectMapping>[^"]*)"$/
     */
    public function iCreateAProjectWithMapping($projectName, $projectMapping)
    {
        $this->iAmOnANewlyOpenedAddProjectPage();
        $this->iEnterInputInTheNameField($projectName);
        $this->iAddTheMemberToTheProject("swarm-admin");
        $this->iAddABranchWithNameAndMapping("Jam", $projectMapping);
        $this->iClickTheSaveProjectButton();

    }

    /**
     * @Then /^I should see the "(?P<projectName>[^"]*)" project header$/
     */
    public function iShouldSeeTheProjectHeader($projectName)
    {
        $this->getMinkContext()->assertElementOnPage(".project-navbar .brand:contains('$projectName')");
    }
}
