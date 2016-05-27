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
use Zend\Stdlib\Parameters;
use Zend\View\Model\JsonModel;

/**
 * Swarm Activity List
 *
 * @SWG\Resource(
 *   apiVersion="v1.1",
 *   basePath="/api/v1.1/"
 * )
 */
class ActivityController extends AbstractApiController
{
    /**
     * @SWG\Api(
     *     path="activity",
     *     @SWG\Operation(
     *         method="POST",
     *         summary="Create Activity Entry",
     *         notes="Creates an entry in the Activity List.
     *                Note: admin-level privileges are required for this action.",
     *         nickname="addActivity",
     *         @SWG\Parameter(
     *             name="type",
     *             paramType="form",
     *             description="Type of activity, e.g., 'jira'",
     *             type="string",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="user",
     *             paramType="form",
     *             description="User who performed the action",
     *             type="string",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="action",
     *             paramType="form",
     *             description="Action that was performed - post-tense, e.g., 'created' or 'commented on'",
     *             type="string",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="target",
     *             paramType="form",
     *             description="Target that the action was performed on, e.g., 'issue 1234'",
     *             type="string",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="topic",
     *             paramType="form",
     *             description="Optional topic for the activity entry. Topics are essentially comment thread IDs.
     *                          Examples: 'reviews/1234' or 'jobs/job001234'",
     *             type="string",
     *             required=false
     *         ),
     *         @SWG\Parameter(
     *             name="description",
     *             paramType="form",
     *             description="Optional description of object or activity to provide context",
     *             type="string",
     *             required=false
     *         ),
     *         @SWG\Parameter(
     *             name="change",
     *             paramType="form",
     *             description="Optional changelist ID this activity is related to. Used to filter activity related to
     *                          restricted changes.",
     *             type="integer",
     *             required=false
     *         ),
     *         @SWG\Parameter(
     *             name="streams[]",
     *             paramType="form",
     *             description="Optional array of streams to display on. This can include user-initiated actions
     *                          ('user-alice'), activity relating to a user's followed projects/users
     *                          ('personal-alice'), review streams ('review-1234'), and project streams
     *                          ('project-exampleproject'). ",
     *             type="array",
     *             @SWG\Items("string"),
     *             required=false
     *         ),
     *         @SWG\Parameter(
     *             name="link",
     *             paramType="form",
     *             description="Optional URL for 'target'",
     *             type="string",
     *             required=false
     *         )
     *     )
     *  )
     *
     * @apiSuccessExample Successful Response:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "activity": {
     *         "id": 123,
     *         "action": "ate",
     *         "behalfOf": null,
     *         "change": null,
     *         "depotFile": null,
     *         "details": [],
     *         "description": "",
     *         "followers": [],
     *         "link": "",
     *         "preposition": "for",
     *         "projects": [],
     *         "streams": [],
     *         "target": "the manual",
     *         "time": 1404776681,
     *         "topic": "",
     *         "type": "coffee",
     *         "user": "A dingo"
     *       }
     *     }
     *
     * @apiErrorResponse Errors if fields are missing:
     *     HTTP/1.1 400 Bad Request
     *
     *     {
     *       "details": {
     *         "target": "Value is required and can't be empty",
     *         "action": "Value is required and can't be empty",
     *         "user": "Value is required and can't be empty"
     *       },
     *       "error": "Bad Request"
     *     }
     *
     * @param   mixed   $data   an array built from the JSON body, if submitted
     * @return  JsonModel
     */
    public function create($data)
    {
        // only allow expected inputs
        $data = array_intersect_key(
            $data,
            array_flip(array('action', 'change', 'description', 'link', 'streams', 'target', 'topic', 'type', 'user'))
        );

        $result = $this->forward(
            'Activity\Controller\Index',
            'add',
            null,
            null,
            $data
        );

        if (!$result->getVariable('isValid')) {
            $this->getResponse()->setStatusCode(400);
            return $this->prepareErrorModel($result);
        }

        return $this->prepareSuccessModel(array('activity' => $result->getVariable('activity')));
    }

    /**
     * Extends parent to provide special preparation of activity data
     *
     * @param   JsonModel|array     $model              A model to adjust prior to rendering
     * @return  JsonModel           The adjusted model
     */
    public function prepareSuccessModel($model)
    {
        $model = parent::prepareSuccessModel($model);

        $activity = $model->getVariable('activity');
        if ($activity) {
            $model->setVariable('activity', $this->normalizeActivity($activity));
        }

        return $model;
    }

    protected function normalizeActivity($activity)
    {
        unset($activity['avatar']);
        return $this->sortEntityFields($activity);
    }
}
