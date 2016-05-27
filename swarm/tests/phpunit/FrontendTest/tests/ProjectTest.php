<?php

namespace Tests;

use \Tests\SwarmTest;
use \Pages\ProjectPage;
use \Pages\ProjectAddPage;
use \Pages\LoginPage;

class ProjectTest extends SwarmTest
{
    public function setUp()
    {
        $this->tags = $this->tags + array('project');

        parent::setUp();
    }

    public function setUpPage() {
        $this->loginPage = new LoginPage($this);
    }

    /**
     * Test without authenticating, expect the add project link to not be displayed.
     */
    public function nottestAddProjectUnauthenticated()
    {
        $this->assertFalse($this->loginPage->addProject->displayed());
    }

    /**
     * Test adding a project as an admin user.
     * Includes verification of the project form as an admin.
     */
    public function testAddProjectAdmin()
    {
        $this->loginPage->openLoginDialog();
        $this->loginPage->username = $this->p4users['admin']['User'];
        $this->loginPage->password = $this->p4users['admin']['Password'];
        $this->loginPage->submitLoginDialog();

        $this->projectFormPage = $this->loginPage->projectAdd();
        $this->verifyProjectForm();
        $this->addTestProject();
    }

    /**
     * Test adding a project as a regular user.
     * Includes verification of the project form as a regular user.
     */
    public function testAddProject()
    {
        $this->loginPage->openLoginDialog();
        $this->loginPage->username = $this->p4users['vera']['User'];
        $this->loginPage->password = $this->p4users['vera']['Password'];
        $this->loginPage->submitLoginDialog();

        $this->projectFormPage = $this->loginPage->projectAdd();
        $this->verifyProjectForm();
        $this->addTestProject();
    }

    /**
     * Create a project as the logged-in user, with vera and the admin
     * user being project members.
     */
    public function addTestProject()
    {
        $this->projectFormPage->name = 'Test Project';
        $this->projectFormPage->addMember('vera');
        $this->projectFormPage->addMember('admin');

        $project = $this->projectFormPage->submitProjectForm();

        $projectName = $project->name->text();
        $this->assertTrue($project->name->text() == 'Test Project');
        $this->assertTrue($project->memberCount->text() == 2);
    }

    public function verifyProjectForm()
    {
        // verify basic content
        $this->assertTrue($this->projectFormPage->name->displayed());
        $this->assertTrue($this->projectFormPage->description->displayed());
        $this->assertTrue($this->projectFormPage->members->displayed());
        $this->assertTrue($this->projectFormPage->addBranchLink->displayed());
        $this->assertTrue($this->projectFormPage->jobFilter->displayed());
        $this->assertTrue($this->projectFormPage->enableTests->displayed());
        $this->assertTrue($this->projectFormPage->enableDeploy->displayed());

        $this->assertTrue($this->projectFormPage->save->displayed());
        $this->assertFalse($this->projectFormPage->save->enabled());
        $this->assertTrue($this->projectFormPage->cancel->displayed());

        // verify advanced functionality
        // look-ahead with project member name
        $this->projectFormPage->members = substr($this->p4users['vera']['User'], 0, 1);
        $this->assertTrue($this->projectFormPage->activeMember->displayed());
        $displayName = $this->p4users['vera']['User'] . ' (' . $this->p4users['vera']['FullName'] . ')';
        $this->assertTrue($this->projectFormPage->activeMember->text() == $displayName,
            '"' . $this->projectFormPage->activeMember->text() . ' is not equal to the expected value, "'
                . $displayName . '".'
        );
        $this->projectFormPage->activeMember->click();

        // verify added properly
        $elements = $this->projectFormPage->test->fetchElementsBy('css selector', $this->projectFormPage->locators['selectedMembers']);
        $this->assertSame(count($elements), 2, 'Unexpected count of selected team members.  ' . __FILE__ . ', ' . __LINE__);
        $this->assertTrue(
            $elements[1]->text() == $this->p4users['vera']['User'],
            '"' . $elements[1]->text() . '" is not equal to the expected value, "'
                . $this->p4users['vera']['User'] . '".'
        );
        $elements = $this->projectFormPage->test->fetchElementsBy('css selector', $this->projectFormPage->locators['hiddenMembers']);
        $this->assertSame(count($elements), 2, 'Unexpected count of selected team members.  ' . __FILE__ . ', ' . __LINE__);
        $this->assertTrue(
            $elements[1]->value() == $this->p4users['vera']['User'],
            '"' . $elements[1]->value() . '" is not equal to the expected value, "'
                . $this->p4users['vera']['User'] . '".'
        );

        // ensure we don't look up already selected users
        $this->projectFormPage->members = substr($this->p4users['vera']['User'], 0, 1);
        $this->assertFalse($this->projectFormPage->activeMember->displayed());

        // verify removal of user via UI
        $elements = $this->projectFormPage->test->fetchElementsBy('css selector', $this->projectFormPage->locators['removeSelectedMembers']);
        $this->assertSame(count($elements), 2, 'Unexpected count of selected team member remove buttons.  ' . __FILE__ . ', ' . __LINE__);
        $elements[1]->click();

        // clear the field, for later use
        $this->projectFormPage->members->clear();
    }
}