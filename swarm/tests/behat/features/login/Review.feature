Feature: Review
    As a p4 user I should be able to see changes shelved for review appear on swarm. As a user logged
    into swarm I should also be able to start reviews directly from the UI.

    Background:
        Given I setup p4d server connection

    @javascript
    Scenario Outline: Shelve a file with changelist description and verify that a review is or isn't
        generated as appropriate.

        When I shelve a file "file1.txt" with change description: "<description>"
        Then I should <outcome>

        Examples:
        | description |             outcome            |
        | #reviewfoo  | not see review activity listed |
        | foo#review  | not see review activity listed |
        | foo #review | see review activity listed     |
        | foo[review] | see review activity listed     |
        | [review]foo | see review activity listed     |

    @javascript
    Scenario: Request a review from the UI and verify that one is created.

        When I submit a file "file1.txt"
        And I login to swarm
        And I navigate to the page generated for that change
        Then I should see a "Request Review" button
        When I click on the "Request Review" button
        Then I should see a "View Review" button
        When I click on the "View Review" button
        Then I should be redirected to the page for that review

    Scenario: Shelve files for review and verify that the generated review page lists the correct
        description, files, and depot location.

        When I shelve files "MAIN/src/file1.txt, MAIN/src/file2.txt" with change description:
        """
        Adding important new text files. #review
        """
        And I navigate to the page generated for that review
        Then I should see the files "file1.txt, file2.txt" listed
        And I should see the depot location "MAIN/src" listed
        # the review keyword should be omitted in the review change description
        And I should see the change description "Adding important new text files."

    @javascript
    Scenario: Create a review that belongs to a project and verify that the project header is
        displayed on the review page.

        Given I login to swarm
        And I create a project "Jam" with mapping "//depot/Jam/..."
        When I shelve a file "Jam/file1.txt" for review
        And I navigate to the page generated for that review
        Then I should see the "Jam" project header

    @javascript
    Scenario: Shelve a change, then go back to add the review keyword. Verify that a review is only
        generated after re-shelving.

        When I shelve a file "file1.txt" with change description: "Hello World"
        And I edit the change description to add the #review keyword
        Then I should not see review activity listed
        When I re-shelve the change
        Then I should see review activity listed

    @javascript
    Scenario: Start a review with user mentions and verify that they are listed on the review page as
       reviewers.

        When I shelve a file "file1.txt" with change description:
        """
        This is a change for review with requested reviewers.
        @swarm-super @non-admin
        #review
        """
        And I navigate to the page generated for that review
        Then I should see users "swarm-super, non-admin" listed as reviewers

