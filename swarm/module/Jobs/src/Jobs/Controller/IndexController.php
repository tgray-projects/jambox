<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Jobs\Controller;

use P4\Connection\Exception\CommandException;
use P4\Spec\Definition as Spec;
use P4\Spec\Job;
use P4\Spec\Exception\NotFoundException;
use Application\Permissions\Exception\ForbiddenException;
use Jobs\Filter\Job as JobFilter;
use Projects\Model\Project;
use Reviews\Model\Review;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    protected $project = null;
    protected $job     = null;

    public function jobAction()
    {
        $services = $this->getServiceLocator();
        $p4       = $services->get('p4');
        $route    = $this->getEvent()->getRouteMatch();
        $id       = $route->getParam('job');
        $project  = $route->getParam('project');
        $query    = $this->getRequest()->getQuery('q');

        // if path contains a possible job id, attempt to look it up.
        $job = null;
        if ($id && !$query) {
            try {
                $job = Job::fetch($id, $p4);
            } catch (NotFoundException $e) {
            } catch (\InvalidArgumentException $e) {
            }

            // if we didn't get the job and trimming would make
            // a difference try to fetch the trimmed id as well
            $trimmed = trim($id, '/');
            if (!$job && $trimmed && $id != $trimmed) {
                try {
                    $job = Job::fetch($trimmed, $p4);
                } catch (NotFoundException $e) {
                } catch (\InvalidArgumentException $e) {
                }
            }

            // if id is numeric and we still have no job, try prefixing with 'job0...'
            if (!$job && ctype_digit($trimmed)) {
                try {
                    $prefixed = 'job' . str_pad($trimmed, 6, '0', STR_PAD_LEFT);
                    if (Job::exists($prefixed, $p4)) {
                        return $this->redirect()->toRoute('job', array('job' => $prefixed));
                    }
                } catch (NotFoundException $e) {
                } catch (\InvalidArgumentException $e) {
                }
            }
        }

        // hand-off to the jobs action if no precise match.
        if (!$job) {
            return $this->forward()->dispatch(
                'Jobs\Controller\Index',
                array(
                    'action'  => 'jobs',
                    'query'   => $query ?: $id,
                    'project' => $project
                )
            );
        }

        // first off separate changes into pending and committed buckets
        $changes   = $job->getChangeObjects();
        $pending   = array();
        $committed = array();
        foreach ($changes as $change) {
            if ($change->isSubmitted()) {
                $committed[$change->getId()] = $change;
            } else {
                $pending[$change->getId()]   = $change;
            }
        }

        // determine which pending changes are actually reviews and separate them
        $p4Admin = $services->get('p4_admin');
        $all     = $pending;
        $reviews = Review::exists(array_keys($all), $p4Admin);
        $pending = array_diff_key($pending, array_flip($reviews));
        $reviews = array_diff_key($all, $pending);

        return new ViewModel(
            array(
                'job'   => $job,
                'fixes' => array(
                    'reviews'   => $reviews,
                    'committed' => $committed,
                    'pending'   => $pending
                )
            )
        );
    }

    public function jobsAction()
    {
        $services = $this->getServiceLocator();
        $route    = $this->getEvent()->getRouteMatch();
        $project  = $route->getParam('project');
        $request  = $this->getRequest();
        $query    = $request->getQuery('q', $route->getParam('query'));
        $max      = $request->getQuery('max', 50);
        $after    = $request->getQuery('after');
        $json     = $request->getQuery('format') == 'json';
        $partial  = $request->getQuery('format') == 'partial';

        // early exit if not requesting jobs data (just render the page)
        if (!$json) {
            $model = new ViewModel(
                array(
                    'partial' => $partial,
                    'query'   => $query,
                    'project' => $project
                )
            );

            $model->setTerminal($partial);
            return $model;
        }

        // compose job search expression
        // if a project was passed, automatically apply it's jobview
        $filter  = trim($query);
        $jobview = $project ? trim($project->get('jobview')) : null;
        if ($jobview) {
            $filter  = $filter  ? "($filter) "  : "";
            $filter .= $jobview ? "($jobview)"  : "";
        }

        $p4   = $services->get('p4');
        $jobs = array();
        try {
            $jobs = Job::fetchAll(
                array(
                    Job::FETCH_BY_FILTER    => trim($filter) ?: null,
                    Job::FETCH_REVERSE      => true,
                    Job::FETCH_MAXIMUM      => $max,
                    Job::FETCH_AFTER        => $after
                ),
                $p4
            );
        } catch (CommandException $e) {
            // we expect the user might enter a bad expression or field name
            if (!preg_match('/expression parse error|unknown field name/i', $e->getMessage())) {
                throw $e;
            }
        }

        // prepare jobs for output
        // special handling for the id, user, date and text fields.
        $rows = array();
        $spec = Spec::fetch('job', $p4);
        $view = $services->get('viewrenderer');
        foreach ($jobs as $job) {
            $row = array('__id' => $job->getId());
            foreach ($job->get() as $key => $value) {
                $info = $spec->getField($key) + array('default' => null);

                if (!strlen($value)) {
                    // if value is empty, nothing to do
                    // handle this case early to avoid errors trying to
                    // create links to null users or similar
                } elseif ($info['code'] == 101) {
                    $url = ($project)
                        ? $view->url('project-jobs', array('job' => $value, 'project' => $project->getId()))
                        : $view->url('jobs', array('job' => $value));
                    $value = '<a href="' . $url . '">'
                           . $view->escapeHtml($value)
                           . '</a>';
                } elseif ($info['default'] === '$user') {
                    $value = $view->userLink($value);
                } elseif ($info['default'] === '$now') {
                    $value = '<span class=timeago title="'
                           . $view->escapeHtmlAttr(date('c', $job->getAsTime($key)))
                           . '"></span>';
                } else {
                    $value = (string) $view->preformat($value);
                }

                $row[$key] = $value;
            }
            $rows[] = $row;
        }

        // enhance spec with a quick lookup for important fields
        $job    = new Job($p4);
        $fields = $spec->getFields() + array(
            '__id'           => $spec->fieldCodeToName(101),
            '__status'       => $spec->fieldCodeToName(102),
            '__description'  => $spec->fieldCodeToName(105),
            '__createdBy'    => $job->hasCreatedByField()    ? $job->getCreatedByField()    : null,
            '__createdDate'  => $job->hasCreatedDateField()  ? $job->getCreatedDateField()  : null,
            '__modifiedBy'   => $job->hasModifiedByField()   ? $job->getModifiedByField()   : null,
            '__modifiedDate' => $job->hasModifiedDateField() ? $job->getModifiedDateField() : null
        );

        $model = new JsonModel;
        $model->setVariables(
            array(
                'project' => $project,
                'query'   => $query,
                'spec'    => $fields,
                'jobs'    => $rows,
                'after'   => $after,
                'errors'  => isset($e) ? $e->getResult()->getErrors() : array()
            )
        );

        return $model;
    }

    /**
     * Start blank job
     * Prefill with project and other data
     * Pass to form
     */
    public function addJobAction()
    {
        $this->getServiceLocator()->get('permissions')->enforce('authenticated');

        $project = $this->getRequestedProject();

        if (!$project) {
            return;
        }

        $services   = $this->getServiceLocator();
        $p4         = $services->get('p4');

        if (!$p4) {
            return;
        }

        $job = new Job($p4);
        $job->setId('new');
        $job->set('Project', $project->getId());
        $job->set('ReportedBy', $p4->getUser());
        $job->set('OwnedBy', $p4->getUser());

        $definition = $job->getSpecDefinition();
        $severity   = $definition->getField('Severity');
        $type       = $definition->getField('Type');
        $status     =  $definition->getField('Status');

        $job->set('Severity', $severity['default']);
        $job->set('Type', $type['default']);
        $job->set('Status', $status['default']);

        return $this->doAddEdit(JobFilter::MODE_ADD, $job);
    }

    /**
     * Fetch specified job
     * Pass to form.
     */
    public function editJobAction()
    {
        // before we call the doAddEdit method we need to ensure the
        // project exists and the user has rights to edit it.
        $job = $this->getRequestedJob();
        if (!$job) {
            return;
        }

        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        $this->canEditJob();

        return $this->doAddEdit(JobFilter::MODE_EDIT, $job);
    }


    /**
     * This is a shared method to power both add and edit actions.
     *
     * @param   string          $mode       one of 'add' or 'edit'
     * @param   Job|null        $job        only passed on edit, the project for starting values
     * @return  ViewModel       the data needed to render an add/edit view
     */
    protected function doAddEdit($mode, $job = null)
    {
        $services = $this->getServiceLocator();
        $p4Admin  = $services->get('p4_admin');
        $p4       = $services->get('p4');
        $request  = $this->getRequest();

        // process add request.
        if ($request->isPost()) {
            // ensure the id in the post is the value passed in the url.
            // we don't want to risk having differing opinions.
            $this->getRequest()->getPost()->set('job', $job->getId());

            // pull data from job to ensure it's not overwritten
            $this->getRequest()->getPost()->set('project', $job->getRawValue('Project'));

            // pull out the data
            $data = $request->getPost();

            // configure our filter with the p4 connection and add/edit mode
            $filter = new JobFilter($p4Admin);
            // set default owner
            $filter->get('ownedBy')->setFallbackValue($p4->getUser());
            $filter->setMode($mode)
                ->setData($data);

            // if the data is valid, setup the job and save it
            $isValid = $filter->isValid();
            $jobId   = $filter->getValue('job');
            if ($isValid) {
                $values         = $filter->getValues();
                $p4             = $services->get('p4');
                $job            = $job ?: new Job($p4Admin);
                $job->setConnection($p4);
                foreach ($values as $field => $value) {
                    $job->setRawValue(ucfirst($field), $value);
                }
                if ($mode == JobFilter::MODE_ADD) {
                    $job->setRawValue('ReportedBy', $p4->getUser());
                }

                $jobId = $job->save()->getId();
            }

            // job id is included separately for ease of future use as well as for testing
            return new JsonModel(
                array(
                    'isValid'   => $isValid,
                    'messages'  => $filter->getMessages(),
                    'jobId'     => $jobId,
                    'redirect'  => '/jobs/' . $jobId
                )
            );
        }

        $partial = $request->getQuery('format') === 'partial';
        $view    = new ViewModel(
            array(
                'mode'      => $mode,
                'job'       => $job,
                'partial'   => $partial,
            )
        );

        $view->setTerminal($partial);

        return $view;
    }

    /**
     * Permissions check for whether or not the current user can edit a specified job via the web UI.
     *
     * @return JsonModel
     */
    public function canEditAction()
    {
        try {
            $canEdit = $this->canEditJob();
        } catch (Exception $e) {
            $canEdit = false;
        }
        return new JsonModel(
            array(
                'canEdit' => $canEdit
            )
        );
    }

    /**
     * Helper method to return model of requested job or false if job
     * id is missing or invalid.
     *
     * @return  Job|false   job model or false if job id is missing or invalid
     */
    protected function getRequestedJob($suppressStatusCode = false)
    {
        if ($this->job) {
            return $this->job;
        }

        $id      = $this->getEvent()->getRouteMatch()->getParam('job');
        $p4Admin = $this->getServiceLocator()->get('p4_admin');

        // attempt to retrieve the specified project
        // translate invalid/missing id's into a 404
        try {
            $this->job = Job::fetch($id, $p4Admin);
            return $this->job;
        } catch (NotFoundException $e) {
        } catch (\InvalidArgumentException $e) {
        }

        if (!$suppressStatusCode) {
            $this->getResponse()->setStatusCode(404);
        }
        return false;
    }

    /**
     * Helper method to return model of requested project or false if project
     * id is missing or invalid.
     *
     * @return  Project|false   project model or false if project id is missing or invalid
     */
    protected function getRequestedProject($suppressStatusCode = false)
    {
        if ($this->project) {
            return $this->project;
        }

        $id      = $this->getEvent()->getRouteMatch()->getParam('project');
        $p4Admin = $this->getServiceLocator()->get('p4_admin');

        // attempt to retrieve the specified project
        // translate invalid/missing id's into a 404
        try {
            $this->project = Project::fetch($id, $p4Admin);
            return $this->project;
        } catch (NotFoundException $e) {
        } catch (\InvalidArgumentException $e) {
        }

        if (!$suppressStatusCode) {
            $this->getResponse()->setStatusCode(404);
        }
        return false;
    }

    /**
     * Checks to see if the current authenticated user can modify the current job.
     *
     * @return bool     Returns true if able to edit
     * @throws \Application\Permissions\Exception\ForbiddenException    Throws exception if not allowed to edit.
     */
    protected function canEditJob()
    {
        $project = $this->getRequestedProject(true);
        if (!$project) {
            return false;
        }

        // ensure only admin/super, project members/owners, or submitter can edit the job
        $checks = $project->hasOwners()
            ? array('admin', 'owner'  => $project)
            : array('admin', 'member' => $project);
        try {
            $this->getServiceLocator()->get('permissions')->enforceOne($checks);
            return true;
        } catch (ForbiddenException $e) {
        } catch (Exception $e) {
        }

        $currentUser = $this->serviceLocator->get('p4_user')->getUser();

        // Throws an exception similar to enforceOne.
        $job = $this->getRequestedJob();
        if ($job->getRawValue('ReportedBy') != $currentUser) {
            return false;
        }

        return true;
    }
}
