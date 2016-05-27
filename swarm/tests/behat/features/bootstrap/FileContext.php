<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace BehatTests;

use Behat\Gherkin\Node\PyStringNode;
use P4\Spec\Change;
use P4\File\File;

class FileContext extends AbstractContext
{
    protected $configParams = array();
    private $changeId;
    private $changeDescription;
    private $reviewKeywords       = array("#review", "[review]");


    public function __construct(array $parameters = null)
    {
        $this->configParams = $parameters;
    }

    /**
     * @return         string   the ID of the most recently submitted/shelved change
     */
    public function getChangeId()
    {
        return $this->changeId;
    }

    /**
     * @return         string   the changelist description of the most recently submitted/shelved change
     */
    public function getChangeDescription()
    {
        return $this->changeDescription;
    }

    /**
     * Function to set the description field in shelve/submit form
     *
     * @param  $flag   string   (review|default) Flag determines what description string the form will contain
     * @return         string   The actual string to be written in description field
     */
    private function setChangeFormDescription($flag)
    {
        switch ($flag) {
            case 'default':
                $this->changeDescription = "A simple change description";
                break;
            case 'review':
                $this->changeDescription = "A simple change description with " . $this->reviewKeywords[0] . " inserted";
                break;
        }

        return $this->changeDescription;
    }

    /**
     * Remove leading and trailing keywords from the given string and return result
     *
     * @param $description  string   the change description to be processed
     * @return              string   the description with all leading and trailing keywords omitted
     */
    private function stripKeywordsFromDescription($description)
    {
        $updated = $description;
        foreach ($this->reviewKeywords as $keyword) {
            $updated = ltrim($updated, $keyword);
            $updated = rtrim($updated, $keyword);

        }

        return trim($updated);
    }

    /**
     * @When /^I shelve(?: a)? file(?:s)? "(?P<fileList>[^"]*)" with change description:$/
     */
    public function iShelveAFileWithLongChangeDescription($fileList, PyStringNode $changeDescription)
    {
        $this->iShelveAFileWithChangeDescription($fileList, $changeDescription->getRaw());
    }

    /**
     * @When /^I shelve(?: a)? file(?:s)? "(?P<fileList>[^"]*)" with change description: "(?P<changeDescription>[^"]*)"$/
     */
    public function iShelveAFileWithChangeDescription($fileList, $changeDescription)
    {
        $files = explode(", ", $fileList);
        $change     = new Change;

        // create the files and add them to the changelist
        $connection = $this->getP4Context()->getAdminUserConnection();
        foreach ($files as $fileName) {
            $file = new File;
            $file->setFilespec('//depot/' . $fileName)->open()->setLocalContents("Sample Content");
            $change->addFile($file);
        }

        $change->setDescription($changeDescription)->save();

        $this->changeId = $change->getId();
        // leading and trailing keywords get trimmed out of the change description upon shelving
        $this->changeDescription = $this->stripKeywordsFromDescription($changeDescription);

        // shelve the change
        $connection->run('shelve', array('-c', $this->changeId));
        $this->getP4Context()->instantiateWorker();
    }

    /**
     * @When /^I submit a file "(?P<fileName>[^"]*)"$/
     */
    public function iSubmitAFile($fileName)
    {
        // create the file
        $connection = $this->getP4Context()->getAdminUserConnection();
        $file       = new File;
        $file->setFilespec('//depot/' . $fileName)->open()->setLocalContents("Sample Content");

        // create new changelist and set the description
        $change     = new Change;
        $change->addFile($file)->setDescription($this->setChangeFormDescription('default'))->save();

        $this->changeId = $change->getId();

        // commit the change
        $connection->run('submit', array('-c', $this->changeId));
        $this->getP4Context()->instantiateWorker();
    }

    /**
     * @When /^I shelve a file "(?P<fileName>[^"]*)" for review$/
     */
    public function iShelveAFileForReview($fileName)
    {
        $this->iShelveAFileWithContentsForReview($fileName, "Sample Content");
    }

    /**
     * @When /^I shelve a file "(?P<fileName>[^"]*)" with the following content for review:$/
     */
    public function iShelveAFileWithContentsForReview($fileName, $content)
    {
        // create the file
        $connection = $this->getP4Context()->getAdminUserConnection();
        $file       = new File;
        $file->setFilespec('//depot/' . $fileName)->open()->setLocalContents($content);

        // create new changelist and set the description for review
        $change     = new Change;
        $change->addFile($file)->setDescription($this->setChangeFormDescription('review'))->save();

        $this->changeId = $change->getId();

        // shelve the change
        $connection->run('shelve', array('-c', $this->changeId));
        $this->getP4Context()->instantiateWorker();
    }

    /**
     * @When /^I edit the change description to add the #review keyword$/
     */
    public function iEditTheDescriptionToAddReviewKeyword()
    {
        $connection = $this->getP4Context()->getAdminUserConnection();

        // get the current change form
        $oldChangeForm = $connection->run('change', array('-o', $this->changeId));
        $newChangeForm = current($oldChangeForm->getData());

        // modify the Description field
        $newChangeForm['Description'] = $this->changeDescription . " " . $this->reviewKeywords[0];

        // set the new change form
        $connection->run('change', '-i', $newChangeForm);
        $this->getP4Context()->instantiateWorker();
    }

    /**
     * @When /^I re-shelve the change$/
     */
    public function iReshelveTheChange()
    {
        $connection = $this->getP4Context()->getAdminUserConnection();

        $connection->run('shelve', array('-c', $this->changeId, '-f'));
        $this->getP4Context()->instantiateWorker();
    }
}
