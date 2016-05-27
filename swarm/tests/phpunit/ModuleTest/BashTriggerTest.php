<?php
/**
 * Test the bash trigger script.
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ModuleTest;

use P4\File\File;
use P4\Spec\Change;
use P4\Spec\Group;
use P4\Spec\Job;
use P4\Spec\Triggers;
use P4\Spec\User;
use P4\Uuid\Uuid;
use Reviews\Model\Review;

class BashTriggerTest extends TestControllerCase
{
    protected $scriptBasename   = 'swarm-trigger.sh';
    protected $scriptConfigFile = null;

    /**
     * Extends parent by creating p4 config file and a config to be used by the trigger script.
     */
    public function setUp()
    {
        // our trigger tests cause unwanted output on p4d's stderr which
        // on some platforms (e.g. mac) surfaces to the console, silence it!
        $this->noP4dStdErr = true;

        // run parent to set up perforce connection and prepare directories
        parent::setUp();

        // reset external triggers config file
        $this->configureScript();
    }

    public function testUsage()
    {
        // no arguments should produce usage
        exec($this->getScriptPath() . ' 2>&1', $output);
        $this->assertSame($this->getExpectedUsage(), implode("\n", $output));
        unset($output);

        // -t without -v should produce usage
        // first line should indicate error
        exec($this->getScriptPath() . ' -t commit 2>&1', $output);
        $first = array_shift($output);
        $this->assertRegExp("/no (ID )?value supplied/i", $first);
        $this->assertSame($this->getExpectedUsage(), implode("\n", $output));
        unset($output);

        // -v without -t should produce usage
        // first line should indicate error
        exec($this->getScriptPath() . ' -v 54321 2>&1', $output);
        $first = array_shift($output);
        $this->assertRegExp("/no event type supplied/i", $first);
        $this->assertSame($this->getExpectedUsage(), implode("\n", $output));
        unset($output);

        // -h should produce usage output
        exec($this->getScriptPath() . ' -h 2>&1', $output);
        $this->assertSame($this->getExpectedUsage(true), implode("\n", $output));
        unset($output);
    }

    /**
     * Startup a local web server and verify trigger pings it
     */
    public function testTaskQueuing()
    {
        $this->markTestSkipped();
        // built-in web-server requires php 5.4+ and posix_kill
        if (version_compare(PHP_VERSION, '5.4', '<') || !function_exists('posix_kill')) {
            $this->markTestSkipped('Requires PHP 5.4+ and posix_kill()');
        }

        // ensure we have a port between 1024 and 65535
        $port  = (getmypid() % 60000) + 1024;
        $token = strtoupper(new Uuid);
        $this->configureScript(
            array(
                'SWARM_HOST'  => '"http://localhost:' . $port . '"',
                'SWARM_TOKEN' => '"' . $token . '"'
            )
        );

        $this->installTriggers(
            array(
                array('job',        'job',    array()),
                array('user',       'user',   array()),
                array('userdel',    'user',   array()),
                array('group',      'group',  array()),
                array('groupdel',   'group',  array()),
                array('changesave', 'change', array()),
                array('shelve',     '//...',  array()),
                array('commit',     '//...',  array())
            )
        );

        // launch a web-server to monitor for requests from the trigger script
        $logFile = DATA_PATH . '/web-server.log';
        $pid     = $this->startWebServer($port, $logFile);

        try {
            // test job event
            $this->p4->disconnect(); // need to disconnect here or trigger won't fire
            $job = new Job;
            $job->setDescription('test')->save();
            $job = Job::fetch('job000001');

            $this->assertSame("test\n", $job->getDescription());
            $this->assertTaskQueued($logFile, $token);

            // test user add event
            $user = new User;
            $user->setId('jdoe')->setEmail('jdoe@host.com')->setFullName('J. Doe')->save();
            $this->assertTrue(User::exists('jdoe'));
            $this->assertTaskQueued($logFile, $token);

            // test user delete event
            $this->superP4->disconnect(); // need to disconnect here or trigger won't fire
            User::fetch('jdoe', $this->superP4)->delete();
            $this->assertFalse(User::exists('jdoe'));
            $this->assertTaskQueued($logFile, $token);

            // test group add event
            $group = new Group($this->superP4);
            $group->setId('trigger')->setOwners(array('tester'))->save();
            $this->assertTrue(Group::exists('trigger'));
            $this->assertTaskQueued($logFile, $token);

            // test group delete event
            Group::fetch('trigger', $this->superP4)->delete();
            $this->assertFalse(Group::exists('trigger'));
            $this->assertTaskQueued($logFile, $token);

            // test change save event
            $change = new Change;
            $change->setDescription('test')->save();
            $this->assertSame(1, $change->getId());
            $this->assertTaskQueued($logFile, $token);

            // test shelve commit event
            $file = new File;
            $file->setFilespec('//depot/foo')->setLocalContents('test')->add(1);
            $this->p4->run('shelve', array('-c', '1', '//...'));
            $result = $this->p4->run('fstat', array('-Rs', '-e', '1', '//...'));
            $this->assertSame('//depot/foo', $result->getData(0, 'depotFile'));
            $this->assertTaskQueued($logFile, $token);

            // test commit event
            $file = new File;
            $file->setFilespec('//depot/bar')->setLocalContents('test')->add()->submit('test');
            $result = $this->p4->run('files', array('//...'));
            $this->assertSame('//depot/bar', $result->getData(0, 'depotFile'));
            $this->assertTaskQueued($logFile, $token);
        } catch (\Exception $e) {
        }

        // now kill the web-server
        posix_kill($pid, 15);

        if (isset($e)) {
            throw $e;
        }
    }

    /**
     * Verify that config files are sourced as expected.
     * We will test it on local copy of the original trigger script with modified paths to config
     * scripts. We set different SWARM_TOKEN values in these scripts, fire triggers and check the
     * token value that was used to add a task to the queue (we will need a web-server for this test).
     */
    public function testConfigSource()
    {
        // built-in web-server requires php 5.4+ and posix_kill
        if (version_compare(PHP_VERSION, '5.4', '<') || !function_exists('posix_kill')) {
            $this->markTestSkipped('Requires PHP 5.4+ and posix_kill()');
        }

        // prepare a dir where we put our custom config files for testing
        $configDir         = DATA_PATH . '/config-scripts';
        $triggerScriptPath = $configDir . '/' . $this->scriptBasename;
        mkdir($configDir, 0777);

        // to test config sourcing, we don't want to place testing config scripts in the
        // default locations defined in the script; instead, we create a local copy of
        // the script and modify it to define paths pointing to locations of our choice and
        // then set this script to be used by triggers
        $candiateConfigPaths = array(
            $configDir . '/1.conf',
            $configDir . '/2.conf'
        );
        $this->adjustTriggerScript(
            $triggerScriptPath,
            array_map(
                function ($path) {
                    return '        "' . $path . '" \\';
                },
                $candiateConfigPaths
            ),
            array(
                150 => 'source_config',
                151 => '{',
                155 => '    for file in',
                158 => '        "$MYDIR/swarm-trigger.conf"'
            ),
            array(156, 157),
            155
        );

        // ensure we have a port between 1024 and 65535
        $port   = (getmypid() % 60000) + 1024;
        $config = array(
            'SWARM_HOST' => '"http://localhost:' . $port . '"'
        );
        $this->configureScript($config);
        $this->installTriggers(
            array(
                array('job', 'job', array())
            ),
            $triggerScriptPath
        );

        // launch a web-server to monitor for requests from the trigger script
        $logFile = DATA_PATH . '/web-server.log';
        $pid     = $this->startWebServer($port, $logFile);

        try {
            // test case #1: just a config passed to trigger via -c
            $token = strtoupper(new Uuid);
            $this->configureScript($config + array('SWARM_TOKEN' => $token));

            $this->p4->disconnect(); // need to disconnect here or trigger won't fire
            $job = new Job;
            $job->setDescription('test 1')->save();
            $this->assertTaskQueued($logFile, $token);

            // test case #2: 3 config files, ensure the last wins
            $token1 = strtoupper(new Uuid);
            $token2 = strtoupper(new Uuid);
            $token3 = strtoupper(new Uuid);
            $this->configureScript($config + array('SWARM_TOKEN' => $token3));
            file_put_contents($candiateConfigPaths[0], 'SWARM_TOKEN="' . $token1 . '"');
            file_put_contents($candiateConfigPaths[1], 'SWARM_TOKEN="' . $token2 . '"');

            $this->p4->disconnect(); // need to disconnect here or trigger won't fire
            $job = new Job;
            $job->setDescription('test 2')->save();
            $this->assertTaskQueued($logFile, $token3);

            // test case #3: 3 config files, but only one contains a token
            $token1 = strtoupper(new Uuid);
            $token2 = strtoupper(new Uuid);
            $this->configureScript($config + array('FOO' => 'bar'));
            file_put_contents($candiateConfigPaths[0], 'SWARM_TOKEN="' . $token1 . '"' . PHP_EOL);
            file_put_contents($candiateConfigPaths[1], 'NO_TOKEN="' . $token2 . '"' . PHP_EOL);

            $this->p4->disconnect(); // need to disconnect here or trigger won't fire
            $job = new Job;
            $job->setDescription('test 3')->save();
            $this->assertTaskQueued($logFile, $token1);

            // test case #4: no config passed via -c
            $triggers          = Triggers::fetch($this->superP4);
            $triggerJobCommand = '%quote%' . $triggerScriptPath . '%quote% -t job -v %formname%';
            $triggers->setTriggers(
                array(
                    array(
                        'name'    => 'job',
                        'type'    => 'form-commit',
                        'path'    => 'job',
                        'command' => $triggerJobCommand
                    )
                )
            )->save();

            $this->configureScript($config + array('SWARM_TOKEN' => $token));
            file_put_contents($candiateConfigPaths[1], file_get_contents($this->scriptConfigFile));

            $this->p4->disconnect(); // need to disconnect here or trigger won't fire
            $job = new Job;
            $job->setDescription('test 4')->save();
            $this->assertTaskQueued($logFile, $token);

            // test #5: config passed via -c but as a blank file
            $triggerJobCommand = '%quote%' . $triggerScriptPath . '%quote% -c %quote%%quote% -t job -v %formname%';
            $triggers->setTriggers(
                array(
                    array(
                        'name'    => 'job',
                        'type'    => 'form-commit',
                        'path'    => 'job',
                        'command' => $triggerJobCommand
                    )
                )
            )->save();

            $this->configureScript($config + array('SWARM_TOKEN' => $token1));
            file_put_contents($candiateConfigPaths[1], file_get_contents($this->scriptConfigFile));

            $this->p4->disconnect(); // need to disconnect here or trigger won't fire
            $job = new Job;
            $job->setDescription('test 5')->save();
            $this->assertTaskQueued($logFile, $token1);
        } catch (\Exception $e) {
        }

        // now kill the web-server
        posix_kill($pid, 15);

        if (isset($e)) {
            throw $e;
        }
    }

    /**
     * @dataProvider triggerTestProvider
     */
    public function testStrictAndEnforceTriggers(
        array $triggers,
        array $changeData,
        $expectedError = null,
        $preRunCallback = null
    ) {
        // configure trigger script
        $this->configureScript();

        // install triggers
        $this->installTriggers($triggers);

        // create a change to test on
        $change = new Change($this->p4);
        $change->setDescription(
            isset($changeData['description'])
            ? $changeData['description']
            : 'testing change'
        );

        // invoke pre-run callback if set
        if ($preRunCallback && is_callable($preRunCallback)) {
            $preRunCallback($this->superP4, $change);
        }

        // add specified files to the change
        foreach ((array) $changeData['files'] as $fileData) {
            $fileSpec = isset($fileData[0]) ? $fileData[0] : null;
            $content  = isset($fileData[1]) ? $fileData[1] : 'file content';

            $file = new File($this->p4);
            $file->setFilespec($fileSpec)->open()->setLocalContents($content);

            // change file type if set
            if (isset($fileData[2])) {
                $file->reopen(null, $fileData[2]);
            }

            $change->addFile($file);
        }

        // shelve the change to mimic how reviews are created by end user
        // this will also put files in Perforce (needed when trigger script
        // compares file contents by running fstat)
        $change->save();
        $this->p4->run('shelve', array('-c', $change->getId()));

        // create review from change if requested by the test
        $review = null;
        if (isset($changeData['createReview']) && $changeData['createReview']) {
            $review = Review::createFromChange($change, $this->p4)
                ->save()
                ->updateFromChange($change)
                ->save();
        }

        // execute on-before-submit callback if specified
        if (isset($changeData['onBeforeSubmit']) && is_callable($changeData['onBeforeSubmit'])) {
            call_user_func_array(
                $changeData['onBeforeSubmit'],
                array($change, $review)
            );
        }

        // submit the change (delete shelved files first)
        $this->p4->run('shelve', array('-d', '-c', $change->getId()));
        $exceptionCaught = null;
        try {
            $change->submit();
        } catch (\Exception $exceptionCaught) {
        }

        // verify expected error
        if (is_array($expectedError)) {
            // we were expecting an error, first verify that we indeed caught some exception
            $this->assertTrue($exceptionCaught instanceof \Exception);

            // verify exception class if specified
            if (isset($expectedError['exceptionClass'])) {
                $this->assertSame($expectedError['exceptionClass'], get_class($exceptionCaught));
            }

            // verify error message if specified
            if (isset($expectedError['message'])) {
                $this->assertTrue(
                    strpos($exceptionCaught->getMessage(), $expectedError['message']) !== false,
                    'Unexpected error message: ' . $exceptionCaught->getMessage()
                );
            }
        } else {
            // no error was expected, verify
            $this->assertTrue(
                is_null($exceptionCaught),
                $exceptionCaught
                ? "Unexpected exception: " . $exceptionCaught->getMessage()
                : ''
            );
        }
    }

    /**
     * @dataProvider strictTriggerWithKtextFilesProvider
     */
    public function testStrictTriggerWithKtextFiles()
    {
        $this->markTestSkipped(
            'Ktext files handling is not implemented in bash trigger script.'
        );
    }

    /**
     * Create test suite for testing strict and enforce triggers.
     *
     * @return  array
     */
    public function triggerTestProvider()
    {
        $changeData1 = array(
            'description' => 'test',
            'files'       => array(
                array('//depot/foo',     'abc'),
                array('//depot/a/foo',   '123'),
                array('//depot/a/b/foo', 'klm'),
            )
        );

        $changeData2 = array(
            'description' => 'test with ktext',
            'files'       => array(
                array('//depot/foo',     'abc', 'ktext'),
                array('//depot/a/foo',   '123', 'ktext'),
                array('//depot/a/b/foo', 'klm'),
            )
        );

        // build test data array
        $tests = array();
        foreach (array($changeData1, $changeData2) as $changeData) {
            // add tests for change not under review (should reject)
            $expectedError = array(
                'exceptionClass' => 'P4\Connection\Exception\CommandException',
                'message'        => 'Cannot find a Swarm review associated with this change'
            );
            foreach ($this->getTriggers('//depot/a/...') as $triggers) {
                foreach ($this->getVariants($changeData) as $variant) {
                    $tests[] = array(
                        $triggers,
                        $variant + array('createReview' => false),
                        $expectedError
                    );
                }
            }

            // add tests for change not under review but with -r flag set on trigger (should accept)
            foreach ($this->getTriggers('//depot/a/...', null, array('-r')) as $triggers) {
                foreach ($this->getVariants($changeData) as $variant) {
                    $tests[] = array(
                        $triggers,
                        $variant + array('createReview' => false),
                        null
                    );
                }
            }

            // add tests for change under un-approved review (should reject)
            $expectedError = array(
                'exceptionClass' => 'P4\Connection\Exception\CommandException',
                'message'        => 'Swarm review 2 for this change (1) is not approved'
            );
            foreach ($this->getTriggers('//depot/a/...') as $triggers) {
                foreach ($this->getVariants($changeData) as $variant) {
                    $tests[] = array(
                        $triggers,
                        $variant + array('createReview' => true),
                        $expectedError
                    );
                }
            }

            // add tests for change under approved review (should accept if unmodified files)
            foreach ($this->getTriggers('//depot/a/...') as $triggers) {
                $tests[] = array(
                    $triggers,
                    $changeData + array(
                        'createReview'   => true,
                        'onBeforeSubmit' => function ($change, $review) {
                           $review->setState(Review::STATE_APPROVED)->save();
                        }
                    ),
                    null
                );
            }

            // add tests for change under approved review with modified files (should accept for enforce)
            foreach ($this->getVariants($changeData) as $variant) {
                $tests[] = array(
                    current($this->getTriggers('//depot/a/...', array('enforce'))),
                    $changeData + array(
                        'createReview'   => true,
                        'onBeforeSubmit' => function ($change, $review) {
                           $review->setState(Review::STATE_APPROVED)->save();
                           $change->getFileObjects()->first()->setLocalContents('xyz');
                        }
                    ),
                    null
                );
            }

            // add tests for change under approved review with modified files (should reject for strict)
            $expectedError = array(
                'exceptionClass' => 'P4\Connection\Exception\CommandException',
                'message'        => 'The content of this change (1) does not match the content'
                    . ' of the associated Swarm review (2)'
            );
            $variants = $this->getVariants(
                $changeData,
                function ($change, $review) {
                    $review->setState(Review::STATE_APPROVED)->save();
                }
            );
            unset($variants['identical']);
            foreach ($this->getTriggers('//depot/a/...', array('strict', 'both')) as $triggers) {
                foreach ($variants as $variant) {
                    $tests[] = array(
                        $triggers,
                        $variant + array('createReview' => true),
                        $expectedError
                    );
                }
            }
        }

        // add special case for '-g <GROUP>' flag (if user is a member of a GROUP then triggers are bypassed)
        // test both cases when change user is/is-not a member of a GROUP
        foreach ($this->getTriggers('//depot/a/...', null, array('-g foo')) as $triggers) {
            foreach ($this->getVariants($changeData) as $variant) {
                $tests[] = array(
                    $triggers,
                    $variant + array('createReview' => false),
                    array(
                        'exceptionClass' => 'P4\Connection\Exception\CommandException',
                        'message'        => 'Cannot find a Swarm review associated with this change'
                    ),
                    function ($p4, Change $change) {
                        // create 'foo' group and put the change user as member
                        $group = new Group($p4);
                        $group->setId('foo')->addUser('foo')->save();
                    }
                );
                $tests[] = array(
                    $triggers,
                    $variant + array('createReview' => false),
                    null,
                    function ($p4, Change $change) {
                        // create 'foo' group and put the change user as member
                        $group = new Group($p4);
                        $group->setId('foo')->addUser($change->getUser())->save();
                    }
                );
            }
        }

        return $tests;
    }

    /**
     * Data provider for testing strict trigger with ktext files handling
     * Returns array with following keys:
     * review_files  - list of files (keyed with filespec) to create a review with
     *                 value is an array with 'type' (file type) and 'content' (local content)
     * submit_files  - list of files (specified in a same fashion as review_files) to submit
     *                 files will be submitted under the review created with review_files
     * should_fail   - boolean flag, true if the trigger is expected to prevent committing files
     *                 or false if trigger is expected to do nothing
     */
    public function strictTriggerWithKtextFilesProvider()
    {
        return array(
            'ktext-no-keywords-unchanged' => array(
                'review_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'ktext',
                        'content' => 'test no keywords'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text',
                        'content' => 'foo $Id$'
                    ),
                ),
                'submit_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'ktext',
                        'content' => 'test no keywords'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text',
                        'content' => 'foo $Id$'
                    ),
                ),
                'should_fail' => false
            ),
            'ktext-no-keywords-changed' => array(
                'review_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => 'abc'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text+k',
                        'content' => '1 2 3'
                    ),
                    '//depot/baz' => array(
                        'type'    => 'ktext',
                        'content' => 'no keywords'
                    ),
                ),
                'submit_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => 'abc'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text+k',
                        'content' => '1 2 3'
                    ),
                    '//depot/baz' => array(
                        'type'    => 'ktext',
                        'content' => 'no keywords changed'
                    ),
                ),
                'should_fail' => true
            ),
            'ktext-with-keywords-unchanged' => array(
                'review_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => '123'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text',
                        'content' => 'a b c'
                    ),
                    '//depot/baz' => array(
                        'type'    => 'text+k',
                        'content' => 'date $DateTime$ 1'
                    ),
                ),
                'submit_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => '123'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text',
                        'content' => 'a b c'
                    ),
                    '//depot/baz' => array(
                        'type'    => 'text+k',
                        'content' => 'date $DateTime$ 1'
                    ),
                ),
                'should_fail' => false
            ),
            'ktext-with-keywords-changed' => array(
                'review_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => '123'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text',
                        'content' => 'a b c'
                    ),
                    '//depot/baz' => array(
                        'type'    => 'text+k',
                        'content' => 'date $DateTime$ 1'
                    ),
                ),
                'submit_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => '123'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'text',
                        'content' => 'a b c'
                    ),
                    '//depot/baz' => array(
                        'type'    => 'text+k',
                        'content' => 'date $DateTime$ 2'
                    ),
                ),
                'should_fail' => true
            ),
            'ktext-missing-file' => array(
                'review_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => '123'
                    ),
                    '//depot/bar' => array(
                        'type'    => 'ktext',
                        'content' => 'a b c'
                    ),
                ),
                'submit_files' => array(
                    '//depot/foo' => array(
                        'type'    => 'text',
                        'content' => '123'
                    ),
                ),
                'should_fail' => true
            ),
        );
    }

    /**
     * Copy and tweak the trigger script.
     *
     * @param   string  $targetFilename     filename for the local script copy
     * @param   array   $configPaths        list with paths that will be inserted into the
     *                                      script as locations for the config files
     * @param   array   $verifyLines        list with strings keyed by line numbers
     *                                      these lines will be verified in the original script
     *                                      to ensure they contain given values
     * @param   array   $deleteLines        list of line number to remove in the local copy
     * @param   int     $insertAfterLine    line number where the new paths for config files will be inserted
     */
    protected function adjustTriggerScript(
        $targetFilename,
        array $configPaths,
        array $verifyLines,
        array $deleteLines,
        $insertAfterLine
    ) {
        // we just replace particular lines by their number
        // this will likely break if the script is modified, so before the replacement
        // we check some lines around to ensure they contain the correct data
        $this->verifyFileLines($this->getScriptPath(), $verifyLines);

        // copy the script and modify lines
        $source     = fopen($this->getScriptPath(), 'r');
        $target     = fopen($targetFilename,        'w');
        $lineNumber = 0;
        while (!feof($source)) {
            $lineNumber++;
            $line = fgets($source);

            // copy the line unless its a line for removal
            if (!in_array($lineNumber, $deleteLines)) {
                fwrite($target, $line);
            }

            // insert new line(s) with config path(s) if we are at the correct spot
            if ($lineNumber === $insertAfterLine) {
                foreach ($configPaths as $path) {
                    fwrite($target, $path . PHP_EOL);
                }
            }
        }

        fclose($source);
        fclose($target);

        // ensure script is executable
        chmod($targetFilename, 0755);
    }

    /**
     * Helper method to verify that given lines in the given file contain
     * given values.
     *
     * @param   string  $filename   filename to check for lines content
     * @param   array   $lines      list of lines to check; each item
     *                              contains line number in key and expected content in value
     *                              (the line in the file must begin with this value, but might be longer)
     */
    protected function verifyFileLines($filename, array $lines)
    {
        $lineNumbersToCheck = array_keys($lines);
        $lineNumber         = 0;
        $handle             = fopen($filename, 'r');
        while (!feof($handle) && $lineNumber < max($lineNumbersToCheck)) {
            $lineNumber++;
            $line = fgets($handle);

            if (array_key_exists($lineNumber, $lines)) {
                $this->assertTrue(
                    strpos($line, $lines[$lineNumber]) === 0,
                    "Script line $lineNumber doesn't match the expected value."
                    . "\nExpected: " . $lines[$lineNumber]
                    . "\n   Found: " . $line
                );
            }
        }

        fclose($handle);
    }

    /**
     * Start built-in web-server on a given port.
     *
     * @param   string|int  $port       port to start web server at
     * @param   string      $logFile    web-server log file (will log both stdout and stderr)
     * @return  int         pid of the web-server process
     */
    protected function startWebServer($port, $logFile)
    {
        // launch a web-server to monitor for requests from the trigger script
        $command = PHP_BINDIR . '/php -S localhost:' . $port . ' > ' . $logFile . ' 2>&1 & echo $!;';
        $pid     = exec($command, $output);

        // sleep to give the web-server a chance to startup
        usleep(250000);

        return $pid;
    }

    /**
     * Helper method to create variant for given $changeData. Intended for data provider
     * for testing strict/enforce triggers.
     *
     * @param   array           $changeData         change data
     * @param   callable|null   $onBeforeSubmit     coptional - before change submit callback
     * @return  array
     */
    protected function getVariants(array $changeData, $onBeforeSubmit = null)
    {
        $p4 = $this->p4;
        return array(
            // identical files
            'identical' => $changeData + array(
                'onBeforeSubmit' => function ($change, $review) use ($onBeforeSubmit) {
                    if (is_callable($onBeforeSubmit)) {
                        call_user_func_array($onBeforeSubmit, array($change, $review));
                    }
                }
            ),

            // one file modified
            'fileModified' => $changeData + array(
                'onBeforeSubmit' => function ($change, $review) use ($onBeforeSubmit) {
                    $change->getFileObjects()->first()->setLocalContents('xyz');

                    if (is_callable($onBeforeSubmit)) {
                        call_user_func_array($onBeforeSubmit, array($change, $review));
                    }
                }
            ),

            // one file added
            'fileAdded' => $changeData + array(
                'onBeforeSubmit' => function ($change, $review) use ($onBeforeSubmit, $p4) {
                    $file = new File($p4);
                    $file->setFilespec('//depot/newfile')->open()->setLocalContents('xyz 123');
                    $change->addFile($file);

                    if (is_callable($onBeforeSubmit)) {
                        call_user_func_array($onBeforeSubmit, array($change, $review));
                    }
                }
            ),

            // one file removed
            'fileRemoved' => $changeData + array(
                'onBeforeSubmit' => function ($change, $review) use ($onBeforeSubmit) {
                    $files = $change->getFiles();
                    array_shift($files);
                    $change->setFiles($files);

                    if (is_callable($onBeforeSubmit)) {
                        call_user_func_array($onBeforeSubmit, array($change, $review));
                    }
                }
            ),
        );
    }

    /**
     * Helper method to prepare list of triggers. Intended for data provider for testing
     * strict/enforce triggers.
     *
     * @param   string  $path   triggers path
     * @param   array   $types  optional - list of triggers types, recognized values are:
     *                           'strict'  for strict trigger
     *                           'enforce' for enforce trigger
     *                           'both'    for strict and enforce triggers
     * @param   array   $flags  optional - additional flags for triggers
     * @return  array
     */
    protected function getTriggers($path, array $types = null, $flags = array())
    {
        $types    = is_array($types) ? $types : array('strict', 'enforce', 'both');
        $triggers = array();

        if (in_array('strict', $types)) {
            $triggers[] = array(
                array('strict', $path, $flags)
            );
        }
        if (in_array('enforce', $types)) {
            $triggers[] = array(
                array('enforce', $path, $flags)
            );
        }
        if (in_array('both', $types)) {
            $triggers[] = array(
                array('strict',  $path, $flags),
                array('enforce', $path, $flags)
            );
        }

        return $triggers;
    }

    /**
     * Helper method to install triggers in Perforce.
     *
     * @param   array   $data   data for triggers, each set is supposed to be an array with 3 values:
     *                           - type    trigger pseudo-type (job, user, group etc. or enforce or strict)
     *                           - paths   list of paths for the trigger
     *                           - params  additional params to be appended to the trigger command
     *                                     by default, '-v %change%', '-t <type>' and '-c <config_file>'
     *                                     are automatically added
     */
    protected function installTriggers(array $data, $triggerScriptPath = null)
    {
        $triggerScriptPath = $triggerScriptPath ?: $this->getScriptPath();
        $triggers          = Triggers::fetch($this->superP4);
        $lines             = $triggers->getTriggers();
        $types             = array(
            'job'        => 'form-commit',
            'user'       => 'form-commit',
            'userdel'    => 'form-delete',
            'group'      => 'form-commit',
            'groupdel'   => 'form-delete',
            'changesave' => 'form-save',
            'shelve'     => 'shelve-commit',
            'commit'     => 'change-commit',
            'enforce'    => 'change-submit',
            'strict'     => 'change-content',
        );

        foreach ($data as $key => $triggerData) {
            if (!is_array($triggerData) || count($triggerData) !== 3) {
                throw new \Exception("Invalid triggers format.");
            }

            list($type, $paths, $params) = $triggerData;

            if (!in_array($type, array_keys($types))) {
                throw new \InvalidArgumentException(
                    "Invalid trigger type: " . implode(', ', array_keys($types)) . " are accepted."
                );
            }
            if (!is_array($params)) {
                throw new \InvalidArgumentException("Invalid trigger params: expecting an array.");
            }

            // mix provided options with defaults
            $options   = $params;
            $options[] = '-t ' . $type;
            $options[] = '-c %quote%' . $this->scriptConfigFile . '%quote%';
            $options[] = in_array($type, array('shelve', 'commit', 'enforce', 'strict'))
                ? '-v %change%'
                : '-v %formname%';

            foreach ((array) $paths as $path) {
                $lines[] = array(
                    'name'    => 'test.' . $type . '.' . $key,
                    'type'    => $types[$type],
                    'path'    => $path,
                    'command' => '%quote%' . $triggerScriptPath . '%quote% ' . implode(' ', $options)
                );
            }
        }

        $triggers->setTriggers($lines)->save();
    }

    protected function getScriptPath()
    {
        return BASE_PATH . '/p4-bin/scripts/' . $this->scriptBasename;
    }

    protected function configureScript(array $config = array())
    {
        $this->scriptConfigFile = DATA_PATH . '/trigger-script.config';

        $config += array(
            'ADMIN_USER'        => '"tester"',
            'ADMIN_TICKET_FILE' => '"' . DATA_PATH . '/p4tickets.txt"',
            'P4_PORT'           => "'" . $this->getP4Params('port') . "'"
        );

        $configLines = array_map(
            function ($lhs, $rhs) {
                return $lhs . '=' . $rhs;
            },
            array_keys($config),
            $config
        );

        file_put_contents($this->scriptConfigFile, implode("\n", $configLines));
    }

    protected function assertTaskQueued($logFile, $token)
    {
        usleep(250000);
        $logData = file_get_contents($logFile);
        $this->assertTrue(strpos($logData, '/queue/add/' . $token) !== false);
        file_put_contents($logFile, '');
    }

    protected function getExpectedUsage($help = false)
    {
        $scriptPath = $this->getScriptPath();
        $scriptName = basename($scriptPath);

        return <<<USAGE
Usage: $scriptName -t <type> -v <value> \
         [-p <p4port>] [-r] [-g <group-to-exclude>] [-c <config file>]
       $scriptName -o
    -t: specify the Swarm trigger type (e.g. job, shelve, commit)
    -v: specify the ID value
    -p: specify optional (recommended) P4PORT, only intended for
        '-t enforce' or '-t strict'
    -r: when using '-t strict' or '-t enforce', only apply this check
        to changes that are in review.
    -g: specify optional group to exclude for '-t enforce' or
        '-t strict'; members of this group, or subgroups thereof will
        not be subject to these triggers
    -c: specify optional config file to source variables
    -o: convenience flag to output the trigger lines

This script is meant to be called from a Perforce trigger. It should be placed
on the Perforce Server machine and the following entries should be added using
'p4 triggers' (use the -o flag to this script to only output these lines):

	swarm.job        form-commit   job    "%quote%$scriptPath%quote% -t job          -v %formname%"
	swarm.user       form-commit   user   "%quote%$scriptPath%quote% -t user         -v %formname%"
	swarm.userdel    form-delete   user   "%quote%$scriptPath%quote% -t userdel      -v %formname%"
	swarm.group      form-commit   group  "%quote%$scriptPath%quote% -t group        -v %formname%"
	swarm.groupdel   form-delete   group  "%quote%$scriptPath%quote% -t groupdel     -v %formname%"
	swarm.changesave form-save     change "%quote%$scriptPath%quote% -t changesave   -v %formname%"
	swarm.shelve     shelve-commit //...  "%quote%$scriptPath%quote% -t shelve       -v %change%"
	swarm.commit     change-commit //...  "%quote%$scriptPath%quote% -t commit       -v %change%"
	#swarm.enforce.1 change-submit  //DEPOT_PATH1/... "%quote%$scriptPath%quote% -t enforce -v %change% -p %serverport%"
	#swarm.enforce.2 change-submit  //DEPOT_PATH2/... "%quote%$scriptPath%quote% -t enforce -v %change% -p %serverport%"
	#swarm.strict.1  change-content //DEPOT_PATH1/... "%quote%$scriptPath%quote% -t strict -v %change% -p %serverport%"
	#swarm.strict.2  change-content //DEPOT_PATH2/... "%quote%$scriptPath%quote% -t strict -v %change% -p %serverport%"
Notes:

* The use of '%quote%' is not supported on 2010.2 servers (they are harmless
  though); if you're using this version, ensure you don't have any spaces in the
  pathname to this script.

* This script requires configuration to be set in an external configuration file
  or directly in the script itself, such as the Swarm host and token.
  By default, this script will source any of these config file:
    /etc/perforce/swarm-trigger.conf
    /opt/perforce/etc/swarm-trigger.conf
    swarm-trigger.conf (in the same directory as this script)
  Lastly, if -c <config file> is passed, that file will be sourced too.

* For 'enforce' triggers (enforce that a change to be submitted is tied to an
  approved review), or 'strict' triggers (verify that the content of a change to
  be submitted matches the content of its associated approved review), uncomment
  the appropriate lines and replace DEPOT_PATH as appropriate. For additional
  paths to check, increment the trigger name suffix so that each trigger name is
  named uniquely.

* For 'enforce' or 'strict' triggers, you can optionally specify a group whose
  members will not be subject to these triggers.

* For 'enforce' or 'strict' triggers, if your Perforce Server is SSL-enabled,
  add the "ssl:" protocol prefix to "%serverport%".

USAGE;
    }
}
