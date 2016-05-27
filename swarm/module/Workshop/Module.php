<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Workshop;

use Application\Filter\StringToId;
use P4\Filter\Utf8;
use Projects\Validator\BranchPath as BranchPathValidator;
use Projects\Model\Project as Project;
use Users\Model\User;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\JsonModel;

class Module
{
    /**
     * Bootstrap events.
     *
     * @param   MvcEvent    $event  the bootstrap event
     * @return  void
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();

        // attach to project add/delete events
        $events = $application->getEventManager();
        $events->attach(
            array(MvcEvent::EVENT_FINISH),
            array($this, 'updateJobSpec'),
            -200
        );

        // update the project filter
        $this->updateProjectFilter($event);

        // update the job spec to Workshop standards
        $events->attach(
            array(MvcEvent::EVENT_ROUTE),
            array($this, 'setJobSpec'),
            -200
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }

    /**
     * If the job spec does not match the expected workshop spec, update it.
     *
     * @param MvcEvent $event
     */
    public function setJobSpec(MvcEvent $event)
    {
        $routeMatch = $event->getRouteMatch();

        if (!$routeMatch) {
            return;
        }

        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $p4          = $services->get('p4');

        if (!$p4) {
            return;
        }

        $route    = $routeMatch->getMatchedRouteName();
        $request  = $event->getRequest();
        if ($request instanceof ConsoleRequest) {
            return;
        }

        $method = $request->getMethod();

        // only proceed if submitting the add project form or forking a project
        if (!(($route == 'projectAddAvatar' || $route == 'add-project') && $method == 'POST')
            && !($route == 'forkProject' && $method == 'GET')) {
            return;
        }

        $spec   = \P4\Spec\Definition::fetch('job', $p4);
        $fields = $spec->getFields();

        if (array_key_exists('Project', $fields)) {
            return;
        }

        // remove these unused fields
        unset($fields['User']);
        unset($fields['Date']);

        $fields['Project'] = array (
            'code' => '106',
            'dataType' => 'select',
            'options'   => '',
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

        // set the list of job ids
        $this->updateJobSpec($event);
    }

    /**
     * Modify the project filter to:
     *  - allow and set the default value for the creator field on add
     *  - enforce the Workshop naming scheme for projects
     *  - enforce the Workshop id scheme for projects
     *  - fill in the jobspec for projects, if empty
     *
     * @param MvcEvent $event
     */
    public function updateProjectFilter(MvcEvent $event)
    {
        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $toId        = new StringToId;

        // Modify the project filter
        $filters        = $services->get('InputFilterManager');
        $p4             = $services->get('p4');
        $projectFilter  = $filters->get('ProjectFilter');

        // callback to create branch id from name
        $toBranchId = function ($name) {
            // don't just use $toId->filter() as we want to allow . and _ characters
            // Attempt to replace uppercase unicode with dashes
            // if the mbstring extension is not installed.
            $utf8  = new Utf8;
            $id = function_exists('mb_strtolower')
                ? mb_strtolower($name, 'UTF-8')
                : strtolower($name);
            $id = preg_replace(
                '/[�\p{Lu}]+/u',
                '-',
                $utf8->filter($id)
            );
            // replace anything except the matching characters with - characters
            $id = trim(
                preg_replace('/[^a-z0-9\x80-\xFF\_\.]+/', '-', $id),
                '-'
            );
            return $id;
        };

        // copy callback to validate users (as its used on multiple elements)
        $usersValidatorCallback = function ($value) use ($p4) {
            if (in_array(false, array_map('is_string', $value))) {
                return 'User ids must be strings';
            }

            $unknownIds = array_diff($value, User::exists($value, $p4));
            if (count($unknownIds)) {
                return 'Unknown user id(s) ' . implode(', ', $unknownIds);
            }

            return true;
        };

        $generateProjectId = function ($value = null) use ($p4, $projectFilter, $toId, $services) {
            if ($value === null) {
                $value = $toId($projectFilter->getRawValue('name'));
            }
            if ($projectFilter->getMode() !== $projectFilter::MODE_ADD) {
                $user = Project::fetch($projectFilter->getRawValue('id'), $p4)->get('creator');
            } else {
                $user = $services->get('user')->getId();
            }
            return $user . '-' . $toId($value);
        };

        // add the creator field and set its value to the current user's id, if we're creating a project
        // if editing the project, use the provided id to fetch the project's creator and return it, ensuring
        // the creator can never be changed by the user
        $projectFilter->add(
            array(
                'name'          => 'creator',
                'required'      => true,
                'filters'       => array(
                    array(
                        'name'      => 'Callback',
                        'options'   => array(
                            'callback' => function ($value) use ($projectFilter, $services) {
                                if ($projectFilter->getMode() !== $projectFilter::MODE_ADD) {
                                    $p4Admin        = $services->get('p4admin');
                                    $id             = $projectFilter->getRawValue('id');
                                    $project        = Project::fetch($id, $p4Admin);
                                    $currentCreator = $project->get('creator');
                                    return $currentCreator;
                                }
                                $user = $services->get('user');
                                return $user->getId();
                            }
                        )
                    )
                ),
            )
        );

        // prepend the id with the current user's id if adding a project
        // only run through StringToId if adding, already verified on edit.
        $projectFilter->remove('id')->add(
            array(
                'name'      => 'id',
                'filters'   => array(
                    array(
                        'name'      => 'Callback',
                        'options'   => array(
                            'callback' => function ($value) use ($projectFilter, $generateProjectId) {
                                if ($projectFilter->getMode() !== $projectFilter::MODE_ADD) {
                                    return $value;
                                }
                                return $generateProjectId($value);
                            }
                        )
                    )
                )
            )
        );

        // copied from original job filter
        $reserved = array('add', 'edit', 'delete');

        // ensure name is given and produces a usable/unique id.
        $projectFilter->remove('name')->add(
            array(
                'name'          => 'name',
                'filters'       => array('trim'),
                'validators'    => array(
                    array(
                        'name'      => 'NotEmpty',
                        'options'   => array(
                            'message'   =>  "Name is required and can't be empty."
                        )
                    ),
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => function ($value) use ($p4, $reserved, $projectFilter, $generateProjectId) {
                                if (empty($value)) {
                                    return 'Name must contain at least one letter or number.';
                                }

                                // if it isn't an add, we assume the caller will take care
                                // of ensuring existence.
                                if ($projectFilter->getMode() !== $projectFilter::MODE_ADD) {
                                    return true;
                                }

                                $id = $generateProjectId($value);

                                // try to get project (including deleted) matching the name
                                $matchingProjects = Project::fetchAll(
                                    array(
                                        Project::FETCH_INCLUDE_DELETED => true,
                                        Project::FETCH_BY_IDS          => array($id)
                                    ),
                                    $p4
                                );

                                if ($matchingProjects->count() || in_array($id, $reserved)) {
                                    return 'This name is taken. Please pick a different name.';
                                }

                                return true;
                            }
                        )
                    )
                )
            )
        );

        $projectFilter->remove('branches')->add(
            array(
                'name'          => 'branches',
                'required'      => false,
                'filters'   => array(
                    array(
                        'name'  => 'Callback',
                        'options'   => array(
                            'callback'  => function ($value) use ($p4, $toId, $toBranchId, $projectFilter, $services) {
                                // normalize the posted branch details to only contain our expected keys
                                // also, generate an id (based on name) for entries lacking one
                                $normalized = array();
                                $defaults   = array(
                                    'id'            => null,
                                    'name'          => null,
                                    'paths'         => '',
                                    'moderators'    => array()
                                );

                                // do not use generateProjectId as we don't want the user prepended
                                $project = $toId($projectFilter->getRawValue('name'));
                                if ($projectFilter->getMode() == $projectFilter::MODE_ADD) {
                                    $user = $services->get('user')->getId();
                                } else {
                                    $user = Project::fetch($projectFilter->getRawValue('id'), $p4)->get('creator');
                                }

                                foreach ((array) $value as $branch) {
                                    $branch = (array) $branch + $defaults;
                                    $branch = array_intersect_key($branch, $defaults);

                                    if (!strlen($branch['id'])
                                        || $projectFilter->getMode() == $projectFilter::MODE_ADD) {
                                        $branch['id'] = $toBranchId($branch['name']);
                                    }

                                    // turn our paths text input into an array based on Workshop rules
                                    $branch['paths'] = array(
                                        '//guest/' . $user . '/' . $project . '/' . $branch['id'] . '/...'
                                    );

                                    $normalized[] = $branch;
                                }

                                return $normalized;
                            }
                        )
                    )
                ),
                'validators'    => array(
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => function ($value) use ($usersValidatorCallback, $p4) {
                                // ensure all branches have a name and id.
                                // also ensure that no id is used more than once.
                                $ids        = array();
                                $branchPath = new BranchPathValidator(array('connection' => $p4));
                                foreach ((array) $value as $branch) {
                                    if (!strlen($branch['name'])) {
                                        return "All branches require a name.";
                                    }

                                    // given our normalization, we assume an empty id results from a bad name
                                    if (!strlen($branch['id'])) {
                                        return 'Branch name must contain at least one letter or number.';
                                    }

                                    if (in_array($branch['id'], $ids)) {
                                        return "Two branches cannot have the same id. '"
                                        . $branch['id'] . "' is already in use for this project.";
                                    }

                                    // validate branch paths
                                    if (!$branchPath->isValid($branch['paths'])) {
                                        return "Error in '" . $branch['name'] . "' branch: "
                                        . implode(' ', $branchPath->getMessages());
                                    }

                                    // verify branch moderators
                                    $moderatorsCheck = $usersValidatorCallback($branch['moderators']);
                                    if ($moderatorsCheck !== true) {
                                        return $moderatorsCheck;
                                    }

                                    $ids[] = $branch['id'];
                                }

                                return true;
                            }
                        )
                    )
                )
            )
        );

        // enforce Workshop naming scheme
        $projectFilter->add(
            array(
                'name'          => 'name',
                'validators'    => array(
                    array(
                        'name'=> 'regex',
                        'options'   => array(
                            'pattern'   => '/^[\w\s\-]+$/',
                            'message'   => 'Name must contain only alphanumeric and underscore characters.',
                        )
                    )
                )
            )
        );

        // replace default jobview validator to fill in if empty
        $projectFilter->remove('jobview')->add(
            array(
                'name'         => 'jobview',
                'required'     => false,
                'filters'      => array(
                    'trim',
                    array(
                        'name'  => 'Callback',
                        'options'   => array(
                            'callback'  => function ($value) use ($generateProjectId) {
                                if (empty($value)) {
                                    return 'project=' . $generateProjectId();
                                }
                                return $value;
                            }
                        )
                    )
                ),
                'validators'   => array(
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => function ($value) {
                                if (!strlen($value)) {
                                    return true;
                                }

                                $filters = preg_split('/\s+/', $value);
                                foreach ($filters as $filter) {
                                    if (!preg_match('/^([^=()|]+)=([^=()|]+)$/', $filter)) {
                                        return "Job filter only supports key=value conditions and the '*' wildcard.";
                                    }
                                }

                                return true;
                            }
                        )
                    )
                )
            )
        );

        $filters->setService('ProjectFilter', $projectFilter);
    }

    /**
     * For the add-project route, only when the form is submitted (vs displayed)
     * OR for the delete-project route,
     * OR for the fork route,
     * if the response is JSON formatted and the result is valid,
     * then update the job spec with the list of current projects.
     *
     * @param MvcEvent $event
     */
    public function updateJobSpec(MvcEvent $event)
    {
        $routeMatch = $event->getRouteMatch();

        if (!$routeMatch) {
            return;
        }

        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $p4Admin     = $services->get('p4_admin');

        if (!$p4Admin) {
            return;
        }

        $route    = $routeMatch->getMatchedRouteName();
        $request  = $event->getRequest();

        if ($request instanceof ConsoleRequest) {
            return;
        }
        
        $method   = $request->getMethod();
        $result   = $event->getResult();

        if ((($route == 'add-project' && $method == 'POST') || $route == 'delete-project' || $route == 'forkProject')
            && ($result instanceof JsonModel && $result->isValid)) {
            $spec     = \P4\Spec\Definition::fetch('job', $p4Admin);
            $fields   = $spec->getFields();

            if ($spec->hasField('Project')) {
                $projects = Project::fetchAll(array(), $p4Admin)->toArray();

                $fields['Project']['options'] = array_keys($projects);

                $spec->setFields($fields);
                try {
                    $spec->save();
                } catch (Exception $e) {
                }
            }
        }
    }
}
