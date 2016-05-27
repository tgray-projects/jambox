<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Fork\Controller;

use Application\Filter\StringToId;
use Projects\Model\Project;
use P4\Connection\Exception\CommandException;
use P4\Log\Logger;
use Record\Exception\NotFoundException;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;

/**
 * Class IndexController
 * @package Fork\Controller
 *
 */
class IndexController extends AbstractActionController
{
    /**
     *
     * If user is not authenticated, cannot fork projects.
     * Users cannot fork their own projects (due to path scheme).
     * (This will change at a later date, when a fork dialog exists, and users can choose a branch name.)
     *
     * When forking:
     * - update the project's id
     * - update the project's creator field
     * - update the branches
     * - copy branch files using perforce
     *
     * @return JsonModel    Returns the id of the new project, for redirecting to the new project page.
     */
    public function forkingAction()
    {
        $services     = $this->getServiceLocator();
        $user         = $services->get('user');
        $p4           = $services->get('p4');
        $p4Admin      = $services->get('p4admin');
        $permissions  = $services->get('permissions');
        $permissions->enforce('authenticated');

        $stringToId   = new StringToId;
        $projectId    = $this->getEvent()->getRouteMatch()->getParam('project');
        $forkBranchId = $this->getEvent()->getRouteMatch()->getParam('branch');

        if (empty($forkBranchId) || empty($projectId)) {
            $this->getResponse()->setStatusCode(404);
            return;
        }

        // verify source project and branch
        try {
            $project = Project::fetch($projectId, $p4);
        } catch (Exception $e) {
            $this->getResponse()->setStatusCode(404);
            return new JsonModel(
                array(
                    'id'      => null,
                    'message' => 'Unable to fork to find project ' . $projectId . '.'
                )
            );
        }

        try {
            $sourceBranch = $project->getBranch($forkBranchId);
        } catch (Exception $e) {
            $this->getResponse()->setStatusCode(404);
            return new JsonModel(
                array(
                    'id'      => null,
                    'message' => 'Unable to fork to find branch ' . $forkBranchId . '.'
                )
            );
        }

        $creator = $user->getId();
        $forkId  = $creator . '-' . $stringToId($project->getName());

        // if the project we're forking to exists, try to add the branch to it
        try {
            if (Project::exists($forkId, $p4)) {
                $forked = Project::fetch($forkId, $p4);
            } else {
                $forked = clone($project);
                $forked->setId($forkId);
                $forked->setRawValue('creator', $creator);
                $forked->setRawValue('parent', $projectId);
                $forked->setOwners(array($creator));
                $forked->setMembers(array($creator));
                $forked->setJobview('project=' . $forkId);
                $forked->setRawValue('avatar', null);
                $forked->setRawValue('splash', null);
                $forked->setBranches(array());
            }
        } catch (Exception $e) {
            $this->getResponse()->setStatusCode(500);
            return new JsonModel(
                array(
                    'id'      => null,
                    'message' => 'Unable to fork to existing project.'
                )
            );
        }

        // if the branch we're forking to already exists, return
        $branches = $forked->getBranches();
        foreach ($branches as $branch) {
            if ($branch['id'] == $forkBranchId) {
                return new JsonModel(
                    array(
                        'id'      => $forked->getId(),
                        'message' => 'Target project and branch already exist.'
                    )
                );
            }
        }

        $forkBranch = $sourceBranch;
        $targetPath = '//guest/' . $creator . '/' . $stringToId($project->getName()) . '/' . $forkBranchId . '/...';

        $forkBranch['paths'] = array($targetPath);
        if (!empty($forkBranch['moderators'])) {
            $forkBranch['moderators'] = array($creator);
        }
        $forked->setBranches(array($forkBranch));

        try {
            // actually populate the branch we're forking
            $description = "\"Forking branch " . $forkBranch['name'] . " of "
                . $project->getId() . " to " . $forked->getId() . ".\"";

            $params = array('-d', $description, $sourceBranch['paths'][0], $targetPath);
            $p4->run('populate', $params);

            // save project if populate succeeds
            $forked->setConnection($p4Admin)->save();
        } catch (CommandException $e) {
            Logger::log(Logger::DEBUG, $e->getMessage());
            return new JsonModel(
                array(
                    'id'      => null,
                    'command' => 'populate ' . implode(' ', $params),
                    'message' => $e->getMessage()
                )
            );
        } catch (Exception $e) {
            Logger::log(Logger::DEBUG, $e->getMessage());
            return new JsonModel(
                array(
                    'id'      => null,
                    'message' => 'Something forked up, could not fork project ' . $project->getName()
                        . ' from ' . $project->getId() . ' to ' . $user->getId() . '.'
                )
            );
        }

        return new JsonModel(
            array(
                'id'      => $forked->getId(),
                'isValid' => true
            )
        );
    }

    public function parentAction()
    {
        $services  = $this->getServiceLocator();
        $p4Admin   = $services->get('p4_admin');
        $projectId = $this->getEvent()->getRouteMatch()->getParam('project');

        try {
            $project = Project::fetch($projectId, $p4Admin);
        } catch (Exception $e) {
            $this->getResponse()->setStatusCode(404);
            return new JsonModel(
                array(
                    'parentId'   => null,
                    'parentName' => null,
                )
            );
        }

        try {
            $parent  = Project::fetch($project->getRawValue('parent'), $p4Admin);
        } catch (NotFoundException $e) {
            return new JsonModel(
                array(
                    'parentId'   => null,
                    'parentName' => null,
                )
            );
        }

        return new JsonModel(
            array(
                'parentId'   => $parent->getId(),
                'parentName' => $parent->getName(),
            )
        );
    }
}
