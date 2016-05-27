<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

class ChangeContext extends AbstractContext
{
    const ELEMENT_TABS_MENU      = 'ul.change-tabs';
    const ELEMENT_REVIEW_BUTTON  = '.change-header .popover-title a';
    const ELEMENT_FILENAME       = '.change-files span.filename';

    protected $configParams = array();


    public function __construct(array $parameters = null)
    {
        $this->configParams = $parameters;
    }

    /**
     * @When /^I navigate to the page generated for that (review|change)$/
     */
    public function iNavigateToTheGeneratedPage($changeType)
    {
        // get the change id from the file context
        $changeId = $this->getMainContext()->getSubcontext('file_context')->getChangeId();

        if ($changeType === "review") {
            // increment to get the corresponding review id
            $url = "/reviews/" . ++$changeId;
        } else {
            $url = "/changes/" . $changeId;
        }

        $this->getMinkContext()->visit($this->configParams['base_url'] . $url);
    }

    /**
     * @When /^I click on the (?P<tab>[^"]*) tab$/
     */
    public function iClickOnTab($tab)
    {
        $tabCss = self::ELEMENT_TABS_MENU . " a[href='#" . strtolower($tab) . "']";
        $this->getMinkContext()->findElementByType($tabCss, 'css')->click();

        $this->getMinkContext()->waitUntilPageElementLoads('#' . strtolower($tab) .'.active');
    }

    /**
     * @Then /^I should see a (?P<tab>[^"]*) tab$/
     */
    public function iShouldSeeATab($tab)
    {
        $tabCss = self::ELEMENT_TABS_MENU . " a[href='#" . strtolower($tab) . "']";
        $this->getMinkContext()->assertElementOnPage($tabCss);
    }

    /**
     * @Then /^I should see the file(s)? "(?P<fileList>[^"]*)" listed$/
     */
    public function iShouldSeeTheFilesListed($fileList)
    {
        $filesArray = explode(", ", $fileList);
        foreach ($filesArray as $file) {
            $this->getMinkContext()->assertElementOnPage(self::ELEMENT_FILENAME . ":contains('$file')");
        }
    }

    /**
     * @Then /^I should see the depot location "(?P<path>[^"]*)" listed$/
     */
    public function iShouldSeeTheDepotLocationListed($path)
    {
        $this->getMinkContext()->assertElementOnPage(".version-summary a:contains('$path')");
    }

    /**
     * @Then /^I click on line (\d+) in the file "(?P<fileName>[^"]*)"$/
     */
    public function iClickOnLineInFile($lineNumber, $fileName)
    {
        $this->getMinkContext()->scrollPageToElement(".diff-details");

        $page = $this->getSession()->getPage();

        // if there is only one file in the change, it will expand automatically
        // otherwise we need to click on it to expand it
        if (count($page->findAll('css', "#files.active .diff-wrapper")) != 1) {
            $page->find('css', ".change-files .diff-wrapper:contains('$fileName') i.icon-chevron-down")->click();
        }

        $this->getMinkContext()->waitUntilPageElementLoads('.diff-table');
        // note that we are treating the line number as relative to the newer version of the file
        $lines = $page->findAll('css', ".diff-table .lr$lineNumber .line-num[data-num='$lineNumber']");
        foreach ($lines as $line) {
            // there are multiple diff views but not all will be visible; it does not matter which one we click
            if ($line->isVisible()) {
                $line->click();
                return;
            }
        }
    }

    /**
     * @Then /^I should see the change description "(?P<description>[^"]*)"$/
     */
    public function iShouldSeeChangeDescription($description)
    {
        $this->getMinkContext()->assertElementOnPage(".change-description .first-line:contains('$description')");
    }

    /**
     * @Then /^I should see a "(?P<button>[^"]*)" button( [^"]*)?$/
     */
    public function iShouldSeeReviewButton($button)
    {
        $css = self::ELEMENT_REVIEW_BUTTON . ":contains('$button')";
        $this->getMinkContext()->assertElementOnPage($css);
    }

    /**
     * @When /^I click on the "(?P<button>[^"]*)" button( [^"]*)?$/
     */
    public function iClickOnTheReviewButton($button)
    {
        $css = self::ELEMENT_REVIEW_BUTTON . ":contains('$button')";
        $this->getMinkContext()->findElementByType($css, 'css')->click();

        $this->getMinkContext()->waitUntilAjaxCallsComplete();

    }
}