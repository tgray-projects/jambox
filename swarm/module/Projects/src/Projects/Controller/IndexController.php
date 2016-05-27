<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Projects\Controller;

use Application\Filter\Preformat;
use Application\Filter\StringToId;
use Attachments\Model\Attachment as Attachment;
use Projects\Filter\Project as ProjectFilter;
use Projects\Model\Project;
use Record\Exception\NotFoundException;
use Users\Model\Group;
use P4\Log\Logger;
use P4\Spec\Protections;
use P4\Uuid\Uuid;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Validator;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function addAction()
    {
        // ensure user is permitted to add projects
        $this->getServiceLocator()->get('permissions')->enforce('projectAddAllowed');

        // force the 'id' field to have the value of name
        // the input filtering will reformat it for us.
        $request = $this->getRequest();
        $request->getPost()->set('id', $request->getPost('name'));

        return $this->doAddEdit(ProjectFilter::MODE_ADD);
    }

    public function editAction()
    {
        // before we call the doAddEdit method we need to ensure the
        // project exists and the user has rights to edit it.
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        // ensure only admin/super or project members/owners can edit the entry
        $checks = $project->hasOwners()
            ? array('admin', 'owner'  => $project)
            : array('admin', 'member' => $project);
        $this->getServiceLocator()->get('permissions')->enforceOne($checks);

        // ensure the id in the post is the value passed in the url.
        // we don't want to risk having differing opinions.
        $this->getRequest()->getPost()->set('id', $project->getId());

        return $this->doAddEdit(ProjectFilter::MODE_EDIT, $project);
    }

    public function deleteAction()
    {
        $translator = $this->getServiceLocator()->get('translator');

        // request must be a post or delete
        $request = $this->getRequest();
        if (!$request->isPost() && !$request->isDelete()) {
            return new JsonModel(
                array(
                    'isValid'   => false,
                    'error'     => $translator->t('Invalid request method. HTTP POST or HTTP DELETE required.')
                )
            );
        }

        // attempt to retrieve the specified project to delete
        $project = $this->getRequestedProject();
        if (!$project) {
            return new JsonModel(
                array(
                    'isValid'   => false,
                    'error'     => $translator->t('Cannot delete project: project not found.')
                )
            );
        }

        // ensure only admin/super or project owner can delete the entry
        $this->getServiceLocator()->get('permissions')->enforceOne(array('admin', 'owner' => $project));

        // shallow delete the project - we don't permanently remove the record, but set the 'deleted' field
        // to true so the project becomes hidden in general view
        $project->setDeleted(true)->save();

        // clean up protections table
        $p4Super  = $this->getServiceLocator()->get('p4_super');
        $p4Admin  = $this->getServiceLocator()->get('p4_admin');
        $branches = $project->getBranches();
        $hadFiles = false;

        try {
            $protections = Protections::fetch($p4Super);

            foreach ($branches as $branch) {
                foreach ($branch['paths'] as $path) {
                    // while we're looping, check to see if we've ever had files in this path
                    $flags = array('-F', '^headAction=...delete');
                    $flags[] = '-T';
                    $flags[] = 'depotFile';
                    $flags[] = $path;
                    $files   = $p4Admin->run('fstat', $flags)->getData();
                    if ($files !== array()) {
                        $hadFiles = true;
                    }

                    $protections->removeProtection(
                        'write',
                        'group',
                        'swarm-project-' . $project->getId(),
                        '*',
                        $path
                    );
                }
            }
            $protections->save();
        } catch (Exception $e) {
            return new JsonModel(
                array(
                    'isValid'   => false,
                    'error'     => $translator->t(
                        'Unable to remove project protections entries.  '
                        . 'Please contact the administrator with the project name for manual cleanup.'
                    )
                )
            );
        }

        if (!$hadFiles) {
            return new JsonModel(
                array(
                    'isValid' => true,
                    'message' => 'Project successfully deleted.  No files found to remove.',
                    'id'      => $project->getId()
                )
            );
        }

        // generate cryptographically secure token, save it and note the time, send email
        $token = (string)new Uuid;

        $project->setRawValue('deleteToken', $token);
        $project->setRawValue('tokenExpiryTime', strtotime('+1 day'));
        $project->save();

        $config = $this->getServiceLocator()->get('config');
        $user   = $this->getServiceLocator()->get('user');

        // this occurs if the person doing the delete is an administrator and there are no owners of the project
        if ($project->getOwners() == array()) {
            return new JsonModel(
                array(
                    'isValid' => true,
                    'message' => 'Project has been removed successfully; any project files must be removed manually.',
                    'id'      => $project->getId()
                )
            );
        }

        // email settings
        $mail = array(
            'subject'       => "Deleting Workshop Project '" . $project->getName() . "'",
            'toUsers'       => $project->getOwners(),
            'fromAddress'   => $config['mail']['sender'],
            'messageId'     => '<delete-' . $project->getId() . '@swarm>',
            'htmlTemplate'  => __DIR__ . '/../../../view/mail/delete-html.phtml',
            'textTemplate'  => __DIR__ . '/../../../view/mail/delete-text.phtml'
        );

        try {
            $message  = $this->getServiceLocator()->get('mail_composer')->compose(
                $mail,
                array(
                    'token'   => $token,
                    'user'    => $user,
                    'project' => $project,
                )
            );

            $mailer = $this->getServiceLocator()->get('mailer');
            $mailer->send($message);

            return new JsonModel(
                array(
                    'isValid' => true,
                    'message' => 'Validation email sent.  Files will not be removed until the instructions in the '
                        . 'email are followed.',
                    'id'      => $project->getId()
                )
            );
        } catch (\Exception $e) {
            $this->getServiceLocator()->get('logger')->err($e);
            return new JsonModel(
                array(
                    'isValid' => false,
                    'message' => 'Validation email could not be sent.  Project files must be removed manually.',
                    'id'      => $project->getId()
                )
            );
        }

        return new JsonModel(
            array(
                'isValid' => true,
                'message' => 'Unable to send validation email.  Project files will have to be removed manually using '
                    + 'p4 delete (recoverable) or p4 obliterate (unrecoverable).',
                'id'      => $project->getId()
            )
        );
    }

    public function confirmAction()
    {
        $request = $this->getRequest();

        // request must be get
        if (!$request->isGet()) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        $token   = $this->getEvent()->getRouteMatch()->getParam('token');
        $id      = $this->getEvent()->getRouteMatch()->getParam('project');
        $p4Admin = $this->getServiceLocator()->get('p4_admin');

        // cannot use getRequestedProject as it does not return deleted projects
        $result  = Project::fetchAll(
            array(Project::FETCH_INCLUDE_DELETED => true, Project::FETCH_BY_IDS => array($id)),
            $p4Admin
        );

        // defensive - should only get one, but let's make sure
        foreach ($result as $project) {
            if ($project->getId() == $id) {
                break;
            }
        }

        if (!$token) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        // user must be authenticated and an owner of the project (admin is not a requirement - they have other
        // means of obliterating data)
        $this->getServiceLocator()->get('permissions')->enforceOne(array('owner' => $project));

        $projectToken = $project->getRawValue('deleteToken');
        $expiryTime   = $project->getRawValue('tokenExpiryTime');
        $time         = time();

        if ($projectToken === null || $projectToken !== $token || $expiryTime <= $time) {
            return new ViewModel(
                array(
                    'messages' => array(
                        array(
                            'text' => 'Invalid, previously used, or expired token provided.  '
                                . 'Please contact the administrators to have your files removed manually.',
                            'type' => 'error'
                        )
                    )
                )
            );
            return;
        }

        //actually obliterate files.
        $p4admin  = $this->getServiceLocator()->get('p4_admin');
        $branches = $project->getBranches();
        foreach ($branches as $branch) {
            foreach ($branch['paths'] as $path) {
                // run p4 snap first, in case we're a parent of children
                // then obliterate the files
                // -n means preview only
                $response = $p4admin->run('snap', array($path));
                // needs -y to make it actually happen
                $response = $p4admin->run('obliterate', array('-y', $path));
            }
        }

        // remove token and expiry so they cannot be used again
        $project->setRawValue('deleteToken', null);
        $project->setRawValue('tokenExpiryTime', 0);
        $project->save();

        return new ViewModel(
            array(
                'messages' => array(
                    array(
                        'text' => 'Project ' . $project->getName() . ' files have been permanently deleted from the '
                            . 'Workshop.',
                        'type' => 'success'
                    )
                )
            )
        );
    }

    /**
     * This is a shared method to power both add and edit actions.
     *
     * @param   string          $mode       one of 'add' or 'edit'
     * @param   Project|null    $project    only passed on edit, the project for starting values
     * @return  ViewModel       the data needed to render an add/edit view
     */
    protected function doAddEdit($mode, Project $project = null)
    {
        $services = $this->getServiceLocator();
        $p4Admin  = $services->get('p4_admin');
        $p4Super  = $services->get('p4_super');
        $config   = $services->get('config');
        $request  = $this->getRequest();

        // decide whether user can edit project name/branches
        $nameAdminOnly = isset($config['projects']['edit_name_admin_only'])
            ? (bool) $config['projects']['edit_name_admin_only']
            : false;
        $branchesAdminOnly = isset($config['projects']['edit_branches_admin_only'])
            ? (bool) $config['projects']['edit_branches_admin_only']
            : false;
        $canEditName     = !$nameAdminOnly     || $services->get('permissions')->is('admin');
        $canEditBranches = !$branchesAdminOnly || $services->get('permissions')->is('admin');

        // process add request.
        if ($request->isPost()) {
            // pull out the data
            $data = $request->getPost();

            // configure our filter with the p4 connection and add/edit mode
            $filter = $services->get('InputFilterManager')->get('ProjectFilter');

            // mark name/branches fields not-allowed if user cannot modify them
            // this will cause an error if data for these fields are posted
            if ($project) {
                !$canEditName     && $filter->setNotAllowed('name');
                !$canEditBranches && $filter->setNotAllowed('branches');
            }

            $filter->setMode($mode)
                   ->setData($data);

            // if we are in edit, set the validation group to process only defined
            // fields we received posted data for
            if ($mode === ProjectFilter::MODE_EDIT) {
                $filter->setValidationGroup(array_keys($data->toArray()));
            }

            // if the data is valid, setup the project and save it
            $isValid = $filter->isValid();
            if ($isValid) {
                $values           = $filter->getValues();
                $previousBranches = $project ? $project->getBranches() : array();
                $previousImages   = array(
                    'avatar' => $project ? $project->getRawValue('avatar') : null,
                    'splash' => $project ? $project->getRawValue('splash') : null
                );
                $project          = $project ?: new Project($p4Admin);
                $project->set($values);

                $newBranches = $project->getBranches();

                // if the branch list has changed, update the protections
                if ($previousBranches !== $newBranches) {
                    $protections = Protections::fetch($p4Super);

                    foreach ($previousBranches as $branch) {
                        foreach ($branch['paths'] as $path) {
                            $protections->removeProtection(
                                'write',
                                'group',
                                'swarm-project-' . $project->getId(),
                                '*',
                                $path
                            );
                        }
                    }

                    foreach ($newBranches as $branch) {
                        foreach ($branch['paths'] as $path) {
                            $protections->addProtection(
                                'write',
                                'group',
                                'swarm-project-' . $project->getId(),
                                '*',
                                $path
                            );
                        }
                    }

                    $protections->save();
                }

                // mark the avatar & splash images as "used" if present, so they're not deleted automatically
                foreach (array('avatar', 'splash') as $image) {
                    $oldImage     = $previousImages[$image];
                    $attachmentId = $project->getRawValue($image);
                    if (empty($attachmentId) || !is_numeric($attachmentId)) {
                        continue;
                    }

                    if ($attachmentId === $oldImage) {
                        continue;
                    }

                    if (!empty($oldImage)) {
                        try {
                            $attachment = Attachment::fetch($oldImage, $p4Admin);
                            $references = $attachment->getReferences();

                            unset($references['references']['project']);
                            $attachment->setReferences($references);
                            $attachment->save();
                        } catch (NotFoundException $e) {
                        }
                    }

                    try {
                        $attachment = Attachment::fetch($attachmentId, $p4Admin);
                        $attachment->addReference('project', $project->getId());
                        $attachment->save();
                    } catch (Exception $e) {
                        Logger::log(
                            Logger::ERR,
                            'Could not add project ' . $project->getId() .
                            ' reference to attachment ' . $attachmentId . "."
                        );
                        Logger::log(Logger::DEBUG, $e->getMessage());
                    }
                }

                // save the project after protections are updated so that if the protections fail,
                // the project is not saved
                $project->save();
            }

            return new JsonModel(
                array(
                    'isValid'   => $isValid,
                    'messages'  => $filter->getMessages(),
                    'redirect'  => '/projects/' . $filter->getValue('id')
                )
            );
        }

        // defaults for new projects
        if ($mode == ProjectFilter::MODE_ADD) {
            $project     = new Project;
            $currentUser = $this->getServiceLocator()->get('user')->getId();
            $project->setRawValue('creator', $currentUser);
            $project->setOwners(array($currentUser));
            $project->setMembers(array($currentUser));
            $project->setBranches(
                array(
                    array(
                        'id'            => 'main',
                        'name'          => 'Main',
                        'paths'         => array(),
                        'moderators'    => array()
                    )
                )
            );
        }

        // prepare view for form.
        $view = new ViewModel;
        $view->setVariables(
            array(
                 'mode'            => $mode,
                 'project'         => $project ?: new Project,
                 'canEditName'     => $canEditName,
                 'canEditBranches' => $canEditBranches
            )
        );

        return $view;
    }

    public function projectAction()
    {
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        $services    = $this->getServiceLocator();
        $config      = $services->get('config');
        $mainlines   = isset($config['projects']['mainlines']) ? (array) $config['projects']['mainlines'] : array();
        $members     = $project->getMembers();
        $branches    = $project->getBranches('name', $mainlines);
        $followers   = $project->getFollowers();
        $currentUser = $services->get('user')->getId();

        return new ViewModel(
            array(
                'project'       => $project,
                'members'       => $members,
                'branches'      => $branches,
                'followers'     => $followers,
                'mainlines'     => $mainlines,
                'userFollows'   => in_array($currentUser, array_merge($followers, $members)),
                'userIsMember'  => in_array($currentUser, $members)
            )
        );
    }

    public function projectsAction()
    {
        $query    = $this->getRequest()->getQuery();
        $services = $this->getServiceLocator();
        $user     = $services->get('user')->getId();
        $p4Admin  = $services->get('p4_admin');

        // fetch all projects
        $projects = Project::fetchAll(
            array(Project::FETCH_COUNT_FOLLOWERS  => true),
            $p4Admin
        );

        // fetch the raw group cache one time here for performance reasons
        $groups = Group::getCachedData($p4Admin);

        // prepare data for output
        // include a virtual isMember field
        // by default, html'ize the description and provide the count of followers and members
        // pass listUsers   = true to instead get the listing of follower/member ids
        // pass disableHtml = true to stop html'izing the description
        $data        = array();
        $preformat   = new Preformat($this->getRequest()->getBaseUrl());
        $listUsers   = (bool) $query->get('listUsers',   false);
        $disableHtml = (bool) $query->get('disableHtml', false);
        $allFields   = (bool) $query->get('allFields',   false);

        foreach ($projects as $project) {
            $values = $allFields
                ? $project->get()
                : array(
                    'id'      => $project->getId(),
                    'name'    => $project->getName(),
                    'creator' => $project->getRawValue('creator')
                );

            // get list of members, but flipped so we can easily check if user is a member
            // in the API route case (allFields = true), we will already have them
            $members = isset($values['members'])
                ? array_flip($values['members'])
                : $project->getAllMembers(true, $groups);
            $values['members'] = $listUsers ? array_flip($members) : count($members);

            // in the event listUsers is not set, we can simply take the value of 'followers'
            // which will be set to a count of followers thanks to FETCH_COUNT_FOLLOWERS
            $values['followers'] = $listUsers
                    ? $project->getFollowers(array_flip($members))
                    : $project->get('followers');

            if ($user) {
                $values['isMember'] = isset($members[$user]);
            }

            if (!$disableHtml && trim($values['description'])) {
                $values['description'] = $preformat->filter($project->getDescription());
            } else {
                $values['description'] = $project->getDescription();
            }

            $data[] = $values;
        }

        return new JsonModel($data);
    }

    public function reviewsAction()
    {
        $query   = $this->getRequest()->getQuery();
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        // forward json requests to the reviews module
        if ($query->get('format') === 'json') {
            // if query doesn't already contain a filter for project, add one
            $query->set('project', $query->get('project') ?: $project->getId());

            return $this->forward()->dispatch(
                'Reviews\Controller\Index',
                array('action' => 'index', 'activeProject' => $project->getId())
            );
        }

        return new ViewModel(
            array(
                'project' => $project
            )
        );
    }

    public function jobsAction()
    {
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        return $this->forward()->dispatch(
            'Jobs\Controller\Index',
            array(
                'action'    => 'job',
                'project'   => $project,
                'job'       => $this->getEvent()->getRouteMatch()->getParam('job')
            )
        );
    }

    public function browseAction()
    {
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        $route = $this->getEvent()->getRouteMatch();
        $mode  = $route->getParam('mode');
        $path  = $route->getParam('path');

        // based on the mode, redirect to changes or files
        if ($mode === 'changes') {
            return $this->forward()->dispatch(
                'Changes\Controller\Index',
                array(
                    'action'    => 'changes',
                    'path'      => $path,
                    'project'   => $project,
                )
            );
        } elseif ($mode === 'archive') {
            return $this->forward()->dispatch(
                'Files\Controller\Index',
                array(
                    'action'    => 'archive',
                    'path'      => $path,
                    'project'   => $project,
                )
            );
        } else {
            return $this->forward()->dispatch(
                'Files\Controller\Index',
                array(
                    'action'    => 'file',
                    'path'      => $path,
                    'project'   => $project,
                    'view'      => $mode === 'view'     ? true : null,
                    'download'  => $mode === 'download' ? true : null,
                )
            );
        }
    }

    public function archiveAction()
    {
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        $route = $this->getEvent()->getRouteMatch();
        $path  = $route->getParam('path');

        // archiving is handled by the Files module
        return $this->forward()->dispatch(
            'Files\Controller\Index',
            array(
                'action'  => 'archive',
                'path'    => $path,
                'project' => $project,
            )
        );
    }

    public function previewIdAction()
    {
        $name = $this->getEvent()->getRouteMatch()->getParam('name');
        if (!$name) {
            return new JsonModel();
        }
        $toId = new StringToId();
        return new JsonModel(
            array(
                'id' => $toId($name)
            )
        );
    }
    public function joinAction($leave = false)
    {
        // only allow logged in users to join/leave
        $services = $this->getServiceLocator();
        $services->get('permissions')->enforce('authenticated');

        $p4Admin  = $services->get('p4_admin');
        $user     = $services->get('user');
        $project  = $this->getEvent()->getRouteMatch()->getParam('id');

        try {
            $project = Project::fetch($project, $p4Admin);
            $members = $project->getAllMembers();

            if ($leave) {
                if (!in_array($user->getId(), $members)) {
                    return new JsonModel(
                        array(
                            'isValid' => false
                        )
                    );
                }
                $key = array_search($user->getId(), $members);
                unset($members[$key]);
            } else {
                $members[] = $user->getId();
            }

            $project->setMembers($members);
            $project->save();
        } catch(Exception $e) {
            return new JsonModel(
                array(
                    'isValid' => false
                )
            );
        }

        return new JsonModel(
            array(
                'isValid' => true
            )
        );
    }

    public function leaveAction()
    {
        // follow will enforce permissions
        return $this->joinAction(true);
    }


    /**
     * Helper method to return model of requested project or false if project
     * id is missing or invalid.
     *
     * @return  Project|false   project model or false if project id is missing or invalid
     */
    protected function getRequestedProject()
    {
        $id      = $this->getEvent()->getRouteMatch()->getParam('project');
        $p4Admin = $this->getServiceLocator()->get('p4_admin');

        // attempt to retrieve the specified project
        // translate invalid/missing id's into a 404
        try {
            return Project::fetch($id, $p4Admin);
        } catch (NotFoundException $e) {
        } catch (\InvalidArgumentException $e) {
        }

        $this->getResponse()->setStatusCode(404);
        return false;
    }
}
