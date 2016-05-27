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
 * Basic API controller providing a simple version action
 * @SWG\Resource(
 *   apiVersion="v1.1",
 *   basePath="/api/v1.1/"
 * )
 */
class IndexController extends AbstractApiController
{
    /**
     * Return version info
     *
     * @SWG\Api(
     *     path="version",
     *     description="Version Information",
     *     @SWG\Operation(
     *         method="GET",
     *         summary="Show Version Information",
     *         notes="This can be used to determine the currently-installed Swarm version,
     *                and also to check that Swarm's API is responding as expected.",
     *         nickname="version"
     *     )
     * )
     *
     * @apiSuccessExample Successful Response:
     *     HTTP/1.1 200 OK
     *
     *     {
     *         "year": "2014",
     *         "version": "SWARM/2014.3-MAIN/885869 (2014/06/25)"
     *     }
     *
     *     Note: "year" refers to the year of the Swarm release, not necessarily the current year.
     *
     * @return  JsonModel
     */
    public function versionAction()
    {
        if (!$this->getRequest()->isGet()) {
            $this->getResponse()->setStatusCode(405);
            return;
        }

        $data = array(
            'version'   => VERSION,
            'year'      => current(explode('.', VERSION_RELEASE)),
        );

        // include a list of supported api versions for v1.1 and up
        if ($this->getEvent()->getRouteMatch()->getParam('version') !== "v1") {
            $data['apiVersions'] = array(1.0, 1.1);
        }

        return new JsonModel($this->sortEntityFields($data));
    }
}
