Feature: Projects
    As a logged in user I need to be able to create, edit, and delete projects.

    Background:
        Given I setup p4d server connection
        And I login to swarm


    ########################
    # Project Creation Tests
    ########################

    @javascript
    Scenario: Click the create a project button and verify the project creation page is brought up.

    When I click the "Add Project" button
    Then I should be redirected to the "Add Project" page


    @javascript
    Scenario: Go to the "Add Project page" and verify that the "Save" button
        only becomes enabled after all required fields are filled.

    When I am on a newly opened "Add Project" page
    Then The "Save" button should be disabled
    When I enter "SampleProject" in the "Name" field
    Then The "Save" button should be disabled
    When I add the member "swarm-admin" to the project
    Then The "Save" button should be enabled


    # Basic Flow
    @javascript
    Scenario: Go to the "Add Project" page, enter a name, description, branch, and member, and
        verify that all information is displayed correctly on the resulting project page and on
        the swarm home page.

    When I am on a newly opened "Add Project" page
    And I fill in the following:
        | Name            |   Vulcan             |
        | Description     |   This is planet X.  |
    And I add the member "swarm-admin" to the project
    And I add the member "non-admin" to the project
    And I add a branch with name "Main" and mapping "//depot/main/..."
    And I add a branch with name "Dev2" and mapping "//depot/dev2/..."
    And I click the "Save" button on the "Add Project" page
    Then I should be redirected to "/projects/vulcan"
    And I should see "This is planet X.", "2 Members", "2 Branches" under the "About" section
    And I should see users "swarm-admin, non-admin" under "members"
    And I should see branches "Main, Dev2" under "branches"
    When I visit the swarm "home" page
    Then I should see the project "Vulcan" listed under "Projects"


    # Creating project with a valid name
    @javascript
    Scenario Outline: Go to the "Add Project" page, enter a member and a valid project name and
        verify that the project saves successfully.

    When I am on a newly opened "Add Project" page
    And I enter "<name>" in the "Name" field
    And I add the member "swarm-admin" to the project
    And I click the "Save" button on the "Add Project" page
    # Redirection to the project page is used as an indication that the project was successfully created
    Then I should be redirected to "/projects/<project_page>"

    Examples: Valid Names
        # Names are valid if they contain at least one alphanumeric ASCII character, or if they
        # contain only UTF8 characters.

        |    name    |  project_page |
        | 3          |   3           |
        | ØÙ         |  øù           |
        | With Space |  with-space   |
        | %symbols/  |  symbols      |
        | sym$''bols |  sym-bols     |


    # Attempting to create project with invalid name
    @javascript
    Scenario Outline: Go to the "Add Project" page, enter a member and an invalid project name and
        verify that an error is displayed when attempting to save the project.

    When I am on a newly opened "Add Project" page
    And I enter <name> in the "Name" field
    And I add the member "swarm-admin" to the project
    And I click the "Save" button on the "Add Project" page
    Then I should see an error "<error>"


    Examples: Invalid Names
        # Names are invalid if they contain nothing but non-alphanumeric ASCII characters.

        |  name  |                       error                             |
        |   "  " |    Name is required and can't be empty.                 |
        |   "%@" |    Name must contain at least one letter or number.     |


    # Attempting to create a project with a name that already exists
    @javascript
    Scenario Outline: Go to the "Add Project" page, choose a member and a project name which is
        already in use and verify that an error is displayed when attempting to save the project.

    Given I have created a project named "sa^mple"
    When I am on a newly opened "Add Project" page
    And I enter "<name>" in the "Name" field
    And I add the member "swarm-admin" to the project
    And I click the "Save" button on the "Add Project" page
    Then I should see an error "This name is taken. Please pick a different name."

    Examples: Duplicate Names
        # Names are invalid if a project by that name already exists. Case is ignored, as are leading
        # and trailing non-alphanumeric ASCII characters. Any other non-alphanumeric ASCII characters
        # are translated to a "-" when determining uniqueness of the name.

        |    name      |
        |   sa^mple    |
        |   SA^MPLE    |
        |   sa#*%mple  |
        |  %&sa^mple   |


    # Attempting to create a project without a member
    @javascript
    Scenario: Go to the "Add Project" page, enter a project name, and type a name in the "Members"
        field but do not select any of the users from the drop down menu. Verify that an error is
        displayed when attempting to save the new project.

    When I am on a newly opened "Add Project" page
    And I enter "SampleProject" in the "Name" field
    And I enter "some-member" in the "Members" field
    And I click the "Save" button on the "Add Project" page
    Then I should see an error "Team must contain at least one member"


    # Verify functionality of member drop down list
    @javascript
    Scenario: Go to the "Add Project" page, start typing in the "Members" field and verify that a
        drop down appears with all matches. Select a member and verify that they are removed from the
        drop down. Remove the member and verify that they re-appear in the drop down.

    Given The list of users on the server includes "Michelle_Mackay, Victoria_Kerr, Nicola_Mills"
    When I am on a newly opened "Add Project" page
    And I enter "m" in the "Members" field
    Then I should see the user list "Michelle_Mackay, Nicola_Mills"
    When I click on user "Michelle_Mackay" in the dropdown list
    Then I should see "Michelle_Mackay"
    When I enter "m" in the "Members" field
    Then I should see the user list "Nicola_Mills"
    When I click on the "x" next to "Michelle_Mackay"
    Then I should not see "Michelle_Mackay"
    When I enter "m" in the "Members" field
    Then I should see the user list "Michelle_Mackay, Nicola_Mills"


    # Attempting to create a project with an invalid branch name or mapping
    @javascript
    Scenario Outline: Go to the "Add Project" page, enter a valid name and member, and add a branch
        with an invalid name or mapping. Verify that an error is displayed when attempting to save
        the project.

    When I am on a newly opened "Add Project" page
    And I enter a valid name and project member
    And I click the "Add Branch" button
    And I fill in the following:
        | branch-name-0  |  <name>   |
        | branch-paths-0 | <mapping> |
    And I click "Done" on the "Add Branch" window
    And I click the "Save" button on the "Add Project" page
    Then I should see an error "<error>"

    Examples:
        |    name     |     mapping         |            error                                                              |
        |    **       |    //depot/main/... |  Branch name must contain at least one letter or number.                      |
        |   main      |    //depot          |  Error in 'main' branch: Depot name must be followed by a path or '/...'.     |
        |   main      |    //depot/main/    |  Error in 'main' branch: The path cannot end with a '/'.                      |
        |   main      |    //dep/main/...   |  Error in 'main' branch: The first path component must be a valid depot name. |


    # Attempting to create a project with two branches of the same name
    @javascript
    Scenario: Go to the "Add Project" page, enter a valid name and member and add two branches with
        the same name. Verify that an error is displayed when attempting to save the project.

    When I am on a newly opened "Add Project" page
    And I enter a valid name and project member
    And I add a branch with name "main" and mapping "//depot/dev1/..."
    And I add a branch with name "main" and mapping "//depot/dev2/..."
    And I click the "Save" button on the "Add Project" page
    Then I should see an error "Two branches cannot have the same id. 'main' is already in use."













