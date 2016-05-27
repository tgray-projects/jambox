Feature: Verifying that Swarm's publicly available resources should be accessible to all users

  Scenario Outline: Verify that an unauthenticated swarm user can reach all valid swarm public URLs

    Given I setup p4d server connection
    When I go to swarm "<swarm_page>" url
    Then I should see a HTTP response code of <HTTP_status_code>

  Examples:
    | swarm_page | HTTP_status_code |
    # Valid url for all swarm users
    | activity   | 200              |
    | home       | 200              |
    | reviews    | 200              |
    | files      | 200              |
    | changes    | 200              |
    | history    | 200              |
    | jobs       | 200              |
    # Valid urls for authenticated swarm user
    | about      | 401              |
    | info       | 403              |
    # Invalid url for all users (authenticated/ unauthenticated)
    | junk       | 404              |

