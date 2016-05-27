<?php
/**
 * This the swarm page that contains information about a project.
 */
namespace Pages;

class ProjectPage extends \Pages\SwarmPage {

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
        'name'        => 'div.project-navbar div.navbar-inner a.brand',
        'memberCount' => 'div.project-sidebar.profile-sidebar div.profile-info .metrics .members span.count',
        'followerCount' => 'div.project-sidebar.profile-sidebar div.profile-info .metrics .followers span.count',
        'branchCount' => 'div.project-sidebar.profile-sidebar div.profile-info .metrics .branches span.count',
        'avatars'     => 'div.project-sidebar div.members div.avatars div span a.avatar-wrapper'
    );

    public $url = 'projects/';
    public $title = 'Swarm';

    public function __construct($test, $projectName) {
        $this->url = $this->url . str_replace(' ', '-', strtolower($projectName));
        $this->title = $this->title . ' - ' . $projectName;

        parent::__construct($test);
    }

    /**
     * Submits the edit project from and asserts based on the response.
     *
     * @todo - add tests to handle editing of project
     * @param boolean $expectedSuccess  Whether or not we expect this form submission to succeed.
     * @return \Pages\AddProjectPage
     */
    public function submitProjectForm($expectedSuccess = true) {
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

        $this->test->spinAssert("Login was not completed as expected.", $projectFormTest);

        return $this;
    }
}