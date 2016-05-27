<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Markdown\Controller;

use Projects\Model\Project;
use Record\Exception\NotFoundException;
use P4\File\File;
use P4\File\Filter;
use P4\File\Query;
use \Parsedown;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractActionController;

class IndexController extends AbstractActionController
{
    /**
     * Finds and returns the content of the first readme.md file (case insensitive) found in a project's mainline
     * branches.
     *
     * Returns empty if error or no readme.md file found.
     *
     * @return JsonModel
     */
    public function projectAction()
    {
        $projectId = $this->getEvent()->getRouteMatch()->getParam('project');
        $p4Admin   = $this->getServiceLocator()->get('p4Admin');

        try {
            $project = Project::fetch($projectId, $p4Admin);
        } catch (NotFoundException $e) {
        } catch (\InvalidArgumentException $e) {
        }

        if (!$project) {
            return new JsonModel(array('readme' => ''));
        }

        $services    = $this->getServiceLocator();
        $config      = $services->get('config');
        $mainlines   = isset($config['projects']['mainlines']) ? (array) $config['projects']['mainlines'] : array();
        $branches    = $project->getBranches('name', $mainlines);

        // check each path of each mainline branch to see if there's a readme.md file present
        $readme = false;
        foreach ($branches as $branch) {
            foreach ($branch['paths'] as $depotPath) {
                if (substr($depotPath, -3) == '...') {
                    $filePath = substr($depotPath, 0, -3);
                }

                // filter is case insensitive
                $filter = Filter::create()->add(
                    'depotFile',
                    $filePath . 'readme.md',
                    Filter::COMPARE_EQUAL,
                    Filter::CONNECTIVE_AND,
                    true
                );
                $query  = Query::create()->setFilter($filter);
                $query->setFilespecs($depotPath);

                $fileList = File::fetchAll($query);
                // there may be multiple files present, break out of the loops on the first one found
                foreach ($fileList as $file) {
                    $readme = File::fetch($file->getFileSpec(), $p4Admin, true);
                    break(3);
                }
            }
        }

        if ($readme === false) {
            return new JsonModel(array('readme' => ''));
        }

        $services         = $this->getServiceLocator();
        $helpers          = $services->get('ViewHelperManager');
        $purifiedMarkdown = $helpers->get('purifiedMarkdown');

        $maxSize  = 1048576; // 1MB
        $contents = $readme->getDepotContents(
            array(
                $readme::UTF8_CONVERT  => true,
                $readme::UTF8_SANITIZE => true,
                $readme::MAX_FILESIZE  => $maxSize
            )
        );

        // baseUrl is used for locating relative images
        return new JsonModel(
            array(
                'readme'  => '<div class="view view-md markdown">' . $purifiedMarkdown($contents) . '</div>',
                'baseUrl' => '/projects/' . $projectId . '/view/' . $branch['id'] . '/'
            )
        );
    }

    /**
     * Handles the project's activity with our new view template.
     *
     * @return ViewModel
     */
    public function activityAction()
    {
        $project = $this->getRequestedProject();
        if (!$project) {
            return;
        }

        return new ViewModel(
            array(
                'project' => $project,
            )
        );
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
