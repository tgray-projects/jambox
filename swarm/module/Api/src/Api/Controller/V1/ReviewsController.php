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
 * Swarm Reviews
 *
 * @SWG\Resource(
 *   apiVersion="v1.1",
 *   basePath="/api/v1.1/"
 * )
 */
class ReviewsController extends AbstractApiController
{
    /**
     * @SWG\Api(
     *     path="reviews/{id}",
     *     @SWG\Operation(
     *         method="GET",
     *         summary="Get Review Information",
     *         notes="Retrieve information about a review.",
     *         nickname="getReview",
     *         @SWG\Parameter(
     *             name="id",
     *             description="Review ID",
     *             paramType="path",
     *             type="integer",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="fields",
     *             description="An optional comma-separated list (or array) of fields to show. Omitting this parameter
     *                          or passing an empty value will show all fields.",
     *             paramType="query",
     *             type="string",
     *             required=false
     *         ),
     *         @SWG\ResponseMessage(code=404, message="Not Found"),
     *         @SWG\ResponseMessage(code=401, message="Unauthorized")
     *     )
     * )
     *
     * @apiSuccessExample Successful Response:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "review": {
     *         "id": 12204,
     *         "author": "bruno",
     *         "changes": [10667],
     *         "commits": [10667],
     *         "commitStatus": [],
     *         "created": 1399325913,
     *         "deployDetails": [],
     *         "deployStatus": null,
     *         "description": "Adding .jar that should have been included in r10145\n",
     *         "participants": {
     *           "alex_qc": [],
     *           "bruno": {
     *             "vote": 1,
     *             "required": true
     *           },
     *           "vera": []
     *         },
     *         "pending": false,
     *         "projects": {
     *           "swarm": ["main"]
     *         },
     *         "state": "archived",
     *         "stateLabel": "Archived",
     *         "testDetails": {
     *           "url": "http://jenkins.example.com/job/project_ci/123/"
     *         },
     *         "testStatus": null,
     *         "type": "default",
     *         "updated": 1399325913
     *       }
     *     }
     *
     * @apiErrorExample Example 404 Response:
     *     HTTP/1.1 404 Not Found
     *
     *     {
     *       "error": "Not Found"
     *     }
     *
     * @param   mixed   $id
     * @return  JsonModel
     */
    public function get($id)
    {
        $fields = $this->getRequest()->getQuery('fields');
        $result = $this->forward('Reviews\Controller\Index', 'review', array('review' => $id));

        return $this->getResponse()->isOk()
            ? $this->prepareSuccessModel(array('review' => $result->getVariable('review')), $fields)
            : $this->prepareErrorModel($result);
    }

    /**
     * @SWG\Api(
     *     path="reviews/",
     *     @SWG\Operation(
     *         method="POST",
     *         summary="Create a Review",
     *         notes="Pass in a changelist ID to create a review. Optionally, you can also provide a
     *                description and a list of reviewers.",
     *         nickname="createReview",
     *         @SWG\Parameter(
     *             name="change",
     *             description="Change ID to create a review from",
     *             paramType="form",
     *             type="integer",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="description",
     *             description="Description for the new review (defaults to change description)",
     *             paramType="form",
     *             type="string",
     *             required=false
     *         ),
     *         @SWG\Parameter(
     *             name="reviewers",
     *             description="A list of reviewers for the new review",
     *             paramType="form",
     *             type="array",
     *             required=false,
     *             @SWG\Items("string")
     *         ),
     *         @SWG\Parameter(
     *             name="requiredReviewers",
     *             description="A list of required reviewers for the new review (v1.1+)",
     *             paramType="form",
     *             type="array",
     *             required="false",
     *             @SWG\Items("string")
     *         ),
     *         @SWG\ResponseMessage(code=400, message="Bad Request"),
     *         @SWG\ResponseMessage(code=401, message="Unauthorized")
     *     )
     * )
     *
     * @apiSuccessExample Successful Response contains Review Entity:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "review": {
     *         "id": 12204,
     *         "author": "bruno",
     *         "changes": [10667],
     *         "commits": [10667],
     *         "commitStatus": [],
     *         "created": 1399325913,
     *         "deployDetails": [],
     *         "deployStatus": null,
     *         "description": "Adding .jar that should have been included in r10145\n",
     *         "participants": {
     *           "bruno": []
     *         },
     *         "pending": false,
     *         "projects": [],
     *         "state": "archived",
     *         "stateLabel": "Archived",
     *         "testDetails": [],
     *         "testStatus": null,
     *         "type": "default",
     *         "updated": 1399325913
     *       }
     *     }
     *
     * @param   mixed   $data
     * @return  JsonModel
     */
    public function create($data)
    {
        $post = array(
            'change'      => isset($data['change'])      ? $data['change']      : null,
            'description' => isset($data['description']) ? $data['description'] : null,
            'reviewers'   => isset($data['reviewers'])   ? $data['reviewers']   : null
        );

        // if the api is 1.1 or newer, include required reviewers
        if ($this->getEvent()->getRouteMatch()->getParam('version') !== "v1") {
            $post['requiredReviewers'] = isset($data['requiredReviewers']) ? $data['requiredReviewers'] : null;
        }

        $result = $this->forward(
            'Reviews\Controller\Index',
            'add',
            null,
            null,
            $post
        );

        if (!$result->getVariable('isValid')) {
            $this->getResponse()->setStatusCode(400);
            return $this->prepareErrorModel($result);
        }

        return $this->prepareSuccessModel($result);
    }

    /**
     * @SWG\Api(
     *     path="reviews/{id}/changes/",
     *     @SWG\Operation(
     *         method="POST",
     *         summary="Add Change to Review",
     *         notes="Links the given change to the review and schedules an update.",
     *         nickname="addChange",
     *         @SWG\Parameter(
     *             name="id",
     *             description="Review ID",
     *             paramType="path",
     *             type="integer",
     *             required=true
     *         ),
     *         @SWG\Parameter(
     *             name="change",
     *             description="Change ID",
     *             paramType="form",
     *             type="integer",
     *             required=true
     *         ),
     *         @SWG\ResponseMessage(code=400, message="Bad Request"),
     *         @SWG\ResponseMessage(code=404, message="Not Found"),
     *         @SWG\ResponseMessage(code=405, message="Method Not Allowed"),
     *         @SWG\ResponseMessage(code=401, message="Unauthorized")
     *     )
     * )
     *
     * @apiSuccessExample Successful Response contains Review Entity:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "review": {
     *         "id": 12204,
     *         "author": "bruno",
     *         "changes": [10667, 12000],
     *         "commits": [10667, 12000],
     *         "commitStatus": [],
     *         "created": 1399325913,
     *         "deployDetails": [],
     *         "deployStatus": null,
     *         "description": "Adding .jar that should have been included in r10145\n",
     *         "participants": {
     *           "bruno": []
     *         },
     *         "pending": false,
     *         "projects": [],
     *         "state": "archived",
     *         "stateLabel": "Archived",
     *         "testDetails": [],
     *         "testStatus": null,
     *         "type": "default",
     *         "updated": 1399325913
     *       }
     *     }
     *
     * @return  JsonModel
     */
    public function addChangeAction()
    {
        $request  = $this->getRequest();
        $response = $this->getResponse();

        // this method is not inherently limited to post, so we check it explicitly
        if (!$request->isPost()) {
            $response->setStatusCode(405);
            return;
        }

        $review = $this->getEvent()->getRouteMatch()->getParam('id');
        $change = $request->getPost('change');

        $result = $this->forward(
            'Reviews\Controller\Index',
            'add',
            null,
            null,
            array('id' => $review, 'change' => $change)
        );

        if (!$result->getVariable('isValid')) {
            if ($response->isOk()) {
                $response->setStatusCode(400);
            }

            // the legacy endpoint returns 404 for a bad change, which is technically incorrect
            // as 404's refer specifically to invalid URIs and change is not in the URI
            if ($response->getStatusCode() === 404 && strlen($result->getVariable('change'))) {
                $response->setStatusCode(400);
            }

            return $this->prepareErrorModel($result);
        }

        return $this->prepareSuccessModel($result);
    }

    /**
     * @SWG\Api(
     *     path="reviews/",
     *     @SWG\Operation(
     *         method="GET",
     *         summary="Get List of Reviews",
     *         notes="List and optionally filter reviews.",
     *         nickname="getReviews",
     *         @SWG\Parameter(
     *             name="after",
     *             description="A review ID to seek to. Reviews up to and including the specified id will be excluded
     *                          from the results and do not count towards max. Useful for pagination. Commonly set to
     *                          the 'lastSeen' property from a previous query.",
     *             paramType="query",
     *             type="integer"
     *         ),
     *         @SWG\Parameter(
     *             name="max",
     *             description="Maximum number of reviews to return. This does not guarantee that 'max' reviews will
     *                          be returned. It does guarantee the number of reviews returned won't exceed 'max'.
     *                          Server-side filtering may exclude some reviews for permissions reasons.",
     *             paramType="query",
     *             type="integer",
     *             defaultValue="1000"
     *         ),
     *         @SWG\Parameter(
     *             name="fields",
     *             description="An optional comma-separated list (or array) of fields to show. Omitting this parameter
     *                          or passing an empty value will show all fields.",
     *             paramType="query",
     *             type="string",
     *             required=false
     *         ),
     *         @SWG\Parameter(
     *             name="change[]",
     *             description="One or more change IDs to limit reviews by. Reviews associated with any of the
     *                          specified changes will be returned.",
     *             paramType="query",
     *             type="array",
     *             @SWG\Items("integer")
     *         ),
     *         @SWG\Parameter(
     *             name="hasReviewers",
     *             description="Boolean option to limit to reviews to those with or without reviewers. Use
     *                          'true' or '1' for true and 'false' or '0' for false. The presence of the parameter
     *                          without a value will be evaluated as true.",
     *             paramType="query",
     *             type="boolean"
     *         ),
     *         @SWG\Parameter(
     *             name="ids[]",
     *             description="One or more review IDs to fetch. Only the specified reviews will be returned. This
     *                          filter cannot be combined with the 'max' parameter.",
     *             paramType="query",
     *             type="array",
     *             @SWG\Items("integer")
     *         ),
     *         @SWG\Parameter(
     *             name="keywords",
     *             description="Keywords to limit reviews by. Only reviews where the description, participants list
     *                          or project list contain the specified keywords will be returned.",
     *             paramType="query",
     *             type="string"
     *         ),
     *         @SWG\Parameter(
     *             name="participants[]",
     *             description="One or more participants to limit reviews by. Reviews with any of the specified
     *                          participants will be returned.",
     *             paramType="query",
     *             type="array",
     *             @SWG\Items("string")
     *         ),
     *         @SWG\Parameter(
     *             name="project[]",
     *             description="One or more projects to limit reviews by. Reviews affecting any of the specified
     *                          projects will be returned.",
     *             paramType="query",
     *             type="array",
     *             @SWG\Items("string")
     *         ),
     *         @SWG\Parameter(
     *             name="state[]",
     *             description="One or more states to limit reviews by. Reviews in any of the specified states
     *                          will be returned.",
     *             paramType="query",
     *             type="array",
     *             @SWG\Items("string")
     *         ),
     *         @SWG\Parameter(
     *             name="passesTests",
     *             description="Boolean option to limit reviews by tests passing or failing. Use 'true' or '1'
     *                          for true and 'false' or '0' for false. The presence of the parameter without a value
     *                          will be evaluated as true.",
     *             paramType="query",
     *             type="string"
     *         )
     *     )
     * )
     *
     * @apiSuccessExample Successful Response:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "lastSeen": 12206,
     *       "reviews": [
     *         {
     *           "id": 12206,
     *           "author": "swarm",
     *           "changes": [12205],
     *           "comments": 0,
     *           "commits": [],
     *           "commitStatus": [],
     *           "created": 1402507043,
     *           "deployDetails": [],
     *           "deployStatus": null,
     *           "description": "Review Description\n",
     *           "participants": {
     *             "swarm": []
     *           },
     *           "pending": true,
     *           "projects": [],
     *           "state": "needsReview",
     *           "stateLabel": "Needs Review",
     *           "testDetails": [],
     *           "testStatus": null,
     *           "type": "default",
     *           "updated": 1402518492
     *         }
     *       ],
     *       "totalCount": 1
     *     }
     *
     *     Swarm will return "null" for "totalCount" if no search filters were provided.
     *
     *     "lastSeen" can often be used as an offset for pagination, by using the value
     *     in the "after" parameter of subsequent requests.
     *
     * @apiSuccessExample When no results are found, the "reviews" array will be empty:
     *     HTTP/1.1 200 OK
     *
     *     {
     *       "lastSeen": null,
     *       "reviews": [],
     *       "totalCount": 0
     *     }
     *
     * @return  JsonModel
     */
    public function getList()
    {
        $request = $this->getRequest();
        $fields  = $request->getQuery('fields');

        // explicitly control the query params we forward to the legacy endpoint
        // if new features get added, we don't want them to suddenly appear
        $filters = array(
            'change', 'hasReviewers', 'ids', 'keywords', 'participants', 'project', 'state', 'passesTests'
        );
        $query   = array(
            'after'       => $request->getQuery('after'),
            'disableHtml' => true,
            'max'         => $request->getQuery('max', 1000),
        ) + array_intersect_key((array) $request->getQuery(), array_flip($filters));

        $result  = $this->forward(
            'Reviews\Controller\Index',
            'index',
            null,
            $query
        );

        return $this->prepareSuccessModel(
            array(
                'lastSeen'   => $result->getVariable('lastSeen'),
                'reviews'    => $result->getVariable('reviews'),
                'totalCount' => $result->getVariable('totalCount')
            ),
            $fields
        );
    }

    /**
     * Extends parent to provide special preparation of review data
     *
     * @param   JsonModel|array     $model              A model to adjust prior to rendering
     * @param   string|array        $limitEntityFields  Optional comma-separated string (or array) of fields
     *                                                  When provided, limits review/reviews to specified fields.
     * @return  JsonModel           The adjusted model
     */
    public function prepareSuccessModel($model, $limitEntityFields = null)
    {
        $model = parent::prepareSuccessModel($model);

        // some legacy endpoints include an 'id' field, which we don't want
        unset($model->id);

        // make adjustments to 'review' entity if present
        if ($model->getVariable('review')) {
            $model->setVariable('review', $this->normalizeReview($model->getVariable('review'), $limitEntityFields));
        }

        // if a list of reviews is present, normalize each one
        $reviews = $model->getVariable('reviews');
        if ($reviews) {
            foreach ($reviews as $key => $review) {
                $reviews[$key] = $this->normalizeReview($review, $limitEntityFields);
            }

            $model->setVariable('reviews', $reviews);
        }

        return $model;
    }

    protected function normalizeReview($review, $limitEntityFields = null)
    {
        // clobber redundant 'participants' field with more informative 'participantsData'
        if (isset($review['participants'], $review['participantsData'])) {
            $review['participants'] = $review['participantsData'];
            unset($review['participantsData']);
        }

        // several fields returned by the legacy endpoints are inconsistent/inappropriate for the api
        unset(
            $review['authorAvatar'],
            $review['createDate'],
            $review['downVotes'],
            $review['hasReviewer'],
            $review['upVotes']
        );

        // limit and re-order fields for aesthetics/consistency
        $review = $this->limitEntityFields($review, $limitEntityFields);
        $review = $this->sortEntityFields($review);

        return $review;
    }
}
