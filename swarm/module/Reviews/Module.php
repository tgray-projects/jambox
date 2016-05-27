<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Reviews;

use Activity\Model\Activity;
use P4\Spec\Change;
use Record\Exception\NotFoundException as RecordNotFoundException;
use Reviews\Listener\Review as ReviewListener;
use Reviews\Filter\GitInfo;
use Reviews\Model\GitReview;
use Reviews\Model\Review;
use Zend\Mvc\MvcEvent;

class Module
{
    /**
     * Connect to queue event manager to handle review tasks.
     *
     * @param   MvcEvent    $event  the bootstrap event
     * @return  void
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $events      = $services->get('queue')->getEventManager();

        // attach listener to process review when its created or updated
        $events->attach(new ReviewListener($services));

        // Deal with git-fusion initiated reviews before traditional p4 reviews.
        //
        // If the shelf is already a git initiated review we'll update it.
        //
        // If the shelf has git-fusion keywords indicating this is a new review
        // translate the existing shelf into a new git-review and process it.
        $events->attach(
            'task.shelve',
            function ($event) use ($services) {
                $p4Admin = $services->get('p4_admin');
                $queue   = $services->get('queue');
                $config  = $services->get('config');
                $change  = $event->getParam('change');

                // if we didn't get a pending change to work with, bail
                if (!$change instanceof Change || !$change->isPending()) {
                    return;
                }

                // if the change is by a user that is ignored for the purpose of reviews, bail
                $ignored = isset($config['reviews']['ignored_users']) ? $config['reviews']['ignored_users'] : null;
                if ($p4Admin->stringMatches($change->getUser(), (array) $ignored)) {
                    return;
                }

                // if this change doesn't have a valid looking git-fusion style review-id
                // there's no need to further examine it here, return
                $gitInfo = new GitInfo($change->getDescription());
                if ($gitInfo->get('review-id') != $change->getId()) {
                    return;
                }

                try {
                    // using the change id, verify if a git review already exists
                    // note the review id and change id are the same for git-fusion reviews
                    $review = Review::fetch($change->getId(), $p4Admin);

                    // if we get a review but its the wrong type, we can't do anything with it
                    // this really shouldn't happen but good to confirm all is well
                    if ($review->getType() != 'git') {
                        return;
                    }
                } catch (RecordNotFoundException $e) {
                    // couldn't fetch an existing review, create one!
                    $review = GitReview::createFromChange($change, $p4Admin);
                    $review->save();

                    // ensure we pass along to the review event that this is an add
                    $isAdd = true;
                }

                // put the fetched/created review on the existing event.
                // the presence of a review on the event will cause the traditional
                // shelf-commit handler to skip processing this change.
                $event->setParam('review', $review);

                // push the new review into queue for further processing.
                $queue->addTask(
                    'review',
                    $review->getId(),
                    array(
                        'user'             => $change->getUser(),
                        'updateFromChange' => $change->getId(),
                        'isAdd'            => isset($isAdd) && $isAdd
                    )
                );
            },
            100
        );

        // Listen for when a shelf is updated or change submitted.
        // We will examine the change description to see if it contains a
        // configured review pattern.
        //
        // If the change contains a review pattern that includes an existing
        // review id we simply push it through to a 'review' task to carry
        // out the work of updating the shelved files, participants, etc.
        //
        // For changes with a review pattern with no id (so its a 'start review')
        // a new review record will be created and the original change's description
        // is updated to include the id. We then push the change through to the
        // 'review' task much like an update to take care of shelve transfer, etc.
        //
        // For more information on review patterns, see the review_keywords service.
        $module = $this;
        $events->attach(
            array('task.shelve', 'task.commit'),
            function ($event) use ($module, $services) {
                $p4Admin  = $services->get('p4_admin');
                $queue    = $services->get('queue');
                $keywords = $services->get('review_keywords');
                $config   = $services->get('config');
                $change   = $event->getParam('change');
                $data     = (array) $event->getParam('data') + array('review' => null);

                // if a review is already present on the event, someone has done the work for us
                // most likely, this means it was a git-fusion review
                if ($event->getParam('review') instanceof Review) {
                    return;
                }

                // if we didn't get a change to work with, bail
                if (!$change instanceof Change) {
                    return;
                }

                // if the change is by a user that is ignored for the purpose of reviews, bail
                $ignored = isset($config['reviews']['ignored_users']) ? $config['reviews']['ignored_users'] : null;
                if ($p4Admin->stringMatches($change->getUser(), (array) $ignored)) {
                    return;
                }

                // when we update the swarm managed change it feeds back around
                // to here and we need to ignore the event.
                if (Review::exists($change->getOriginalId(), $p4Admin)) {
                    return;
                }

                // we have to determine if this change is already in a review. if it is we:
                // - ensure the change updates that review (even if #review-123 isn't present)
                // - block starting/updating any additional reviews
                // - ignore the change if it is a new archive/version of the review
                // - if change is in the midst of being committed against a specific review,
                //   use that review
                $reviews = Review::fetchAll(array(Review::FETCH_BY_CHANGE => $change->getOriginalId()), $p4Admin);

                // if the change is a new archive/version of the review, ignore event altogether.
                // note: we use the raw versions value to avoid tickling on-the-fly upgrade code
                foreach ($reviews as $review) {
                    $versions = (array) $review->getRawValue('versions');
                    foreach ($versions as $version) {
                        $version += array('change' => null, 'archiveChange' => null, 'pending' => null);
                        if (($version['change'] == $change->getId() || $version['archiveChange'] == $change->getId())
                            && $version['pending']
                        ) {
                            return;
                        }
                    }
                }

                // check for a review keyword in the description
                $matches = $keywords->getMatches($change->getDescription());

                // if this change is associated to a review; ignore the keyword and use
                // the review id we're already associated with.
                // we don't expect multiple reviews but should that occur use the first.
                if ($reviews->count()) {
                    $matches['id'] = $reviews->first()->getId();
                }

                // if the change is in the midst of being committed against a review,
                // that review's id should be used (even if it isn't the first review)
                foreach ($reviews as $review) {
                    if ($review->getCommitStatus('change') == $change->getOriginalId()) {
                        $matches['id'] = $review->getId();
                        break;
                    }
                }

                // if an id was passed in data 'review' it always wins
                if (strlen($data['review'])) {
                    $matches['id'] = $data['review'];
                }

                // if no review details could be located; nothing to do
                if (!$matches) {
                    return;
                }

                // normalize matches now that we know we should be processing
                $matches += array('id' => null);

                // don't allow a change to be in more than one review
                // - if the change is in a review, block adding another review
                // - if the change is in a review, only allow updates to that review
                // largely unnecessary but does protect us in the data['review'] case.
                if ($reviews->count()) {
                    if (!strlen($matches['id'])) {
                        return;
                    }
                    if (!in_array($matches['id'], $reviews->invoke('getId'))) {
                        return;
                    }
                }

                // if this is an update to an existing review, fetch it
                // otherwise create a new review.
                if (strlen($matches['id'])) {
                    // fetch to make sure it exists and to normalize edits/adds
                    // when we push the queue event.
                    try {
                        $review = Review::fetch($matches['id'], $p4Admin);
                    } catch (\Record\Exception\NotFoundException $e) {
                    } catch (\InvalidArgumentException $e) {
                    }

                    // nothing to update if they provided a bad id
                    if (isset($e)) {
                        // @todo inform user via email their id was bad?
                        return;
                    }

                    // perforce users can only commit against a git review, they are
                    // not otherwise allowed to update it. if this is a git review
                    // and not a commit based update, bail
                    if ($review->getType() == 'git' && !$change->isSubmitted()) {
                        // @todo inform user via email they cannot update git reviews?
                        return;
                    }

                    // if the review is mid-commit for another change, bail
                    if ($review->isCommitting() && $review->getCommitStatus('change') != $change->getOriginalId()) {
                        // @todo inform user via email their update was skipped due to ongoing approve & commit?
                        return;
                    }

                    // add the on behalf of information if the user committing this review is not the same as the
                    // original author of it
                    $committer = $review->getCommitStatus('change') == $change->getOriginalId()
                        ? $review->getCommitStatus('committer')
                        : $change->getUser();
                    if ($committer
                        && $committer != $review->get('author')
                        && $event->getParam('activity') instanceof Activity
                    ) {
                        $activity = $event->getParam('activity');
                        $activity->set('behalfOf', $review->get('author'));
                        $activity->set('user', $committer);
                    }
                } else {
                    // create the review record
                    $review = Review::createFromChange($change, $p4Admin);

                    // strip off the review keyword(s) and save it
                    $review->set('description', $keywords->filter($review->get('description')));
                    $review->save();

                    // ensure we pass along to the review event that this is an add
                    $isAdd = true;

                    // the change that started this review needs its description updated to include
                    // the review id. this will give the user feedback we've handled it and make it
                    // clear any future updates to shelved files on that change will impact the review.
                    $change->setDescription(
                        $keywords->update($change->getDescription(), array('id' => $review->getId()))
                    );

                    // saving won't work correctly without a valid client; grab one
                    // and ensure its released even if exceptions should occur.
                    try {
                        $change->getConnection()->getService('clients')->grab();
                        $change->save(true);
                    } catch (\Exception $e) {
                        // we're pretty committed to adding the review at this point so just log and carry on
                        $services->get('logger')->err($e);
                    }
                    $change->getConnection()->getService('clients')->release();
                }

                // put the fetched/created review on the existing event in case anyone cares for it
                $event->setParam('review', $review);

                // push the new review into queue for further processing.
                $queue->addTask(
                    'review',
                    $review->getId(),
                    array(
                        'user'             => $change->getUser(),
                        'updateFromChange' => $change->getId(),
                        'isAdd'            => isset($isAdd) && $isAdd
                    )
                );
            },
            90
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
