<?php
/**
 * Perforce Workshop
 *
 * @copyright   2016 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Admin\Controller;

use P4\Connection;
use P4\Spec\Protections;
use Projects\Model\Project;
use Users\Model\Config;
use Users\Model\User;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

/**
 * Class IndexController
 * @package Admin\Controller
 *
 */
class IndexController extends AbstractActionController
{
    public function indexAction()
    {
    }

    /**
     * Duplicate followers from one project to another
     */
    public function moveFollowersAction()
    {
        $services = $this->getServiceLocator();
        $connection = Connection\Connection::getDefaultConnection();
        $source = $this->params('source');
        $target = $this->params('target');

        $services->get('permissions')->enforce('authenticated');
        $services->get('permissions')->enforce('super');

        // Check if source project exists
        // Note: project id has the form $username-$projectname
        if (!Project::exists($source, $connection)) {
            return new ViewModel(
                array(
                    'msg' => $source . ' project does not exist.'
                )
            );
        }

        // Check if target project exists
        if (!Project::exists($target, $connection)) {
            return new ViewModel(
                array(
                    'msg' => $target . ' project does not exist.'
                )
            );
        }

        // Check if target project and source project are the same
        if ($source == $target) {
            return new ViewModel(
                array(
                    'msg' => 'Source and target projects are the same.'
                )
            );
        }

        $sourceProject = new Project();
        $sourceProject->setId($source);
        // get followers and exclude members
        $followers = $sourceProject->getFollowers(true);

        foreach ($followers as $follower) {
            $user = new User();
            $user->setId($follower);
            $config = $user->getConfig();
            $config->addFollow($target, 'project');
            $config->save();
        }

        return new ViewModel(
            array(
                'source'    => $source,
                'target'    => $target,
                'followers' => $followers,
            )
        );
    }
}
