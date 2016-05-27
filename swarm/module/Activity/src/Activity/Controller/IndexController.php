<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Activity\Controller;

use Activity\Model\Activity;
use Application\Filter\Preformat;
use Application\Permissions\Protections;
use Comments\Model\Comment;
use Users\Model\User;
use Zend\Feed\Writer\Feed;
use Zend\InputFilter\InputFilter;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\FeedModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        // collect request parameters:
        // stream - only activity for given stream name
        //    max - limit number of results
        //  after - only activity below the given id
        //   type - only activity of given type
        $request    = $this->getRequest();
        $stream     = $this->event->getRouteMatch()->getParam('stream');
        $max        = $request->getQuery('max', 25);
        $after      = $request->getQuery('after');
        $type       = $request->getQuery('type');

        // build fetch query.
        $services   = $this->getServiceLocator();
        $p4Admin    = $services->get('p4_admin');
        $options    = array(
            Activity::FETCH_MAXIMUM     => $max,
            Activity::FETCH_AFTER       => $after,
            Activity::FETCH_BY_STREAM   => $stream,
            Activity::FETCH_BY_TYPE     => $type
        );

        // fetch activity and prepare data for output
        $activity         = array();
        $topics           = array();
        $preformat        = new Preformat($request->getBaseUrl());
        $project          = strpos($stream, 'project-') === 0 ? substr($stream, strlen('project-')) : '';
        $projectList      = $services->get('viewhelpermanager')->get('projectlist');
        $avatar           = $services->get('viewhelpermanager')->get('avatar');
        $reviewersChanges = $services->get('viewhelpermanager')->get('reviewersChanges');
        $ipProtects       = $services->get('ip_protects');
        $records          = Activity::fetchAll($options, $p4Admin);

        // remove activity related to restricted/forbidden changes
        $records = $services->get('changes_filter')->filter($records, 'change');

        foreach ($records as $event) {
            // filter out events related to files user doesn't have access to
            $depotFile = $event->get('depotFile');
            if ($depotFile && !$ipProtects->filterPaths($depotFile, Protections::MODE_READ)) {
                continue;
            }

            //  - render user avatar
            //  - add formatted date
            //  - compose a url if possible
            //  - preformat/linkify descriptions or format reviewers as appropriate
            $activity[] = array_merge(
                $event->get(),
                array(
                    'avatar'         => $avatar($event->get('user'), 64),
                    'date'           => date('c', $event->get('time')),
                    'url'            => $event->getUrl($this->url()),
                    'projectList'    => $projectList($event->get('projects'), $project),
                    'userExists'     => User::exists($event->get('user'), $p4Admin),
                    'behalfOfExists' => User::exists($event->get('behalfOf'), $p4Admin),
                    'description'    => $event->getDetails('reviewers')
                        ? (string) $reviewersChanges($event->getDetails('reviewers'))
                        : $preformat->filter($event->get('description'))
                )
            );

            // remember the topic
            $topics[] = $event->get('topic');
        }

        // add comment count to the activity data
        $counts = Comment::countByTopic(array_unique($topics), $p4Admin);
        foreach ($activity as $key => $event) {
            $activity[$key]['comments'] = isset($counts[$event['topic']])
                ? $counts[$event['topic']]
                : array(0, 0);
        }

        // activity stream title is taken from stream filter
        // e.g. 'user-jdoe' becomes 'jdoe', 'project-swarm' becomes 'swarm'.
        $title = end(explode('-', $stream));

        if ($this->event->getRouteMatch()->getParam('rss')) {
            return $this->getFeedModel($activity, $title);
        }

        return new JsonModel(
            array(
                'activity' => $activity,
                'lastSeen' => $records->getProperty('lastSeen')
            )
        );
    }

    public function addAction()
    {
        // require admin/super to add to activity feed
        $this->getServiceLocator()->get('permissions')->enforce('admin');

        // request must be a post.
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return new JsonModel(
                array(
                    'isValid'   => false,
                    'error'     => 'Invalid request method. HTTP POST required.'
                )
            );
        }

        $data    = $request->getPost();
        $filter  = $this->getActivityFilter();
        $filter->setData($data);

        $isValid = $filter->isValid();
        if ($isValid) {
            $p4Admin  = $this->getServiceLocator()->get('p4_admin');
            $activity = new Activity($p4Admin);
            $activity->set($filter->getValues())
                     ->save();
        }

        return new JsonModel(
            array(
                'activity'  => $isValid ? $activity->toArray() : null,
                'isValid'   => $isValid,
                'messages'  => $filter->getMessages()
            )
        );
    }

    public function deleteAction()
    {
        // no permissions checking as this is a console only action
        $services   = $this->getServiceLocator();
        $p4Admin    = $services->get('p4_admin');
        $request    = $this->getRequest();
        $id         = $request->getParam('i', false);
        $max        = $request->getParam('m', false);

        if ($id === false && $max === false) {
            echo "You must specify one of -m <max> or -i <id>\n";
            return;
        }

        $options = array();

        if ($id !== false) {
            $id = array_filter(array_map('trim', explode(',', $id)), 'strlen');
            $options[Activity::FETCH_BY_IDS] = $id;
        }

        if ($max !== false) {
            $options[Activity::FETCH_MAXIMUM] = $max;
        }

        $toDelete = Activity::fetchAll($options, $p4Admin);
        $toDelete->invoke('delete');

        return "Deleted " . $toDelete->count() . " activity event(s).\n";
    }

    protected function getFeedModel($activity, $title)
    {
        $services   = $this->getServiceLocator();
        $translator = $services->get('translator');

        // determine the URI the user came in on
        // clear the port if it was 80 so it doesn't show in the URI
        $uri = clone $this->request->getUri();
        $uri->setPort($uri->getPort() !== 80 ? $uri->getPort() : null);

        // default url for activity events that lack one
        $defaultUrl = $this->url()->fromRoute('home');

        // we'll need a fully qualified url for the 'link' get the helper
        $qualifiedUrl = $services->get('viewhelpermanager')->get('qualifiedUrl');

        // create the parent feed
        $feed = new Feed;
        $feed->setTitle($translator->t('Swarm') . ($title ? ' - ' . $title : ''));
        $feed->setLink($qualifiedUrl('home'));
        $feed->setDescription($translator->t('Swarm Activity') . ($title ? ' - ' . $title : ''));

        // convert data over to feed entries
        foreach ($activity as $event) {
            // set the first entries time as our modified date
            if (!$feed->getDateModified()) {
                $feed->setDateModified((int) $event['time']);
            }

            $entry = $feed->createEntry();
            $entry->setTitle($event['user'] . ' ' . $event['action'] . ' ' . $event['target']);
            $entry->setLink($event['url'] ?: $defaultUrl);
            $entry->setDateModified((int) $event['time']);
            $entry->setDescription($event['description']);
            $feed->addEntry($entry);
        }

        $model = new FeedModel;
        $model->setFeed($feed);
        return $model;
    }

    protected function getActivityFilter()
    {
        $filter = new InputFilter;

        $filter->add(
            array(
                'name'          => 'type',
                'filters'       => array('trim'),
            )
        );

        $filter->add(
            array(
                'name'          => 'link',
                'filters'       => array('trim'),
                'required'      => false
            )
        );

        $filter->add(
            array(
                'name'          => 'user',
                'filters'       => array('trim'),
            )
        );

        $filter->add(
            array(
                'name'          => 'action',
                'filters'       => array('trim'),
            )
        );

        $filter->add(
            array(
                'name'          => 'target',
                'filters'       => array('trim'),
            )
        );

        $filter->add(
            array(
                'name'          => 'description',
                'filters'       => array('trim'),
                'required'      => false
            )
        );

        $filter->add(
            array(
                'name'          => 'topic',
                'filters'       => array('trim'),
                'required'      => false
            )
        );

        $filter->add(
            array(
                'name'          => 'time',
                'required'      => false,
                'validators'    => array(array('name' => 'Digits'))
            )
        );

        $filter->add(
            array(
                'name'          => 'streams',
                'required'      => false,
                'validators'    => array(
                    array(
                        'name'      => '\Application\Validator\Callback',
                        'options'   => array(
                            'callback'  => function ($value) {
                                if (!is_array($value)) {
                                    return 'Streams must be an array.';
                                }

                                if (count($value) !== count(array_filter($value, 'is_string'))) {
                                    return 'Only string values are permitted in the streams array.';
                                }

                                return true;
                            }
                        )
                    )
                )
            )
        );

        $filter->add(
            array(
                'name'          => 'change',
                'required'      => false,
                'validators'    => array(array('name' => 'Digits'))
            )
        );

        return $filter;
    }
}
