<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Activity\Model\Activity;
use Projects\Model\Project as Project;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;

class IndexController extends AbstractActionController
{
    /**
     * Let the view script handle it all.
     *
     * @return array|void
     */
    public function indexAction()
    {
    }

    /**
     * Gets a list of project details for use in the front page.
     *
     * @return ViewModel
     */
    public function projectsAction()
    {
        $source     = $this->getEvent()->getRouteMatch()->getParam('source');
        $count      = $this->getEvent()->getRouteMatch()->getParam('count');
        $services   = $this->getServiceLocator();
        $p4Admin    = $services->get('p4_admin');

        // farm out the fetching of project ids to another method, depending on requested source
        if ($source == 'undefined' || !method_exists($this, $source)) {
            $source = 'active';
        }

        $projectIdList = $this->$source($count);

        $projects = Project::fetchAll(
            array(Project::FETCH_BY_IDS => $projectIdList),
            $p4Admin
        );

        $projectAvatar  = $services->get('viewhelpermanager')->get('projectAvatar');
        $userAvatar     = $services->get('viewhelpermanager')->get('avatar');
        $projectSplash  = $services->get('viewhelpermanager')->get('projectSplash');
        $truncate       = $services->get('viewhelpermanager')->get('smartTruncate');
        $projectLink    = $services->get('viewhelpermanager')->get('projectLink');
        $escapeHtml     = $services->get('viewhelpermanager')->get('escapeHtml');
        $linkify        = $services->get('viewhelpermanager')->get('linkify');

        $projectList = array();

        foreach ($projects as $project) {
            $item = array (
                'name'        => $projectLink($project->getId()),
                'description' => $truncate(
                    $linkify($escapeHtml($project->getDescription())),
                    512,
                    '.',
                    '... <a href="/project/' . $project->getId() . '">[more]</a>'
                )
            );

            $savedSplash    = $project->get('splash');
            $item['splash'] = null;

            if (!empty($savedSplash)) {
                $item['splash'] = $projectSplash($project, 'splash', true);
            }

            $savedAvatar    = $project->get('avatar');
            if (!empty($savedAvatar)) {
                $avatar = $projectAvatar($project, 128, true, 'project', false);
            } else {
                $avatar = $userAvatar($project->get('creator'), 128, true, null, false);
            }
            $item['avatar'] = $avatar;

            $projectList[]  = $item;
        }

        return new JsonModel(
            array('projectList' => $projectList)
        );
    }

    /**
     * Returns a list of project ids created by the provided user.
     *
     * @param  int      $maximum    The maximum number of projects to return; overrides config.
     * @return array()              A list of project ids; may be an empty array.
     */
    public function user($maximum)
    {
        $services = $this->getServiceLocator();
        $p4Admin  = $services->get('p4_admin');

        // short-circuit for production; if at least one of these specified projects is present, limit to this list
        // if not, show default behavior
        $projectIdList = array('p4perl', 'perforce-software-chronicle', 'perforce-software-p4win');
        $projects = Project::fetchAll(
            array(Project::FETCH_BY_IDS => $projectIdList),
            $p4Admin
        );
        if ($projects->count() > 0) {
            return $projectIdList;
        }

        $config   = $services->get('config');
        $maximum  = $maximum ?: isset($config['frontpage']['projects']['maximum'])
            ? $config['frontpage']['projects']['maximum']
            : 5;

        $creator  = $this->getEvent()->getRouteMatch()->getParam('user');

        $projects      = Project::fetchAll(array(), $p4Admin);
        $projectIdList = array();

        foreach ($projects as $project) {
            if ($project->hasField('creator') && $project->get('creator') == $creator) {
                $projectIdList[] = $project->getId();
            }
        }

        $projectIdList = array_slice($projectIdList, 0, $maximum);

        return $projectIdList;
    }

    /**
     * Returns a list of project ids for the most recently
     * active projects.
     *
     * get a count of the projects
     * if the project count < maximum, show them all
     * else fetch the activity
     *   get 5 activity events at a time
     *   change events only? configurable.
     *   get projects from activity event, add to list
     *   stop when we hit maximum project count
     *
     * @param int | null    $maximum    The maximum number of results to return; overrides config.
     * @return array()                  A list of project ids; may be an empty array.
     */
    public function active($maximum = null)
    {
        $services = $this->getServiceLocator();

        $config   = $services->get('config');
        if ($maximum === null) {
            $maximum  = isset($config['frontpage']['projects']['maximum'])
                ? $config['frontpage']['projects']['maximum']
                : 5;
        }

        $minimum  = isset($config['frontpage']['projects']['minimum'])
            ? $config['frontpage']['projects']['minimum']
            : 1;

        $wait     = isset($config['frontpage']['projects']['wait'])
            ? $config['frontpage']['projects']['wait']
            : 10;

        $pad      = ($config['frontpage']['projects']['pad']) ?: false;

        // if the project count is less than the requested count, return all ids
        // this value may be an empty array
        $p4Admin     = $services->get('p4_admin');
        $allProjects = Project::fetchAll(array(), $p4Admin);

        $totalProjects = $allProjects->count();
        if ($totalProjects <= $minimum) {
            return $allProjects->keys();
        }

        // build fetch query.
        $options    = array(
            Activity::FETCH_MAXIMUM     => $maximum,
            Activity::FETCH_AFTER       => null,
            Activity::FETCH_BY_TYPE     => 'change'
        );

        // fetch activity and prepare data for output
        $projectIdList = array();
        $records       = Activity::fetchAll($options, $p4Admin);

        if ($records->count() == 0) {
            return $allProjects->keys();
        }

        // for removing projects we don't have access to
        $ipProtects = $services->get('ip_protects');

        // scenarios at this point
        //  many recently active projects -- best case
        //  few recently active projects
        //  a few recently active projects, but more projects that are not active; worst case scenario

        // count the amount of times we loop with no additional projects found; compare to wait value to decide when
        // to give up.
        $idleLoopCount = 0;
        while (count($projectIdList) < $maximum && $records->count() > 0) {

            foreach ($records as $event) {
                // filter out events related to files user doesn't have access to
                $depotFile = $event->get('depotFile');
                if ($depotFile && !$ipProtects->filterPaths($depotFile, Protections::MODE_READ)) {
                    continue;
                }
                $projects = array_keys($event->get('projects'));
                foreach ($projects as $project) {
                    if (!in_array($project, $projectIdList)) {
                        $idleLoopCount = 0;
                        $projectIdList[] = $project;
                    }
                }
            }

            // if we've hit our limit, stop searching
            $idleLoopCount++;
            if (count($projectIdList) >= $minimum && $idleLoopCount >= $wait) {
                break;
            }

            $options[Activity::FETCH_AFTER] = $event->get('id');
            $records = Activity::fetchAll($options, $p4Admin);

            // remove activity related to restricted/forbidden changes
            $records = $services->get('changes_filter')->filter($records, 'change');
        }

        // if there are less projects than the minimum requested, merge in the project list to pad it out
        if ($pad && count($projectIdList) < $maximum && $totalProjects > count($projectIdList)) {
            $projectIds = array_merge($projectIdList, $allProjects->keys());
            $projectIds = array_unique($projectIds);
            $projectIds = array_slice($projectIds, 0, $maximum);

            return $projectIds;
        }

        return array_slice($projectIdList, 0, $maximum);
    }
}
