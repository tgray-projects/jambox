<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Api\Controller\V1;

use Api\AbstractApiController;
use Zend\View\Model\JsonModel;

/**
 * Swarm Projects
 *
 * @SWG\Resource(
 *   apiVersion="v1.1",
 *   basePath="/api/v1.1/"
 * )
 */
class ProjectsController extends AbstractApiController
{
    /**
     * @SWG\Api(
     *     path="projects/",
     *     @SWG\Operation(
     *         method="GET",
     *         summary="Get List of Projects",
     *         notes="Returns the complete list of projects in Swarm.",
     *         nickname="listProjects",
     *         @SWG\Parameter(
     *             name="fields",
     *             description="An optional comma-separated list (or array) of fields to show for each project.
     *                          Omitting this parameter or passing an empty value will show all fields.",
     *             paramType="query",
     *             type="string",
     *             required=false
     *         )
     *     )
     * )
     *
     * @apiSuccessExample Successful Response:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "projects": [
     *         {
     *           "id": "testproject",
     *           "branches": [
     *             {
     *               "id": "main",
     *               "name": "main",
     *               "paths": ["//depot/main/TestProject/..."],
     *               "moderators": []
     *             }
     *           ],
     *           "deleted": false,
     *           "deploy": {"url": "", "enabled": false},
     *           "description": "Test test test",
     *           "followers": [],
     *           "jobview": "subsystem=testproject",
     *           "members": ["alice"],
     *           "name": "TestProject",
     *           "tests": {"url": "", "enabled": false}
     *         }
     *       ]
     *     }
     *
     * @return mixed
     */
    public function getList()
    {
        $fields = $this->getRequest()->getQuery('fields');
        $result = $this->forward(
            'Projects\Controller\Index',
            'projects',
            null,
            array(
                'disableHtml' => true,
                'listUsers'   => true,
                'allFields'   => true
            )
        );

        $result = array('projects' => $result->getVariables());

        return $this->getResponse()->isOk()
            ? $this->prepareSuccessModel($result, $fields)
            : $this->prepareErrorModel($result);
    }

    /**
     * Extends parent to provide special preparation of project data
     *
     * @param   JsonModel|array     $model              A model to adjust prior to rendering
     * @param   string|array        $limitEntityFields  Optional comma-separated string (or array) of fields
     *                                                  When provided, limits entity output to specified fields.
     * @return  JsonModel           The adjusted model
     */
    public function prepareSuccessModel($model, $limitEntityFields = null)
    {
        $model = parent::prepareSuccessModel($model);

        // if a list of projects is present, normalize each one
        $projects = $model->getVariable('projects');
        if ($projects) {
            foreach ($projects as $key => $project) {
                $projects[$key] = $this->normalizeProject($project, $limitEntityFields);
            }

            $model->setVariable('projects', $projects);
        }

        return $model;
    }

    protected function normalizeProject($project, $limitEntityFields = null)
    {
        unset($project['isMember']);
        $project = $this->limitEntityFields($project, $limitEntityFields);

        return $this->sortEntityFields($project);
    }
}
