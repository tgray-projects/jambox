<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

class CommentContext extends AbstractContext
{
     const ELEMENT_COMMENT                 = '.task-state-comment';
     const ELEMENT_COMMENT_BOX_NAMED       = 'body';
     const ELEMENT_COMMENT_BOX_CSS         = '.comment-form textarea';
     const ELEMENT_POST_BUTTON             = '.comment-form button[type="submit"]';
     const ELEMENT_COMMENT_FIRST_LINE_CSS  = '.comments-table .comment-text-wrapper .comment-body .first-line';
     const ELEMENT_COMMENT_CONTEXT_CSS     = '.comments-table .comment-text-wrapper .context a';

     private $commentCount = 0;

    /**
     * Posts a comment in the comment box on the current page.
     *
     * comment: The string that the comment will contain.
     *
     * @Given /^I make the comment "([^"]*)"$/
     */
    public function iMakeTheComment($comment)
    {
        $session = $this->getSession();
        $page = $session->getPage();

        $this->getMinkContext()->waitUntilPageElementLoads(self::ELEMENT_COMMENT_BOX_CSS, "css");
        $page->fillField(self::ELEMENT_COMMENT_BOX_NAMED, $comment);

        $this->getMinkContext()->findElementByType(self::ELEMENT_POST_BUTTON, 'css')->press();
        $this->commentCount++;

        $commentCss = "tr.c" . $this->commentCount . ".row-main" . self::ELEMENT_COMMENT;
        $this->getMinkContext()->waitUntilPageElementLoads($commentCss);

        $this->getP4Context()->instantiateWorker();
    }

    /**
     * @When /^I flag the comment as a task before submitting it$/
     */
    public function iFlagTheCommentAsATaskAndSubmit()
    {
        $this->getMinkContext()->checkOption("taskState");
        $this->getMinkContext()->findElementByType(self::ELEMENT_POST_BUTTON, 'css')->press();
    }

    /**
     * @Then /^I should see (?P<num>\d+) comments?$/
     */
    public function iShouldSeeNumberOfComments($commentCount)
    {
        $this->getMinkContext()->assertNumElements($commentCount, self::ELEMENT_COMMENT);
    }

    /**
     * @Then /^I should see that there is (\d)+ archived comment(s)?$/
     */
    public function iShouldSeeNumberOfArchivedComments($commentCount)
    {
        $actualCount = $this->getSession()->getPage()->find('css', ".comments-wrapper .closed-comments-header strong");
        assertEquals($commentCount, $actualCount->getHtml());
    }

    /**
     * @Then /^I should see a comment input box$/
     */
    public function iShouldSeeACommentInputBox()
    {
        $this->getMinkContext()->assertElementOnPage(self::ELEMENT_COMMENT_BOX_CSS);
    }

    /**
     * @When /^I type in "(?P<input>[^"]*)" in the comment input box$/
     */
    public function iTypeInTheCommentInputBox($comment)
    {
        $page = $this->getSession()->getPage();

        $this->getMinkContext()->waitUntilPageElementLoads(self::ELEMENT_COMMENT_BOX_CSS, "css");
        $page->fillField(self::ELEMENT_COMMENT_BOX_NAMED, $comment);
    }

    /**
     * @Then /^I should see "(?P<text>[^"]*)" in the comment input box$/
     */
    public function iShouldSeeTextInTheCommentInputBox($text)
    {
        $this->getMinkContext()->assertFieldContains(self::ELEMENT_COMMENT_BOX_NAMED, $text);
    }

    /**
     * @Then /^I should see the comment "(?P<content>[^"]*)"$/
     */
    public function iShouldSeeTheComment($content)
    {
        $this->getMinkContext()->assertElementOnPage(self::ELEMENT_COMMENT_FIRST_LINE_CSS . ":contains($content)");
    }

    /**
     * @Then /^I should see comment "(?P<content>[^"]*)" made on (?P<context>[^"]*)$/
     */
    public function iShouldSeeCommentWithContext($content, $context)
    {
        $this->getMinkContext()->waitUntilPageElementLoads("#comments.active");

        $this->getMinkContext()->assertElementOnPage(self::ELEMENT_COMMENT_FIRST_LINE_CSS . ":contains($content)");

        $contextCss = ".comments-table .comment-text-wrapper:contains('$content') .context a";
        $this->getMinkContext()->assertElementContainsText($contextCss, $context);
    }

    /**
     * @Then /^I should not see the comment "(?P<content>[^"]*)"$/
     */
    public function iShouldNotSeeTheComment($content)
    {
        $this->getMinkContext()->waitUntilAjaxCallsComplete();

        $comment = $this->getSession()->getPage()->find(
            'css',
            self::ELEMENT_COMMENT_FIRST_LINE_CSS . ":contains($content)"
        );
        assertFalse($comment->isVisible());
    }

    /**
     * @When /^I archive the comment "(?P<commentContent>[^"]*)"$/
     */
    public function iArchiveTheComment($content)
    {
        $css = ".comments-table td:contains('$content') button[data-original-title='Archive']";
        $this->getSession()->getPage()->find('css', $css)->click();
    }

    /**
     * @Then /^I should see comment activity with content "(?P<commentContent>[^"]*)"$/
     */
    public function iShouldSeeCommentActivityWithContent($commentContent)
    {
        $this->getMinkContext()->reload();
        $this->getMinkContext()->waitUntilAjaxCallsComplete();

        $this->getMinkContext()->assertElementOnPage(".activity-type-comment .first-line:contains('$commentContent')");
    }

    /**
     * @Then /^I should see comment "(?P<commentContent>[^"]*)" after line (\d+) in the code$/
     */
    public function iShouldSeeCommentInCode($commentContent, $lineNumber)
    {
        $css = ".diff-table tr.lr$lineNumber + .comments-section .comment-body .first-line";
        $this->getMinkContext()->assertElementOnPage($css . ":contains('$commentContent')");
    }
}
