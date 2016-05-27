Feature: Comments
    As a user I need the ability to comment on different things in swarm.
    These things are Changes, Reviews, and Jobs.
    For all of these I should be able to comment on them in some way.

    Background:
        Given I setup p4d server connection
        And I login to swarm

    #########################
    # Commenting on a Job
    #########################
    @javascript
    Scenario: Comment on a job and verify that it appears under the comments tab and under activity
        on the main page.

        Given create a job
        When I go to swarm "jobs/job000001" url
        Then I should see a Comments tab
        When I click on the Comments tab
        And I make the comment "Hello World"
        Then I should see the comment "Hello World"
        When I visit the swarm "home" page
        Then I should see comment activity with content "Hello World"

    @javascript
    Scenario: Make multiple comments on a job

        Given create a job
        When I go to swarm "jobs/job000001#comments" url
        And I make the comment "Comment 1"
        And I make the comment "Comment 2"
        And I make the comment "Comment 3"
        Then I should see 3 comments


    #########################
    # Commenting on a Review
    #########################

    @javascript
    Scenario: Comment on a review and verify that it appears in the following places: under the comments
        tab, under the history tab, under activity on the main page.

        Given I shelve a file "file1.txt" for review
        When I navigate to the page generated for that review
        Then I should see a Comments tab
        When I click on the Comments tab
        And I make the comment "Hello World"
        Then I should see the comment "Hello World"
        When I click on the History tab
        Then I should see comment activity with content "Hello World"
        When I visit the swarm "home" page
        Then I should see comment activity with content "Hello World"

    @javascript
    Scenario: Comment in the code and verify that comment appears both there and under the comments tab
        with the correct code line listed.

        Given I shelve a file "file1.txt" with the following content for review:
        """
        Some Title
        ===============
        Here is the first paragraph of my file.
        Lorem ipsum dolor sit amet, consectetur
        adipiscing elit.
        """
        And I navigate to the page generated for that review
        When I click on line 3 in the file "file1.txt"
        Then I should see a comment input box
        When I make the comment "Change this"
        Then I should see comment "Change this" after line 3 in the code
        When I click on the Comments tab
        Then I should see comment "Change this" made on file1.txt, line 3

    @javascript
    Scenario: Comment on a review, flagging the comment as a task. Verify that the review task count is
        incremented.

        Given I shelve a file "file1.txt" for review
        When I navigate to the page generated for that review
        Then I should see that the review open task count is 0
        When I click on the Comments tab
        And I type in "Hello World" in the comment input box
        But I flag the comment as a task before submitting it
        Then I should see that the review open task count is 1

    @javascript
    Scenario: Comment on a review, then archive comment and verify that it is hidden.

        Given I shelve a file "file1.txt" for review
        And I navigate to the page generated for that review
        When I click on the Comments tab
        And I make the comment "Hello World"
        Then I should see the comment "Hello World"
        When I archive the comment "Hello World"
        Then I should not see the comment "Hello World"
        And I should see that there is 1 archived comment

    @javascript
    Scenario: Start typing a comment, then switch tabs. Verify that upon going back to the comments tab,
        the input is still there.

        Given I shelve a file "file1.txt" for review
        And I navigate to the page generated for that review
        When I click on the Comments tab
        And I type in "Hello" in the comment input box
        And I click on the Files tab
        And I click on the Comments tab
        Then I should see "Hello" in the comment input box


    #########################
    # Commenting on a Change
    #########################

    @javascript
    Scenario: Comment on a commit and verify that it appears under the comments tab and under activity
        on the main page.

        Given I submit a file "file1.txt"
        When I navigate to the page generated for that change
        Then I should see a Comments tab
        When I click on the Comments tab
        And I make the comment "Hello World"
        Then I should see the comment "Hello World"
        When I visit the swarm "home" page
        Then I should see comment activity with content "Hello World"

