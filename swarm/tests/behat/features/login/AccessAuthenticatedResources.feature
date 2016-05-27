Feature: Verifying that a licensed p4d admin user can access Swarm's non-public resources

  Scenario Outline: Verify that a p4 admin user that has a valid Swarm license can access all
					Swarm resources (publicly available and authenticated resources).
					A HTTP response code of 200 indicates a successful page access.

	Given I setup p4d server connection
	When I login to swarm
	And I go to swarm "<swarm_page>" url
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
	| about      | 200              |
	| info       | 200              |
	# Invalid url for all users (authenticated/ unauthenticated)
	| junk       | 404              |
