<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace JobsTest\Controller;

use ModuleTest\TestControllerCase;
use P4\Spec\Job;
use P4\Spec\User;
use Projects\Model\Project;
use Zend\Stdlib\Parameters;

class IndexControllerTest extends TestControllerCase
{
    /**
     * Test the job action.
     */
    public function testJobAction()
    {
        // add a new job
        $job = new Job;
        $job->setDescription('job xyz')
            ->setUser('foo')
            ->save();

        // dispatch to import to add jobs to the activity list
        $this->dispatch('/activity/import');

        // dispatch to view the job
        $this->resetApplication();
        $this->dispatch('/jobs/' . $job->getId());

        $result = $this->getResult();
        $this->assertRoute('jobs');
        $this->assertRouteMatch('jobs', 'jobs\controller\indexcontroller', 'job');
        $this->assertResponseStatusCode(200);
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        $this->assertInstanceOf('P4\Spec\Job', $result->getVariable('job'));

        $this->assertQueryContentContains('.job-info .job-status.status-open',  'Open');
        $this->assertQueryContentContains('.job-details dt.field-status',       'Status');
        $this->assertQueryContentContains('.job-details dd.field-status',       'open');
        $this->assertQueryContentContains('.job-details dt.field-user',         'User');
        $this->assertQueryContentContains('.job-details dd.field-user',         'foo');
        $this->assertQueryContentContains('.job-details dt.field-date',         'Date');
        $this->assertNotQuery('.job-details dt.field-description');
    }

    public function updateJobSpec($projectIds)
    {
        // update to match workshop
        $spec   = \P4\Spec\Definition::fetch('job');
        $fields = $spec->getFields();

        unset($fields['User']);
        unset($fields['Date']);

        $fields['Project'] = array (
            'code' => '106',
            'dataType' => 'select',
            'options'   => $projectIds,
            'displayLength' => '10',
            'fieldType' => 'required',
            'default' => 'setme',
        );
        $fields['Severity'] = array (
            'code' => '109',
            'dataType' => 'select',
            'options'   => array('A', 'B', 'C'),
            'displayLength' => '10',
            'fieldType' => 'required',
            'default' => 'C',
        );
        $fields['ReportedBy'] = array (
            'code' => '103',
            'dataType' => 'word',
            'displayLength' => '32',
            'fieldType' => 'required',
            'default' => '$user',
        );
        $fields['ReportedDate'] = array (
            'code' => '104',
            'dataType' => 'date',
            'displayLength' => '20',
            'fieldType' => 'once',
            'default' => '$now',
        );
        $fields['ModifiedBy'] = array (
            'code' => '110',
            'dataType' => 'word',
            'displayLength' => '20',
            'fieldType' => 'always',
            'default' => '$user',
        );
        $fields['ModifiedDate'] = array (
            'code' => '111',
            'dataType' => 'date',
            'displayLength' => '20',
            'fieldType' => 'always',
            'default' => '$now',
        );
        $fields['OwnedBy'] = array (
            'code' => '108',
            'dataType' => 'word',
            'displayLength' => '32',
            'fieldType' => 'required',
        );
        $fields['DevNotes'] = array (
            'code' => '107',
            'dataType' => 'text',
            'displayLength' => '0',
            'fieldType' => 'optional',
        );
        $fields['Type']  = array (
            'code' => '112',
            'dataType' => 'select',
            'options'   => array('Bug', 'Feature'),
            'displayLength' => '7',
            'fieldType' => 'required',
            'default' => 'Bug',
        );

        $spec->setFields($fields);
        $spec->save();
    }

    /**
     * Test add job form
     */
    public function testAddJobNoParams()
    {
        // add a project
        $projectData = array(
            'id'      => 'prjtest',
            'name'    => 'prjtest',
            'members' => array('foo', 'bar', 'xyz')
        );
        $project = new Project($this->p4);
        $project->set($projectData)->save();

        // update spec
        $this->updateJobSpec(array('prjtest'));

        $this->dispatch('/projects/prjtest/job/add');

        $this->assertRoute('job-add');
        $this->assertRouteMatch('jobs', 'jobs\controller\indexcontroller', 'addJob');
        $this->assertResponseStatusCode(200);

        $this->assertQueryContentContains('h1', 'Add Job');
        $this->assertQueryContentContains('form label', 'Severity');
        $this->assertQueryContentContains('form label', 'Status');
        $this->assertQueryContentContains('form label', 'Description');
        $this->assertQuery('form input[name="severity"]');
        $this->assertQuery('form input[name="type"]');
        $this->assertQuery('form textarea[name="description"]');
    }

    /**
     * Test job add action.
     *
     * @dataProvider addParamsProvider
     */
    public function testAddActionPost(array $createUsers, array $projects, array $postData, array $messages)
    {
        $this->markTestSkipped();
        foreach ($createUsers as $id) {
            $user = new User($this->p4);
            $user->setId($id)->set('FullName', $id)->set('Email', $id . '@test')->save();
        }

        foreach ($projects as $id) {
            $projectData = array(
                'id'      => $id,
                'name'    => ucfirst($id),
                'members' => $createUsers
            );
            $project = new Project($this->p4);
            $project->set($projectData)->save();
        }

        $postData = new Parameters($postData);
        $this->getRequest()
            ->setMethod(\Zend\Http\Request::METHOD_POST)
            ->setPost($postData);

        // dispatch and check output
        $this->dispatch('/project/prjtest/job/add');
        $result = $this->getResult();

        $this->assertInstanceOf('Zend\View\Model\JsonModel', $result);
        $this->assertRoute('job-add');
        $this->assertRouteMatch('workshop', 'workshop\controller\projectcontroller', 'addJob');
        $this->assertResponseStatusCode(200);

        if ($messages) {
            $this->assertSame(false, $result->getVariable('isValid'));
            $responseMessages = $result->getVariable('messages');
            foreach ($messages as $message) {
                list($messageField, $messageValidator, $messageValue) = $message;
                $this->assertTrue(array_key_exists($messageField, $responseMessages));
                $this->assertSame($messageValue, $responseMessages[$messageField][$messageValidator]);
            }

            // ensure job was not created (only if name was set)
            $jobId = $result->getVariable('jobId');
            if (!empty($jobId) && $jobId != 'new') {
                $this->assertFalse(Job::exists($jobId, $this->p4));
            }
        } else {
            // if no messages, check the project was saved and is dispatchable
            $projectName = $postData['project'];
            $jobId       = $result->getVariable('jobId');
            $this->assertSame(true, $result->getVariable('isValid'));
            $this->assertSame(
                '/projects/' . $projectName . '/jobs/' . $jobId,
                $result->getVariable('redirect')
            );
            $this->assertTrue(Job::exists($jobId, $this->p4));

            $this->resetApplication();
            $this->dispatch('/projects/' . $projectName . '/jobs/' . $jobId);

            $this->assertRoute('project-jobs');
            $this->assertRouteMatch('projects', 'projects\controller\indexcontroller', 'jobs');
            $this->assertResponseStatusCode(200);
        }
    }

    public function addParamsProvider()
    {
        // return values are:
        // - list of users (by ids) to create before running the test
        // - list of project ids to create before running the test
        // - post data
        // - list of message sets expected in response (each set contains 3 values -
        //   form field name, validator name, message) or empty array if valid post is expected
        return array(
            // valid params
            array(
                array('foo', 'bar'),
                array('prj123'),
                array(
                    'job'           => 'new',
                    'status'        => 'open',
                    'project'       => 'prj123',
                    'severity'      => 'A',
                    'description'   => 'test',
                    'devNotes'      => '',
                    'type'          => 'Bug',
                ),
                array()
            ),

            // invalid params
            array(
                array('a'),
                array('prj234'),
                array(
                    'job'           => '',
                    'status'        => 'open',
                    'project'       => 'prj123',
                    'severity'      => 'A',
                    'description'   => 'test',
                    'devNotes'      => '',
                    'type'          => 'Bug',
                ),
                array(
                    array('job', 'isEmpty', "Job is required and can't be empty.")
                ),
            ),
        );
    }
}
