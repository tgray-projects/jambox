<?php
/**
 * Test methods for the P4 Job class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Job;
use P4\Spec\User;
use P4\Log\Logger;
use P4\Spec\Client;
use P4\Spec\Exception\NotFoundException;
use Zend\Log\Logger as ZendLogger;
use Zend\Log\Writer\Mock as MockLog;

class JobTest extends TestCase
{
    /**
     * Test fetching a job.
     */
    public function testFetch()
    {
        // ensure fetch fails for a non-existant job.
        $jobId = 'alskdfj2134';
        try {
            Job::fetch($jobId);
            $this->fail('Fetch should fail for a non-existant job.');
        } catch (NotFoundException $e) {
            $this->assertSame(
                "Cannot fetch job $jobId. Record does not exist.",
                $e->getMessage(),
                'Expected error fetching a non-existant job.'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected exception fetching a non-existant job.');
        }

        // ensure fetch fails with an empty id
        $jobId = '';
        try {
            Job::fetch($jobId);
            $this->fail('Unexpected success fetching an empty job id.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(
                'Must supply a valid id to fetch.',
                $e->getMessage(),
                'Expected error fetching an empty job id.'
            );
        } catch (\Exception $e) {
            $this->fail('Unexpected exception fetching an empty job id.');
        }
    }

    /**
     * Verify one command populates a job.
     */
    public function testFetchOneCommand()
    {
        $job = new Job;
        $description = "test job for fetching\n";
        $job->set('Description', $description)->save();
        try {
            $job = Job::fetch('job000001');
            $this->assertSame($description, $job->getDescription());
        } catch (\Exception $e) {
            $this->fail("Unexpected exception fetching a job.");
        }

        // ensure it only takes one command to fetch a job and read
        // basic values from it -- we verify this by peeking at the log
        $original = Logger::hasLogger() ? Logger::getLogger() : null;
        $logger   = new ZendLogger;
        $mock     = new MockLog;
        $logger->addWriter($mock);
        Logger::setLogger($logger);

        $fetched = Job::fetch('job000001');
        $fetched->get();
        $this->assertSame(1, count($mock->events));

        // restore original logger if there is one.
        Logger::setLogger($original);
    }

    /**
     * Test exists.
     */
    public function testExists()
    {
        // ensure id-exists returns false for non-existant job
        $this->assertFalse(Job::exists('alsdjf'), 'Given job id should not exist.');

        // ensure id-exists returns false for invalid job
        $this->assertFalse(Job::exists('-job1'), 'Invalid job id should not exist.');

        // create job and ensure it exists.
        $job = new Job;
        $job->set('Description', 'test')->save();
        $this->assertTrue(Job::exists('job000001'), 'Given job id should exist.');
    }

    /**
     * Test saving a job.
     */
    public function testSave()
    {
        $job = new Job;
        $description = 'test!';
        $job->set('Description', $description);

        // demonstrate that pre-save description is unmodified.
        $this->assertSame(
            $description,
            $job->get('Description'),
            'Expected pre-fetch description'
        );

        $job->save();
        $firstId = 'job000001';
        $this->assertSame($firstId, $job->getId(), 'Expected id');

        $job = Job::fetch($firstId);
        $this->assertSame($firstId, $job->getId(), 'Expected id');

        // demonstrate that post-save description has had whitespace
        // management performed by the server.
        $this->assertSame(
            "$description\n",
            $job->get('Description'),
            'Expected post-fetch description'
        );
    }

    /**
     * Test deleting a job.
     */
    public function testDelete()
    {
        // make a few jobs
        $expectedIds = array();
        $expectedDescriptions = array();
        for ($i = 0; $i < 5; $i++) {
            $job = new Job;
            $description = "job $i\n";
            $job->set('Description', $description);
            $job->save();
            $expectedIds[] = $job->getId();
            $expectedDescriptions[] = $description;
        }

        $jobs = Job::fetchAll();
        $this->assertTrue($jobs->count() == 5, 'Expected job count');
        $jobIds = array();
        $descriptions = array();
        foreach ($jobs as $job) {
            $jobIds[] = $job->getId();
            $descriptions[] = $job->get('Description');
        }
        $this->assertSame(
            $expectedIds,
            $jobIds,
            'Expected job ids'
        );
        $this->assertSame(
            $expectedDescriptions,
            $descriptions,
            'Expected job descriptions'
        );
        $theId = $jobs->nth(2)->getId();
        $this->assertTrue(
            Job::exists($theId),
            'Given job id should exist.'
        );

        // now delete a job
        $job = $jobs->nth(2);
        $job->delete();

        // adjust expectations
        array_splice($expectedIds, 2, 1);
        array_splice($expectedDescriptions, 2, 1);

        // refetch and test that the deleted job no longer exists
        // and that the non-deleted jobs still exist
        $jobs = Job::fetchAll();
        $this->assertTrue($jobs->count() == 4, 'Expected job count');
        $jobIds = array();
        $descriptions = array();
        foreach ($jobs as $job) {
            $jobIds[] = $job->getId();
            $descriptions[] = $job->get('Description');
        }
        $this->assertSame(
            $expectedIds,
            $jobIds,
            'Expected job ids'
        );
        $this->assertSame(
            $expectedDescriptions,
            $descriptions,
            'Expected job descriptions'
        );
        $this->assertFalse(
            Job::exists($theId),
            'Given job id should not exist.'
        );
    }

    /**
     * Test fetchAll.
     */
    public function testFetchAll()
    {
        $expectedJobIds = array();
        $expectedDescriptions = array();
        for ($i = 0; $i < 10; $i++) {
            $job = new Job;
            $description = "test job $i";
            $job->set('Description', $description);
            $job->save();
            $expectedDescriptions[] = "$description\n";
            $expectedJobIds[] = $job->getId();
        }

        $jobs = Job::fetchAll();
        $this->assertTrue($jobs->count() == 10, 'Expected job count');
        $jobIds = array();
        $descriptions = array();
        foreach ($jobs as $job) {
            $jobIds[] = $job->getId();
            $descriptions[] = $job->get('Description');
        }
        $this->assertSame(
            $expectedJobIds,
            $jobIds,
            'Expected job ids'
        );
        $this->assertSame(
            $expectedDescriptions,
            $descriptions,
            'Expected job descriptions'
        );
    }

    /**
     * get/set value are tested already for code indexed mutators. Test them for
     * fields without mutator/accessors.
     */
    public function testGetSet()
    {
        // add the field 'NewField' to do our testing on.
        $fields = \P4\Spec\Definition::fetch('job')->getFields();
        $fields['NewField'] = array (
            'code' => '110',
            'dataType' => 'word',
            'displayLength' => '32',
            'fieldType' => 'required',
        );
        \P4\Spec\Definition::fetch('job')->setFields($fields)->save();

        // test in memory object
        $job = new Job;
        $job->setDescription('test');

        $this->assertSame(
            null,
            $job->get('NewField'),
            'Expected matching starting value'
        );

        $job->set('NewField', 'test');

        $this->assertSame(
            'test',
            $job->get('NewField'),
            'Expected matching value after set'
        );

        // save it and refetch to verify it is still good
        $job->save();
        $job = Job::fetch($job->getId());

        $this->assertSame(
            'test',
            $job->get('NewField'),
            'Expected matching value after save/fetch'
        );
    }

    /**
     * Test setting invalid inputs for Status/User/Description
     */
    public function testBadSetStatusUserDescription()
    {
        $methods = array(
            'setStatus'      => 'Status must be a string or null',
            'setUser'        => 'User must be a string, P4\Spec\User or null',
            'setDescription' => 'Description must be a string or null',
        );

        $tests = array(
            array(
                'title' => __LINE__.' int',
                'value' => 10
            ),
            array(
                'title' => __LINE__.' bool',
                'value' => true
            ),
            array(
                'title' => __LINE__.' float',
                'value' => 10.0
            ),
            array(
                'title' => __LINE__.' P4\Spec\Client',
                'value' => Client::fetchAll(array(Client::FETCH_MAXIMUM => 1))
            ),
        );

        foreach ($methods as $method => $expectedError) {
            foreach ($tests as $test) {
                try {
                    $job = new Job;

                    $job->{$method}($test['value']);

                    $this->fail(
                        $method.' '.$test['title'].': Unexpected success'
                    );
                } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                    $this->fail($e->getMessage());
                } catch (\InvalidArgumentException $e) {
                    $this->assertSame(
                        $expectedError,
                        $e->getMessage(),
                        $method.' '.$test['title'].': unexpected exception message'
                    );
                } catch (\Exception $e) {
                    $this->fail(
                        $method.' '.$test['title'].
                        ': unexpected exception ('. get_class($e) .') '.
                        $e->getMessage()
                    );
                }
            }
        }
    }

    /**
     * Test setting valid inputs for Status
     */
    public function testGoodSetStatus()
    {
        $tests = array(
            array(
                'title' => __LINE__.' empty string',
                'value' => ''
            ),
            array(
                'title' => __LINE__.' null',
                'value' => null
            ),
            array(
                'title' => __LINE__.' valid string',
                'value' => 'closed',
                'canSave' => true,
            ),
            array(
                'title' => __LINE__.' valid string',
                'value' => 'suspended',
                'canSave' => true,
            ),
            array(
                'title' => __LINE__.' valid string',
                'value' => 'open',
                'canSave' => true,
            ),
        );

        foreach ($tests as $test) {
            $title = $test['title'];
            $value = $test['value'];
            $out   = array_key_exists('out', $test) ? $test['out'] : $test['value'];

            $job = new Job;

            // in memory test via setStatus
            $job->setStatus($value);
            $this->assertSame(
                $out,
                $job->getStatus(),
                $title.': expected to match set value'
            );

            $this->assertSame(
                $out,
                $job->get('Status'),
                $title.': Expected get() to match'
            );

            // via set comparison
            $job = new Job;
            $job->set('Status', $value);
            $this->assertSame(
                $out,
                $job->get('Status'),
                $title.': Expected getStatus to match get'
            );

            $this->assertSame(
                $out,
                $job->getStatus(),
                $title.': Expected set to match getStatus'
            );

            if (array_key_exists('canSave', $test)) {
                // post save test
                $job->setDescription('blah')->setUser('user1')->save();
                $this->assertSame(
                    $out,
                    $job->getStatus(),
                    'Expected getStatus to match post save'
                );

                $this->assertSame(
                    $out,
                    $job->get('Status'),
                    'Expected get(Status) to match post save'
                );

                // post fetch test
                $job = Job::fetch($job->getId());
                $this->assertSame(
                    $out,
                    $job->getStatus(),
                    'Expected getStatus to match post fetch'
                );

                $this->assertSame(
                    $out,
                    $job->get('Status'),
                    'Expected get(Status) to match post fetch'
                );
            }
        }
    }

    /**
     * Test setting valid inputs for User
     */
    public function testGoodSetUser()
    {
        $user = new User;
        $user->setId('bob');
        $tests = array(
            array(
                'title' => __LINE__.' empty string',
                'value' => ''
            ),
            array(
                'title' => __LINE__.' null',
                'value' => null
            ),
            array(
                'title' => __LINE__.' valid string',
                'value' => 'user1',
                'canSave' => true,
            ),
            array(
                'title' => __LINE__.' valid object',
                'value' => $user,
                'out'   => $user->getId(),
                'canSave' => true,
            ),
        );

        foreach ($tests as $test) {
            $title = $test['title'];
            $value = $test['value'];
            $out   = array_key_exists('out', $test) ? $test['out'] : $test['value'];

            $job = new Job;

            // in memory test via setField
            $job->setUser($value);
            $this->assertSame(
                $out,
                $job->getUser(),
                $title.': expected to match set value'
            );

            $this->assertSame(
                $out,
                $job->get('User'),
                $title.': Expected get() to match'
            );

            // via set comparison
            $job = new Job;
            $job->set('User', $value);
            $this->assertSame(
                $out,
                $job->get('User'),
                $title.': Expected to match get'
            );

            $this->assertSame(
                $out,
                $job->getUser(),
                $title.': Expected set to match getUser'
            );

            if (array_key_exists('canSave', $test)) {
                // post save test
                $job->setDescription('blah')->setStatus('open')->save();
                $this->assertSame(
                    $out,
                    $job->getUser(),
                    'Expected getUser to match post save'
                );

                $this->assertSame(
                    $out,
                    $job->get('User'),
                    'Expected get(User) to match post save'
                );

                // post fetch test
                $job = Job::fetch($job->getId());
                $this->assertSame(
                    $out,
                    $job->getUser(),
                    'Expected getUser to match post fetch'
                );

                $this->assertSame(
                    $out,
                    $job->get('User'),
                    'Expected get(User) to match post fetch'
                );
            }
        }
    }

    /**
     * Test setting valid inputs for Description
     */
    public function testGoodSetDescription()
    {
        $tests = array(
            array(
                'title' => __LINE__.' empty string',
                'value' => ''
            ),
            array(
                'title' => __LINE__.' null',
                'value' => null
            ),
            array(
                'title' => __LINE__.' valid string',
                'value' => "test description\n",
                'canSave' => true,
            ),
            array(
                'title' => __LINE__.' valid multi-line string',
                'value' => "test of\nmultiline\ndescriptoin!\n",
                'canSave' => true,
            ),
        );

        foreach ($tests as $test) {
            $title = $test['title'];
            $value = $test['value'];
            $out   = array_key_exists('out', $test) ? $test['out'] : $test['value'];

            $job = new Job;

            // in memory test via setField
            $job->setDescription($value);
            $this->assertSame(
                $out,
                $job->getDescription(),
                $title.': expected to match set value'
            );

            $this->assertSame(
                $out,
                $job->get('Description'),
                $title.': Expected get() to match'
            );

            // via set comparison
            $job = new Job;
            $job->set('Description', $value);
            $this->assertSame(
                $out,
                $job->get('Description'),
                $title.': Expected to match get'
            );

            $this->assertSame(
                $out,
                $job->getDescription(),
                $title.': Expected set to match getDescription'
            );

            if (array_key_exists('canSave', $test)) {
                // post save test
                $job->setUser('user1')->setStatus('open')->save();
                $this->assertSame(
                    $out,
                    $job->getDescription(),
                    'Expected getDescription to match post save'
                );

                $this->assertSame(
                    $out,
                    $job->get('Description'),
                    'Expected get(Description) to match post save'
                );

                // post fetch test
                $job = Job::fetch($job->getId());

                $this->assertSame(
                    $out,
                    $job->getDescription(),
                    'Expected getDescription to match post fetch'
                );

                $this->assertSame(
                    $out,
                    $job->get('Description'),
                    'Expected get(Description) to match post fetch'
                );
            }
        }
    }

    /**
     * test the get date function
     */
    public function testGetDate()
    {
        $job = new Job;

        $this->assertSame(
            null,
            $job->getDate(),
            'Expected starting date to match'
        );

        $job->setUser('user1')->setStatus('open')->setDescription('blah')->save();

        // convert to unix time (accounting for timezone) and verify it looks ok
        $dateTime = \DateTime::createFromFormat('Y/m/d H:i:s', $job->getDate(), $this->p4->getTimeZone());
        $this->assertLessThan(
            2,
            abs((int) $dateTime->format('U') - time()),
            'Expected converted data/time to be within range post save'
        );

        // also confirm the built-in conversion on the job object spits out a good unixtime
        $this->assertSame(
            (int) $dateTime->format('U'),
            $job->getTime(),
            'Expected time to be within range post save'
        );
    }

    /**
     * Tests invalid options and option combos
     */
    public function testFetchAllBadOptions()
    {
        $tests = array(
            array(
                'title'     => __LINE__.' integer filter',
                'options'   => array(Job::FETCH_BY_FILTER => 0),
                'exception' => 'Fetch by Filter expects a non-empty string as input'
            ),
            array(
                'title'     => __LINE__.' empty string filter',
                'options'   => array(Job::FETCH_BY_FILTER => ""),
                'exception' => 'Fetch by Filter expects a non-empty string as input'
            ),
            array(
                'title'     => __LINE__.' empty string filter w/whitespace',
                'options'   => array(Job::FETCH_BY_FILTER => "     "),
                'exception' => 'Fetch by Filter expects a non-empty string as input'
            ),
            array(
                'title'     => __LINE__.' integer filter',
                'options'   => array(Job::FETCH_BY_FILTER => 10),
                'exception' => 'Fetch by Filter expects a non-empty string as input'
            ),
        );

        foreach ($tests as $test) {
            try {
                Job::fetchAll($test['options']);

                $this->fail($test['title'].': unexpected success');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    $test['exception'],
                    $e->getMessage(),
                    $test['title'].': unexpected exception message'
                );
            } catch (\Exception $e) {
                $this->fail(
                    $test['title'].
                    ': unexpected exception ('. get_class($e) .') '.
                    $e->getMessage()
                );
            }
        }
    }

    /**
     * Test fetchAll with FETCH_DESCRIPTION = false and = true
     */
    public function testFetchAllDescriptions()
    {
        for ($i=0; $i<4; $i++) {
            $job = new Job;
            $job->setId((string)$i)->setUser('user'.$i)->setStatus('open')
                ->setDescription('test: '.$i."\n")->save();
        }

        // eval a mock object into existence which adds a 'getRawValues' function
        $mockCode = 'class P4_JobMock extends \\P4\\Spec\\Job {
                        public function getRawValues()
                        {
                            return $this->values;
                        }
                    }';

        if (!class_exists('P4_JobMock')) {
            eval($mockCode);
        }

        // Test with FETCH_DESCRIPTION off
        $jobs = \P4_JobMock::fetchAll(array(Job::FETCH_DESCRIPTION => false));
        foreach ($jobs as $job) {
            $this->assertFalse(
                array_key_exists('Description', $job->getRawValues()),
                'Job: '.$job->getId().' expected Description to be non-existent'
            );

            $this->assertSame(
                "test: ".$job->getId()."\n",
                $job->getDescription(),
                'Job: '.$job->getId().' expected Description to autoload'
            );
        }

        // Test with FETCH_DESCRIPTION on
        $jobs = \P4_JobMock::fetchAll(array(Job::FETCH_DESCRIPTION => true));
        foreach ($jobs as $job) {
            $this->assertTrue(
                array_key_exists('Description', $job->getRawValues()),
                'Job: '.$job->getId().' expected Description to exist'
            );

            $values = $job->getRawValues();
            $this->assertSame(
                "test: ".$job->getId()."\n",
                $values['Description'],
                'Job: '.$job->getId().' expected Description to match'
            );

            $this->assertSame(
                "test: ".$job->getId()."\n",
                $job->getDescription(),
                'Job: '.$job->getId().' expected Description to match via accessor'
            );
        }

        // Test default is FETCH_DESCRIPTION on
        $explicitJobs = \P4_JobMock::fetchAll(array(Job::FETCH_DESCRIPTION => true));
        $defaultJobs  = \P4_JobMock::fetchAll();

        foreach ($explicitJobs as $key => $job) {
            $this->assertSame(
                $job->getRawValues(),
                $defaultJobs[$key]->getRawValues(),
                'Expeted default to be fetch description = true'
            );
        }
    }

    /**
     * Verify a variety of unusual ids don't cause trouble
     */
    public function testCrazyIds()
    {
        $ids = array(
            '!bang', '$test', '%percent', '&and', '\'', '(open', ')closed', ',comma', '.dot',
            '042709', '30387b', '3spaces', '<ab>', '<abc>', '<open', '>new', '?question', '[open',
            ']closed', '^carrot', '^new', '_Myjob001', '__job_w/_leading_and_trailing_white_space_',
            '_underscore', 'www.hello'
        );

        foreach ($ids as $id) {
            $job = new Job;
            $job->setId($id)
                ->setDescription($id)
                ->save();
        }
    }

    /**
     * Verify ID's with - don't cause erroneous fetch.
     */
    public function testDashFetches()
    {
        $ids = array('foo', 'foo2', 'foo-bar', 'foo-biz', 'bar-foo', 'biz-foo');
        foreach ($ids as $id) {
            $job = new Job;
            $job->setId($id)
                ->setDescription($id)
                ->save();
        }

        $this->assertSame(
            array('foo'),
            Job::fetchAll(array(Job::FETCH_BY_IDS => 'foo'))->invoke('getId'),
            'expected fetch by ids to work'
        );
    }

    public function testCreatedModifiedFieldMethods()
    {
        $job = new Job;

        // should have mod-date field by default
        $this->assertTrue($job->hasModifiedDateField());
        $this->assertSame('Date', $job->getModifiedDateField());

        // should NOT have create-date field
        $this->assertFalse($job->hasCreatedDateField());
        try {
            $job->getCreatedDateField();
            $this->fail();
        } catch (\P4\Spec\Exception\Exception $e) {
            $this->assertTrue(true);
        }

        // add a created date field
        $spec   = $job->getSpecDefinition();
        $fields = $spec->getFields();
        $fields['CreatedDate'] = array(
            'code'          => 106,
            'dataType'      => 'date',
            'displayLength' => 20,
            'fieldType'     => 'once',
            'default'       => '$now'
        );
        $spec->setFields($fields)->save();

        // should now have create-date field
        $this->assertTrue($job->hasCreatedDateField());
        $this->assertSame('CreatedDate', $job->getCreatedDateField());

        // should have created by field.
        $this->assertTrue($job->hasCreatedByField());
        $this->assertSame('User', $job->getCreatedByField());

        // should NOT have modified by field.
        $this->assertFalse($job->hasModifiedByField());
        try {
            $job->getModifiedByField();
            $this->fail();
        } catch (\P4\Spec\Exception\Exception $e) {
            $this->assertTrue(true);
        }

        // add a modified by field
        $spec   = $job->getSpecDefinition();
        $fields = $spec->getFields();
        $fields['ModifiedBy'] = array(
            'code'          => 107,
            'dataType'      => 'word',
            'displayLength' => 20,
            'fieldType'     => 'always',
            'default'       => '$user'
        );
        $spec->setFields($fields)->save();

        // should have mod-date field by default
        $this->assertTrue($job->hasModifiedByField());
        $this->assertSame('ModifiedBy', $job->getModifiedByField());
    }
}
