<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Comments;

use Activity\Model\Activity;
use Application\Filter\Linkify;
use Attachments\Model\Attachment;
use Comments\Model\Comment as CommentModel;
use P4\File\File;
use Reviews\Model\Review;
use P4\Spec\Change;
use P4\Spec\Job;
use Projects\Model\Project;
use Users\Model\User;
use Zend\Mvc\MvcEvent;

class Module
{
    /**
     * Connect to queue event manager to handle new comments.
     *
     * @param   MvcEvent    $event  the bootstrap event
     * @return  void
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $events      = $services->get('queue')->getEventManager();

        // when a comment is created, fetch it and prepare notifications.
        $events->attach(
            'task.comment',
            function ($event) use ($services) {
                $p4Admin  = $services->get('p4_admin');
                $keywords = $services->get('review_keywords');
                $id       = $event->getParam('id');
                $data     = $event->getParam('data') + array(
                    'user'     => null,
                    'previous' => array(),
                    'current'  => array(),
                );

                try {
                    // fetch comment record
                    $comment = CommentModel::fetch($id, $p4Admin);
                    $context = $comment->getFileContext();
                    $event->setParam('comment', $comment);

                    // there are several types of comment activity - compare new against old to see what happened
                    $data['current'] = $data['current'] ?: $comment->get();
                    $commentAction   = CommentModel::deriveAction($data['current'], $data['previous']);

                    // exit early if there's nothing to do
                    if ($commentAction === CommentModel::ACTION_NONE) {
                        return;
                    }

                    // determine the action to report
                    $action    = 'commented on';
                    $taskState = $data['current']['taskState'];
                    if ($commentAction === CommentModel::ACTION_ADD && $taskState !== CommentModel::TASK_COMMENT) {
                        $action = 'opened an issue on';
                    } elseif ($commentAction === CommentModel::ACTION_STATE_CHANGE) {
                        $oldState = isset($data['previous']['taskState'])
                            ? $data['previous']['taskState']
                            : null;
                        $transitions = array(
                            CommentModel::TASK_COMMENT   => 'cleared',
                            CommentModel::TASK_OPEN      => $oldState === CommentModel::TASK_COMMENT
                                ? 'opened'
                                : 'reopened',
                            CommentModel::TASK_ADDRESSED => 'addressed',
                            CommentModel::TASK_VERIFIED  => 'verified'
                        );

                        $action = isset($transitions[$taskState])
                            ? $transitions[$taskState] . ' an issue on'
                            : 'changed task state on';
                    } elseif ($commentAction === CommentModel::ACTION_EDIT) {
                        $action = 'edited a comment on';
                    }

                    // prepare comment info for activity streams
                    $activity = new Activity;
                    $activity->set(
                        array(
                            'type'          => 'comment',
                            'user'          => $data['user'] ?: $comment->get('user'),
                            'action'        => $action,
                            'target'        => $comment->get('topic'),
                            'description'   => $comment->get('body'),
                            'topic'         => $comment->get('topic'),
                            'depotFile'     => $context['file'],
                            'time'          => $comment->get('updated')
                        )
                    );
                    $event->setParam('activity', $activity);

                    // prepare attachment info for comment notification emails
                    if ($comment->get('attachments')) {
                        $event->setParam(
                            'attachments',
                            Attachment::fetchAll(
                                array(Attachment::FETCH_BY_IDS => $comment->get('attachments')),
                                $p4Admin
                            )
                        );
                    }

                    // default mail message subject is simply the topic name.
                    $subject = $comment->get('topic');

                    // enhance activity and mail info if we recognize the topic type
                    $to    = array();
                    $topic = $comment->get('topic');

                    // start by priming mentions with valid users in this new comment
                    // later we'll also add in @mentions from other locations
                    $mentions = Linkify::getCallouts($comment->get('body'));

                    // handle change comments
                    if (strpos($topic, 'changes/') === 0) {
                        $change = $context['change'] ?: end(explode('/', $topic));
                        $target = 'change ' . $change;
                        $hash   = 'comments';
                        if ($context['file']) {
                            $line    = isset($context['line']) ? ", line " .  $context['line'] : '';
                            $target .= " (" . File::decodeFilespec($context['name']) . $line . ")";
                            $hash    = $context['md5'] . ',c' . $comment->getId();
                        }

                        $activity->set('target', $target);
                        $activity->set('link',   array('change', array('change' => $change, 'fragment' => $hash)));

                        try {
                            $change = Change::fetch($change, $p4Admin);

                            // set 'change' field on activity, we want to ensure its the change id
                            // in theory it might be different from $change in case the change was renumbered
                            // and we got it from the topic as topics keep reference to the original id
                            $activity->set('change', $change->getId());

                            // change author, @mentions and project(s) should be notified
                            $to[]     = $change->getUser();
                            $mentions = array_merge($mentions, Linkify::getCallouts($change->getDescription()));
                            $activity->addFollowers($change->getUser());
                            $activity->addProjects(Project::getAffectedByChange($change, $p4Admin));

                            // enhance mail subject to use the change description (will be cropped)
                            $subject  = 'Change @' . $change->getId() . ' - '
                                      . $keywords->filter($change->getDescription());
                        } catch (\Exception $e) {
                            $services->get('logger')->err($e);
                        }
                    }

                    // handle review comments
                    if (strpos($topic, 'reviews/') === 0) {
                        $review = $context['review'] ?: end(explode('/', $topic));
                        $target = 'review ' . $review;
                        $hash   = 'comments';
                        if ($context['file']) {
                            $line    = isset($context['line']) ? ", line " .  $context['line'] : '';
                            $target .= " (" . File::decodeFilespec($context['name']) . $line . ")";
                            $hash    = $context['md5'] . ',c' . $comment->getId();
                        }

                        $activity->set('target', $target);
                        $activity->set('link',   array('review', array('review' => $review, 'fragment' => $hash)));

                        try {
                            $review = Review::fetch($review, $p4Admin);

                            // associate activity with review's head change so we can filter for restricted changes
                            $activity->set('change', $review->getHeadChange());

                            // add any folks that were @*mentioned as required reviewers
                            $review->addRequired(
                                User::filter(Linkify::getCallouts($comment->get('body'), true), $p4Admin)
                            );

                            // comment author and, valid, @mentioned users should be participants
                            $review->addParticipant($comment->get('user'))
                                   ->addParticipant(User::filter($mentions, $p4Admin))
                                   ->save();

                            // review comments should appear on the review stream
                            $activity->addStream('review-' . $review->getId());

                            // all review participants should be notified
                            $to = $review->getParticipants();
                            $activity->addFollowers($review->getParticipants());
                            $activity->addProjects($review->getProjects());

                            // enhance mail subject to use the review description (will be cropped)
                            $subject  = 'Review @' . $review->getId() . ' - '
                                      . $keywords->filter($review->get('description'));
                        } catch (\Exception $e) {
                            $services->get('logger')->err($e);
                        }
                    }

                    // handle job comments
                    if (strpos($topic, 'jobs/') === 0) {
                        $job    = end(explode('/', $topic));
                        $target = $job;
                        $hash   = 'comments';

                        $activity->set('target', $target);
                        $activity->set('link',   array('job', array('job' => $job, 'fragment' => $hash)));

                        try {
                            $job = Job::fetch($job, $p4Admin);

                            // add author, modifier and possibly others to the email recipients list
                            // we find users by looping through all job's defined fields and looking
                            // for default value of '$user'
                            $fields = $job->getSpecDefinition()->getFields();
                            foreach ($fields as $key => $field) {
                                if (isset($field['default']) && $field['default'] === '$user') {
                                    $to[] = $job->get($key);
                                }
                            }

                            // notify users mentioned in job's description
                            $to = array_merge($to, Linkify::getCallouts($job->getDescription()));

                            // associated change(s) users should also be notified
                            if (count($job->getChanges())) {
                                foreach ($job->getChangeObjects() as $change) {
                                    $to[] = $change->getUser();
                                }
                            }

                            // enhance mail subject to use the job description (will be cropped)
                            $subject = $job->getId() . ' - ' . $job->getDescription();
                        } catch (\Exception $e) {
                            $services->get('logger')->err($e);
                        }
                    }

                    // every user that participates in this comment thread
                    // should be notified of this activity (excluding author).
                    try {
                        // if the topic isn't a review we want to included any previous commenters and mentioned users.
                        // if the topic is a review, we skip this step and simply rely on the review participants,
                        // otherwise we might erroneously add back in removed reviewers
                        if (strpos($topic, 'reviews/') !== 0) {
                            // examine every comment on this topic to include:
                            // - all users who posted a comment to the topic
                            // - all users who were mentioned in a comment on this topic
                            $comments = CommentModel::fetchAll(array('topic' => $topic), $p4Admin);
                            $users    = array();
                            foreach ($comments as $entry) {
                                $users[]  = $entry->get('user');
                                $mentions = array_merge($mentions, Linkify::getCallouts($entry->get('body')));
                            }

                            $to = array_merge($to, $users);
                        }

                        // knock back the to list to only unique, valid ids
                        $to = array_unique(array_merge($to, $mentions));
                        $to = User::filter($to, $p4Admin);

                        // if we're emailing you its activity stream worthy, add em
                        $activity->addFollowers($to);

                        // don't email the person who carried out the action
                        $to = array_diff($to, array($data['user'] ?: $comment->get('user')));

                        // configure mail notification - only email for adds and edits
                        if (in_array($commentAction, array(CommentModel::ACTION_ADD, CommentModel::ACTION_EDIT))) {
                            $event->setParam(
                                'mail',
                                array(
                                    'subject'       => $subject,
                                    'cropSubject'   => 80,
                                    'toUsers'       => $to,
                                    'fromUser'      => $data['user'] ?: $comment->get('user'),
                                    'messageId'     => '<comment-' . $comment->getId() . '-' . time() . '@swarm>',
                                    'inReplyTo'     => '<topic-'   . $topic            . '@swarm>',
                                    'htmlTemplate'  => __DIR__ . '/view/mail/comment-html.phtml',
                                    'textTemplate'  => __DIR__ . '/view/mail/comment-text.phtml',
                                )
                            );
                        }
                    } catch (\Exception $e) {
                        $services->get('logger')->err($e);
                    }
                } catch (\Exception $e) {
                    $services->get('logger')->err($e);
                }
            },
            100
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
