<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

class ReviewContext extends AbstractContext
{

    const ELEMENT_ACTIVITY_FIRST_LINE = '.activity-stream .activity-type-review .first-line';

    protected $configParams = array();


    public function __construct(array $parameters = null)
    {
        $this->configParams = $parameters;
    }

    /**
     * @Then /^I should be redirected to the page for that review$/
     */
    public function iShouldBeRedirectedToTheReviewPage()
    {
        // automatically generated reviews IDs are one over the corresponding change ID
        $reviewId = 1 + $this->getMainContext()->getSubcontext('file_context')->getChangeId();
        $this->getSession()->visit($this->configParams['base_url']."/reviews/" . $reviewId);
    }

    /**
     * @Then /^I should see review activity listed$/
     */
    public function iShouldSeeReviewActivity()
    {
        $this->getSession()->reload();
        $this->getMinkContext()->waitUntilAjaxCallsComplete();

        $description = $this->getMainContext()->getSubcontext("file_context")->getChangeDescription();
        $css = self::ELEMENT_ACTIVITY_FIRST_LINE . ":contains('$description')";
        $this->getMinkContext()->assertElementOnPage($css);
    }

    /**
     * @Then /^I should not see review activity listed$/
     */
    public function iShouldNotSeeReviewActivity()
    {
        $this->getSession()->reload();
        $this->getMinkContext()->waitUntilAjaxCallsComplete();

        $description = $this->getMainContext()->getSubcontext("file_context")->getChangeDescription();
        $css = self::ELEMENT_ACTIVITY_FIRST_LINE . ":contains('$description')";
        $this->getMinkContext()->assertElementNotOnPage($css);
    }

    /**
     * @Then /^I should see that the review open task count is (\d+)$/
     */
    public function iShouldSeeOpenTaskCount($count)
    {
        $this->getMinkContext()->waitUntilAjaxCallsComplete();
        $this->getMinkContext()->assertElementOnPage(".tasks-open:contains('$count')");
    }

    /**
     * @Then /^I should see users "(?P<userList>[^"]*)" listed as reviewers$/
     */
    public function iShouldSeeReviewers($userList)
    {
        $userArray = explode(", ", $userList);
        foreach ($userArray as $user) {
            $this->getMinkContext()->assertElementOnPage(".reviewers a[href='/users/$user/']");
        }
    }
}