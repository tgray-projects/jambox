<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ReviewsTest\Controller;

use ModuleTest\TestControllerCase;
use Reviews\Model\Review;
use Users\Model\Group;
use Users\Model\User;

class ReviewsIndexControllerTest extends TestControllerCase
{
    public function setUp()
    {
        parent::setUp();

        // set up registered group
        // make registered group, if it does not exist, and clear cache
        if (!Group::exists('registered', $this->p4)) {
            Group::fromArray(
                array('Owners' => array($this->p4->getUser()), Group::ID_FIELD => 'registered'),
                $this->superP4
            )->save();
            $this->p4->getService('cache')->invalidateItem('groups');
        }
    }

    public function testVoteAction()
    {
        // create users
        $joe = new User($this->superP4);
        $joe->setId('joe')
            ->setEmail('joe@example.com')
            ->setFullName('Mr Bastianich')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        $graham = new User($this->superP4);
        $graham->setId('graham')
            ->setEmail('graham@example.com')
            ->setFullName('Mr Elliot')
            ->setPassword('abcd1234')
            ->addToGroup('registered')
            ->save();

        $review = new Review($this->p4);
        $review->setParticipants(array('joe', 'graham'))
            ->set('author', 'graham')
            ->save();

        $reviewId = $review->getId();

        // switch to the joe user
        $services = $this->getApplication()->getServiceManager();
        $auth     = $services->get('auth');
        $adapter  = new \Users\Authentication\Adapter('joe', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        // vote down
        $this->getRequest()->setMethod('POST');
        $this->dispatch('/reviews/' . $reviewId . '/vote/down');
        $this->assertResponseStatusCode(200);

        $review = Review::fetch($reviewId, $this->p4);
        $votes  = $review->getVotes(false);
        $this->assertSame(-1, $votes['joe']['value']);

        // vote up
        $this->resetApplication();

        // switch to the joe user
        $services = $this->getApplication()->getServiceManager();
        $auth     = $services->get('auth');
        $adapter  = new \Users\Authentication\Adapter('joe', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        $this->getRequest()->setMethod('POST');
        $this->dispatch('/reviews/' . $reviewId . '/vote/up');
        $this->assertResponseStatusCode(200);

        $review = Review::fetch($reviewId, $this->p4);
        $votes  = $review->getVotes(false);
        $this->assertSame(1, $votes['joe']['value']);

        // vote clear
        $this->resetApplication();

        // switch to the joe user
        $services = $this->getApplication()->getServiceManager();
        $auth     = $services->get('auth');
        $adapter  = new \Users\Authentication\Adapter('joe', 'abcd1234', $this->p4);
        $auth->authenticate($adapter);

        $this->getRequest()->setMethod('POST');
        $this->dispatch('/reviews/' . $reviewId . '/vote/clear');
        $this->assertResponseStatusCode(200);

        $review = Review::fetch($reviewId, $this->p4);
        $this->assertSame(array(), $review->getVotes(false));
    }
}
