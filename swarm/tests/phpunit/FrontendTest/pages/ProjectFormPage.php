<?php
/**
 * This the swarm page that contains information about adding and editing projects.
 */
namespace Pages;

class ProjectFormPage extends \Pages\SwarmPage {

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
        'name'                => '#name',
        'description'         => '#description',

        'members'               => '#members',
        'activeMember'          => 'div.control-group.team .swarm-add-member ul.typeahead li.active',

        // note that these 3 selectors are expected to return multiple results
        'selectedMembers'       => 'div.control-group.team div.controls.team-list div.member-button button.member-name',
        'removeSelectedMembers' => 'div.control-group.team div.controls.team-list div.member-button button.member-remove',
        'hiddenMembers'         => 'div.control-group.team div.controls.team-list div.member-button input.member-id',

        'addBranchLink'       => 'a.swarm-branch-group[name="branches"]',
        'openAddBranchButton' => 'div.branch-button div.btn-group.open button.dropdown-toggle.btn-danger',

        'addBranchDialog'     => 'div.branch-button div.dropdown-menu.dropdown-subform',
        'addBranchName'       => 'div.branch-button div.dropdown-menu.dropdown-subform input[type="text"]',
        'addBranchPaths'      => 'div.branch-button div.dropdown-menu.dropdown-subform textarea',
        'addBranchDone'       => 'div.branch-button div.dropdown-menu.dropdown-subform button.close-branch-btn',
        'addBranchRemove'     => 'div.branch-button div.dropdown-menu.dropdown-subform button.clear-branch-btn',

        'jobFilter'     => '#jobview',

        'enableTests'   => '#testsEnabled',
        'testsUrl'      => 'div.automated-tests-control textarea',

        'enableDeploy'  => '#deployEnabled',
        'deployUrl'     => 'div.automated-deployment-control textarea',

        'save'          => 'div.project-edit form div.control-group.group-buttons button[type="submit"]',
        'cancel'        => 'div.project-edit form div.control-group.group-buttons button[type="button"]',

        'alertError'    => 'div.alert.alert-error',
        'inputError'    => 'div.control-group.error',
    );

    public $url = 'project/add';
    public $title = 'Swarm - Add Project';

    /**
     * Submits the add project from and asserts based on the response.
     *
     * @param boolean $expectedSuccess  Whether or not we expect this form submission to succeed.
     * @return \Pages\AddProjectPage
     */
    public function submitProjectForm($expectedSuccess = true) {
        $projectName = $this->name->value();

        $this->save->click();

        $page = $this;
        $projectFormTest = function() use ($page, $expectedSuccess) {
            // Return false if page-level error notice is unexpectedly displayed.
            // Note that this usually indicates a system-level issue, such as no
            // connection to perforce, no permission, etc.
            $elements = $page->test->fetchElementsBy('css selector', $page->locators['alertError']);
            if (!empty($elements)) {
                return false;
            }

            // Otherwise check to ensure the the form completed as expected by
            // looking for elements indicating error.
            $elements = $page->test->fetchElementsBy('css selector', $page->locators['inputError']);
            return ((empty($elements) && $expectedSuccess) || (!empty($elements) && !$expectedSuccess));
        };

        $this->test->spinAssert("Project was not created as expected.", $projectFormTest);

        return new ProjectPage($this->test, $projectName);
    }

    public function addMember($name) {
        if (!array_key_exists($name, $this->test->p4users)) {
            throw new Exception ('Invalid member name "' . $name . '", ' . __LINE__ . ' of ' . __FILE__);
        }

        $this->members = substr($this->test->p4users[$name]['User'], 0, 1);
        $this->activeMember->click();

        return $this;
    }
}