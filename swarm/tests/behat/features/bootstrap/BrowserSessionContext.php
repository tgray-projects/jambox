<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

use Behat\Behat\Context\Step;

class BrowserSessionContext extends AbstractContext
{
    /**
     * Validate that Swarm's urls are correctly hit.
     *
     * @param  string $page  Swarm Page name
     *
     * @Given /^(?: |I) (?:hit the|am on|visit the|go to) swarm "(?P<page>[^"]*)" (?: |page|link|url)$/
     */
    public function iGoToSwarmPage($page = 'activity')
    {
        $url         = $page;
        $translation = array(
            'activity' => '',
            'home'     => '',
            'history'  => 'changes',
            'help'     => 'docs'
        );

        // relative URLs get converted to absolute ones
        if (preg_match('/https?:\/\//', $page) == false) {
            $page = isset($translation[$page]) ? $translation[$page] : $page;
            $url  = $this->configParams['base_url'] . '/' . $page;
        }

        $context = $this->getMinkContext();
        $context->getSession()->visit($url);
        $context->waitUntilPageUrlLoads($url);
    }

    /**
     * @Then /^I should see a HTTP response code of (\d+)$/
     */
    public function iShouldSeeAHttpStatusCode($statusCode)
    {
        assertEquals(
            $statusCode,
            $this->getMinkContext()->getHttpStatusCode(),
            "HTTP response code for url \"{$this->getSession()->getCurrentUrl()}\" does not match expected"
        );
    }
}
