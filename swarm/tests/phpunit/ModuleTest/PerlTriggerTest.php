<?php
/**
 * Test the perl trigger script.
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ModuleTest;

use P4\File\File;
use P4\Spec\Change;
use Reviews\Model\Review;

class PerlTriggerTest extends BashTriggerTest
{
    protected $scriptBasename = 'swarm-trigger.pl';

    /**
     * Test exempt files count configurable for strict/enforce triggers (with a change under review).
     */
    public function testExemptFileCountWithReview()
    {
        $this->installTriggers(
            array(
                array('job',        'job',    array()),
                array('user',       'user',   array()),
                array('userdel',    'user',   array()),
                array('group',      'group',  array()),
                array('groupdel',   'group',  array()),
                array('enforce',    '//...',  array()),
                array('strict',     '//...',  array())
            )
        );

        // create a change to review
        $change = new Change($this->p4);
        $change->setDescription('test');

        // add files
        for ($i = 0; $i < 2; $i++) {
            $file = new File($this->p4);
            $file->setFilespec('//depot/a' . $i)
                 ->open(null, 'text')
                 ->setLocalContents('abc' . $i);

            $change->addFile($file);
        }
        $change->save();

        // create a review
        $this->p4->run('shelve', array('-c', $change->getId()));
        $review = Review::createFromChange($change, $this->p4)
            ->save()
            ->updateFromChange($change)
            ->save();

        // at the first attempt, modify files and try to submit with no files exempt count set,
        // it should fail
        $change->revert();
        for ($i = 0; $i < 4; $i++) {
            $file = new File($this->p4);
            $file->setFilespec('//depot/a' . $i)
                 ->open(null, 'text')
                 ->setLocalContents('xyz' . $i);

            $change->addFile($file);
        }
        $change->save();

        // approve the review (otherwise commit will fail)
        $review->setState(Review::STATE_APPROVED)->save();

        // delete shelved files before committing
        $this->p4->run('shelve', array('-d', '-c', $change->getId()));

        // commit the modified change and verify the output
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertTrue(isset($e));

        // second attempt, set exempt files count (lower than number of files in change)
        // and try again, it should succeed
        unset($e);
        $this->configureScript(array('EXEMPT_FILE_COUNT' => 3));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertFalse(isset($e));
    }

    /**
     * Test exempt files count configurable for strict/enforce triggers (with change not under review).
     */
    public function testExemptFileCountWithNoReview()
    {
        $this->installTriggers(
            array(
                array('job',        'job',    array()),
                array('user',       'user',   array()),
                array('userdel',    'user',   array()),
                array('group',      'group',  array()),
                array('groupdel',   'group',  array()),
                array('enforce',    '//...',  array()),
                array('strict',     '//...',  array())
            )
        );

        // create a change to submit
        $change = new Change($this->p4);
        $change->setDescription('test');

        // add 3 files
        for ($i = 0; $i < 3; $i++) {
            $file = new File($this->p4);
            $file->setFilespec('//depot/a' . $i)
                 ->open(null, 'text')
                 ->setLocalContents('abc' . $i);

            $change->addFile($file);
        }
        $change->save();

        // commit the change and verify the output
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertTrue(isset($e));

        // second attempt, set exempt files count (lower than number of files in change)
        // and try again, it should succeed
        unset($e);
        $this->configureScript(array('EXEMPT_FILE_COUNT' => 2));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertFalse(isset($e));
    }

    /**
     * Test strict/enforce triggers in conjunction with EXEMPT_EXTENSIONS config option (with change under review).
     */
    public function testExemptExtensionsWithReview()
    {
        $this->configureScript();
        $this->installTriggers(
            array(
                array('job',        'job',    array()),
                array('user',       'user',   array()),
                array('userdel',    'user',   array()),
                array('group',      'group',  array()),
                array('groupdel',   'group',  array()),
                array('enforce',    '//...',  array()),
                array('strict',     '//...',  array())
            )
        );

        // create a change to review
        $change = new Change($this->p4);
        $change->setDescription('test');

        // add files
        for ($i = 0; $i < 3; $i++) {
            $file = new File($this->p4);
            $file->setFilespec('//depot/a' . $i)
                 ->open(null, 'text')
                 ->setLocalContents('abc' . $i);

            $change->addFile($file);
        }
        $change->save();

        // create a review
        $this->p4->run('shelve', array('-c', $change->getId()));
        $review = Review::createFromChange($change, $this->p4)
            ->save()
            ->updateFromChange($change)
            ->save();

        // at the first attempt, modify files and try to submit with no exempt extensions
        // specified, it should fail
        $change->revert();
        $file1 = new File($this->p4);
        $file1->setFilespec('//depot/foo.ext1')
             ->open(null, 'text')
             ->setLocalContents('abc');

        $file2 = new File($this->p4);
        $file2->setFilespec('//depot/bar.ext2')
             ->open(null, 'text')
             ->setLocalContents('xyz');

        $change->addFile($file1)->addFile($file2)->save();

        // approve the review
        $review->setState(Review::STATE_APPROVED)->save();

        // delete shelved files before committing
        $this->p4->run('shelve', array('-d', '-c', $change->getId()));

        // commit the modified change and verify the output
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $errorMessage = 'content of this change (1) does not match the content of the associated Swarm review (2)';
        $this->assertTrue(isset($e));
        $this->assertTrue(stripos($e->getMessage(), $errorMessage) !== false);

        // second attempt, set exemopt extensions to 'ext1', it still should fail
        unset($e);
        $this->configureScript(array('EXEMPT_EXTENSIONS' => 'ext1'));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertTrue(isset($e));
        $this->assertTrue(stripos($e->getMessage(), $errorMessage) !== false);

        // third attempt - verify literal dot appears before the extension
        $file = new File($this->p4);
        $file->setFilespec('//depot/foo-ext1')
             ->open(null, 'text')
             ->setLocalContents('xyz');
        $change->setFiles(array($file))->save();

        unset($e);
        $this->configureScript(array('EXEMPT_EXTENSIONS' => 'ext1'));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertTrue(isset($e));
        $this->assertTrue(stripos($e->getMessage(), $errorMessage) !== false);

        // fourth attempt, set exempt extensions to 'ext1, ext2, ext3', it should succeed
        $change->setFiles(array($file1, $file2))->save();

        unset($e);
        $this->configureScript(array('EXEMPT_EXTENSIONS' => '.EXT1, .ext2,ext3'));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
            print $e->getMessage();
            exit;
        }

        $this->assertFalse(isset($e));
    }

    /**
     * Test strict/enforce triggers in conjunction with EXEMPT_EXTENSIONS config option (with change not under review).
     */
    public function testExemptExtensionsWithNoReview()
    {
        $this->configureScript();
        $this->installTriggers(
            array(
                array('job',        'job',    array()),
                array('user',       'user',   array()),
                array('userdel',    'user',   array()),
                array('group',      'group',  array()),
                array('groupdel',   'group',  array()),
                array('enforce',    '//...',  array()),
                array('strict',     '//...',  array())
            )
        );

        // create a change and few files
        $change = new Change($this->p4);
        $change->setDescription('test');

        $file1 = new File($this->p4);
        $file1->setFilespec('//depot/foo.ext1')
             ->open(null, 'text')
             ->setLocalContents('abc');

        $file2 = new File($this->p4);
        $file2->setFilespec('//depot/bar.ext2')
             ->open(null, 'text')
             ->setLocalContents('xyz');

        // at the first attempt, try to submit with no exempt extensions specified, it should fail
        $change->addFile($file1)->addFile($file2)->save();

        // commit the change and verify the output
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $errorMessage = 'Cannot find a Swarm review associated with this change (1)';
        $this->assertTrue(isset($e));
        $this->assertTrue(stripos($e->getMessage(), $errorMessage) !== false);

        // second attempt, set exempt extensions to 'ext1', it still should fail
        unset($e);
        $this->configureScript(array('EXEMPT_EXTENSIONS' => 'ext1'));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertTrue(isset($e));
        $this->assertTrue(stripos($e->getMessage(), $errorMessage) !== false);

        // third attempt - verify literal dot appears before the extension
        $file = new File($this->p4);
        $file->setFilespec('//depot/foo-ext1')
             ->open(null, 'text')
             ->setLocalContents('xyz');
        $change->setFiles(array($file))->save();

        unset($e);
        $this->configureScript(array('EXEMPT_EXTENSIONS' => 'ext1'));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
        }

        $this->assertTrue(isset($e));
        $this->assertTrue(stripos($e->getMessage(), $errorMessage) !== false);

        // fourth attempt, set exempt extensions to 'ext1, ext2, ext3', it should succeed
        $change->setFiles(array($file1, $file2))->save();

        unset($e);
        $this->configureScript(array('EXEMPT_EXTENSIONS' => '.EXT1, .ext2,ext3'));
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
            print $e->getMessage();
            exit;
        }

        $this->assertFalse(isset($e));
    }

    /**
     * @dataProvider strictTriggerWithKtextFilesProvider
     */
    public function testStrictTriggerWithKtextFiles(array $reviewFiles, array $submitFiles, $shouldFail)
    {
        $this->configureScript();
        $this->installTriggers(
            array(
                array('job',        'job',    array()),
                array('user',       'user',   array()),
                array('userdel',    'user',   array()),
                array('group',      'group',  array()),
                array('groupdel',   'group',  array()),
                array('enforce',    '//...',  array()),
                array('strict',     '//...',  array())
            )
        );

        // create a change to review
        $change = new Change($this->p4);
        $change->setDescription('strict test');

        // add files as specified
        foreach ($reviewFiles as $fileSpec => $properties) {
            $content = isset($properties['content']) ? $properties['content'] : '';
            $type    = isset($properties['type'])    ? $properties['type']    : null;

            $file = new File($this->p4);
            $file->setFilespec($fileSpec)
                 ->open(null, $type)
                 ->setLocalContents($content);

            $change->addFile($file);
        }
        $change->save();

        // create a review from the change
        $this->p4->run('shelve', array('-c', $change->getId()));
        $review = Review::createFromChange($change, $this->p4)
            ->save()
            ->updateFromChange($change)
            ->save();

        // modify a change (that is now associated with a review) with files as specified and try to commit it
        $change->revert();
        foreach ($submitFiles as $fileSpec => $properties) {
            $content = isset($properties['content']) ? $properties['content'] : '';
            $type    = isset($properties['type'])    ? $properties['type']    : null;

            $file = new File($this->p4);
            $file->setFilespec($fileSpec)
                 ->open(null, $type)
                 ->setLocalContents($content);

            $change->addFile($file);
        }
        $change->save();

        // approve the review (otherwise commit will fail)
        $review->setState(Review::STATE_APPROVED)->save();

        // delete shelved files before committing
        $this->p4->run('shelve', array('-d', '-c', $change->getId()));

        // commit the modified change and verify the output
        try {
            $change->submit();
        } catch (\P4\Connection\Exception\CommandException $e) {
            // if trigger prevents committing the change, this is the exception class thrown
            // we verify the message later
        }

        // verify the output
        if ($shouldFail) {
            $this->assertTrue(isset($e));
            $this->assertTrue(
                strpos(
                    $e->getMessage(),
                    "The content of this change (1) does not match the content of the associated Swarm review (2)"
                ) !== false
            );
        } else {
            // should it not fail, ensure we didn't catch any exception
            $this->assertFalse(isset($e), "Unexpected trigger behaviour (should not fail).");
        }
    }

    protected function getExpectedUsage($help = false)
    {
        $usage = parent::getExpectedUsage();

        // perl trigger has a short version of usage unless help output requested
        return $help ? $usage : current(explode("\nThis script is", $usage));
    }

    protected function adjustTriggerScript(
        $targetFilename,
        array $configPaths,
        array $verifyLines,
        array $deleteLines,
        $insertAfterLine
    ) {
        // we were called with values for bash trigger script, we need to change some
        // of them here according to the perl trigger script
        $offset      = 688;
        $verifyLines = array(
            $offset     => 'sub parse_config {',
            $offset + 1 => '    my @candidates = (',
            $offset + 4 => '        "$MY_PATH/swarm-trigger.conf"'
        );
        $deleteLines     = array($offset + 2, $offset + 3);
        $insertAfterLine = $offset + 1;

        // remove trailing \ from config paths and add punctuation at the end
        $configPaths = array_map(
            function ($path) {
                return rtrim($path, ' \\') . ',';
            },
            $configPaths
        );

        return parent::adjustTriggerScript(
            $targetFilename,
            $configPaths,
            $verifyLines,
            $deleteLines,
            $insertAfterLine
        );
    }
}
