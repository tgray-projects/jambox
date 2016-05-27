<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ApiTest\Controller;

use ModuleTest\TestControllerCase;
use P4\File\File;
use P4\Spec\Change;
use Users\Model\User;
use Reviews\Model\Review;
use Zend\Http\Request;
use Zend\Stdlib\Parameters;

class ReviewsControllerTest extends TestControllerCase
{
    public function testGetReview()
    {
        $response = $this->get('/api/v1/reviews/2');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"Not Found"}', $response->getContent());

        $change = $this->createChange();
        $review = Review::createFromChange($change->getId(), $this->p4);
        $review->save();

        $response = $this->get('/api/v1/reviews/2');
        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => "change description\n",
                'participants'  => array(
                    'admin'     => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);
    }

    public function testGetReviewLimitByFields()
    {
        $change = $this->createChange();
        $review = Review::createFromChange($change->getId(), $this->p4);
        $review->save();

        $response = $this->get('/api/v1/reviews/2?fields=id,author,description,pending,state,stateLabel');
        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'description'   => "change description\n",
                'pending'       => false,
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
            )
        );

        $this->assertSame($actual, $expected);
    }

    public function testCreateReview()
    {
        $change   = $this->createChange();
        $response = $this->post(
            '/api/v1/reviews',
            array(
                'change'      => $change->getId(),
                'description' => 'Test Review',
                'reviewers'   => array('tester')
            )
        );

        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => 'Test Review',
                'participants'  => array(
                    'admin'     => array(),
                    'nonadmin'  => array(),
                    'tester'    => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);

        // ensure record was really created
        $review = Review::fetch(2, $this->p4);
        $this->assertSame("Test Review", $review->get('description'));
    }

    public function testCreateReviewDuplicate()
    {
        $change   = $this->createChange();
        $response = $this->post(
            '/api/v1/reviews',
            array(
                'change'      => $change->getId(),
                'description' => 'Test Review'
            )
        );

        $this->assertSame(200, $response->getStatusCode());

        $response = $this->post(
            '/api/v1/reviews',
            array(
                'change'      => 1,
                'description' => 'Dupe Review',
            )
        );

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            json_decode($response->getContent(), true),
            array('error' => 'A Review for change 1 already exists.')
        );
    }

    public function testCreateReviewJson()
    {
        $change   = $this->createChange();
        $response = $this->postJson(
            '/api/v1/reviews',
            array(
                'change'      => $change->getId(),
                'description' => 'Test Review',
                'reviewers'   => array('tester')
            )
        );

        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => 'Test Review',
                'participants'  => array(
                    'admin'     => array(),
                    'nonadmin'  => array(),
                    'tester'    => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);

        // ensure review record exists
        $review = Review::fetch(2, $this->p4);
        $this->assertSame("Test Review", $review->get('description'));
    }

    public function getRequiredReviewersData()
    {
        return array(
            array(array('tester'), 200, array('tester')),
            array(array(), 200, array()),
            array(null, 200, array()),
            array(array('idonotexist'), 400, array()),
            array('wrongtype', 400, array())
        );
    }

    /**
     * @dataProvider getRequiredReviewersData
     */
    public function testCreateReviewRequiredReviewers($requiredReviews, $statusCode, $resultRequired)
    {
        $change   = $this->createChange();
        $response = $this->post(
            '/api/v1.1/reviews',
            array(
                'change'            => $change->getId(),
                'description'       => 'Test Review',
                'reviewers'         => array('tester'),
                'requiredReviewers' => $requiredReviews
            )
        );

        $this->assertSame($statusCode, $response->getStatusCode());

        // could not create the review, but was expected: pass
        if ($statusCode == 400) {
            return;
        }

        $participants = array(
            'admin'     => array(),
            'nonadmin'  => array(),
            'tester'    => array()
        );

        foreach ($resultRequired as $required) {
            $participants[$required] = array('required' => true);
        }
        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => 'Test Review',
                'participants'  => $participants,
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);

        // ensure record was really created
        $review = Review::fetch(2, $this->p4);
        $this->assertSame("Test Review", $review->get('description'));
    }

    public function testCreateReviewWithMentions()
    {
        $user = new User;
        $user->set(
            array(
                'User'      => 'sample',
                'Email'     => 'sample@example.com',
                'FullName'  => 'Seamus Ample',
                'Password'  => '123',
            )
        )->save();

        $change   = $this->createChange();
        $response = $this->post(
            '/api/v1/reviews',
            array(
                'change'      => $change->getId(),
                'description' => 'Test Review @sample',
                'reviewers'   => array('tester')
            )
        );

        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => 'Test Review @sample',
                'participants'  => array(
                    'admin'     => array(),
                    'nonadmin'  => array(),
                    'tester'    => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);

        // ensure record was really created
        $review = Review::fetch(2, $this->p4);
        $this->assertSame("Test Review @sample", $review->get('description'));


        // PROCESS QUEUE AND RETRY ABOVE
        $reviewId = $review->getId();
        $this->getRequest()->getQuery()->set('debug', 1)->set('retire', 1);
        $response = $this->dispatch('/queue/worker');

        $response = $this->get('/api/v1/reviews/' . $reviewId);
        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => 'Test Review @sample',
                'participants'  => array(
                    'admin'     => array(),
                    'nonadmin'  => array(),
                    'sample'    => array(),
                    'tester'    => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(
                    array(
                        'change'     => 1,
                        'user'       => 'admin',
                        'time'       => null,
                        'pending'    => false,
                        'difference' => 1,
                        'stream'     => null
                    )
                )
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated/versioned times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $actual['review']['versions'][0]['time'],
            $expected['review']['created'],
            $expected['review']['updated'],
            $expected['review']['versions'][0]['time']
        );
        $this->assertSame($actual, $expected);
    }

    public function testAddSubmittedChange()
    {
        $change1 = $this->createChange('test123', 'change description', '//depot/main/foo/change1.txt');
        $change2 = $this->createChange('xyz789', '2nd change description', '//depot/main/foo/change2.txt');
        $review  = Review::createFromChange($change1)->save();

        $result = $this->post(
            '/api/v1/reviews/' . $review->getId() . '/changes',
            array(
                'change' => $change2->getId(),
            )
        );

        $this->assertSame(200, $result->getStatusCode());

        $actual   = json_decode($this->getResponse()->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 3,
                'author'        => 'admin',
                'changes'       => array(1, 2),
                'commits'       => array(1, 2),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => "change description\n",
                'participants'  => array(
                    'admin'     => array(),
                    'nonadmin'  => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);

        $review = Review::fetch(3, $this->p4);
        $this->assertSame(array(1, 2), $review->getChanges());
        $this->assertSame(2, $review->getHeadChange());
    }

    public function testAddPendingChange()
    {
        $change1 = $this->createChange('test123', 'change description', '//depot/main/foo/change1.txt');
        $review  = Review::createFromChange($change1)->save();

        $review = Review::fetch($review->getId(), $this->p4);
        $this->assertSame(array(1), $review->getChanges());
        $this->assertFalse($review->isPending());

        $change2 = $this->createPendingChange('xyz789', '2nd change description', '//depot/main/foo/change2.txt');

        $result = $this->post(
            '/api/v1/reviews/' . $review->getId() . '/changes',
            array(
                'change' => $change2->getId(),
            )
        );

        $this->assertSame(200, $result->getStatusCode());

        $actual   = json_decode($this->getResponse()->getContent(), true);
        $expected = array(
            'review' => array(
                'id'            => 2,
                'author'        => 'admin',
                'changes'       => array(1, 3),
                'commits'       => array(1),
                'commitStatus'  => array(),
                'created'       => null,
                'deployDetails' => array(),
                'deployStatus'  => null,
                'description'   => "change description\n",
                'participants'  => array(
                    'admin'     => array(),
                    'nonadmin'  => array(),
                ),
                'pending'       => false,
                'projects'      => array(),
                'state'         => 'needsReview',
                'stateLabel'    => 'Needs Review',
                'testDetails'   => array(),
                'testStatus'    => null,
                'type'          => 'default',
                'updated'       => null,
                'versions'      => array(),
            )
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual['review']), array_keys($expected['review']));

        // discounting created/updated times, ensure we have the same data
        unset(
            $actual['review']['created'],
            $actual['review']['updated'],
            $expected['review']['created'],
            $expected['review']['updated']
        );
        $this->assertSame($actual, $expected);

        $review = Review::fetch($review->getId(), $this->p4);
        $this->assertSame(array(1, 3), $review->getChanges());
    }

    public function testBadAddChange()
    {
        $change1 = $this->createChange('test123', 'change description',   '//depot/main/foo/change1.txt');
        $change2 = $this->createChange('test567', 'change description 2', '//depot/main/foo/change1.txt');
        $review = Review::createFromChange($change1)->save();

        // only post should be accepted
        $this->get('/api/v1/reviews/3/changes?change=1');

        $this->assertSame(405, $this->getResponse()->getStatusCode());
        $this->assertSame($this->getResult()->getVariables(), array('error' => 'Method Not Allowed'));

        // review id must be specified
        $this->post('/api/v1/reviews//changes', array('change' => 2));
        $this->assertSame(404, $this->getResponse()->getStatusCode());

        // review id must exist
        $this->post('/api/v1/reviews/123/changes', array('change' => 2));
        $this->assertSame(404, $this->getResponse()->getStatusCode());

        // change must exist (not a 404)
        $this->post('/api/v1/reviews/3/changes', array('change' => 123));
        $this->assertSame(400, $this->getResponse()->getStatusCode());
    }

    public function testGetReviewList()
    {
        $response = $this->get('/api/v1/reviews');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"lastSeen":null,"reviews":[],"totalCount":null}', $response->getContent());

        $change = $this->createChange();
        $review = Review::createFromChange($change->getId(), $this->p4);
        $review->save();

        $response = $this->get('/api/v1/reviews');
        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'lastSeen'   => 2,
            'reviews'    => array(
                array(
                    'id'            => 2,
                    'author'        => 'admin',
                    'changes'       => array(1),
                    'comments'      => array(0, 0),
                    'commits'       => array(1),
                    'commitStatus'  => array(),
                    'created'       => null,
                    'deployDetails' => array(),
                    'deployStatus'  => null,
                    'description'   => "change description\n",
                    'participants'  => array(
                        'admin'     => array(),
                    ),
                    'pending'       => false,
                    'projects'      => array(),
                    'state'         => 'needsReview',
                    'stateLabel'    => 'Needs Review',
                    'testDetails'   => array(),
                    'testStatus'    => null,
                    'type'          => 'default',
                    'updated'       => null
                ),
            ),
            'totalCount' => null,
        );

        // ensure we have the same set of fields
        $this->assertSame(array_keys($actual), array_keys($expected));
        $this->assertSame(array_keys($actual['reviews'][0]), array_keys($expected['reviews'][0]));

        // remove fields that contain timestamps
        unset($actual['reviews'][0]['created']);
        unset($actual['reviews'][0]['updated']);
        unset($expected['reviews'][0]['created']);
        unset($expected['reviews'][0]['updated']);

        $this->assertSame($actual, $expected);
    }

    public function testGetReviewListLimitByFields()
    {
        $change = $this->createChange();
        $review = Review::createFromChange($change->getId(), $this->p4);
        $review->save();

        $response = $this->get(
            '/api/v1/reviews'
            . '?fields[]=id&fields[]=author&fields[]=description&fields[]=pending'
            . '&fields[]=state&fields[]=stateLabel'
        );
        $this->assertSame(200, $response->getStatusCode());

        $actual   = json_decode($response->getContent(), true);
        $expected = array(
            'lastSeen'   => 2,
            'reviews'    => array(
                array(
                    'id'            => 2,
                    'author'        => 'admin',
                    'description'   => "change description\n",
                    'pending'       => false,
                    'state'         => 'needsReview',
                    'stateLabel'    => 'Needs Review',
                ),
            ),
            'totalCount' => null,
        );

        $this->assertSame($actual, $expected);
    }

    /**
     * @dataProvider getReviewListFilterItems
     */
    public function testGetReviewListFilter($query, $expected, $status = 200)
    {
        $change1 = $this->createChange(
            'abc123',
            'This is my change. There are many like it, but this one is mine.',
            '//depot/main/foo/change1.txt'
        );
        $change2 = $this->createChange(
            'xyz123',
            'I. Am. Mine.',
            '//depot/main/foo/change2.txt'
        );
        $change3 = $this->createPendingChange(
            'lmnop12345',
            "I guess it's better to be who you are. Turns out people like you best that way, anyway.",
            '//depot/main/foo/change3.txt'
        );
        $change4 = $this->createPendingChange(
            'testTEST',
            "Uncle Sam Wants YOU!",
            '//depot/main/foo/change4.txt'
        );
        $change5 = $this->createPendingChange(
            'troopers',
            "They're doing their part. Are you?",
            '//depot/main/foo/change5.txt'
        );
        $change6 = $this->createPendingChange(
            'starship',
            "Join the Mobile Infantry and save the world. Service guarantees citizenship.",
            '//depot/main/foo/change6.txt'
        );

        // start our sample reviews
        $review7  = Review::createFromChange($change2->getId())->setProjects('test')
            ->setTestDetails(array('url' => 'http://localhost/build/7'))
            ->set('testStatus', 'fail')
            ->save();
        $review8  = Review::createFromChange($change1->getId())->setProjects('test')
            ->setTestDetails(array('url' => 'http://localhost/build/8'))
            ->set('testStatus', 'pass')
            ->save();
        $review9  = Review::createFromChange($change6->getId())
            ->addParticipant(array('nonadmin', 'admin'))
            ->setState('needsRevision')
            ->save();

        $response = $this->get('/api/v1/reviews', $query);

        $this->assertSame($status, $response->getStatusCode());

        $actual            = json_decode($response->getContent(), true);
        $actual['reviews'] = array_map(
            function ($array) {
                return $array['id'];
            },
            $actual['reviews']
        );

        $this->assertSame($actual, $expected);
    }

    public function getReviewListFilterItems()
    {
        return array(
            array(
                array('keywords' => 'anyway'),
                array('lastSeen' => null, 'reviews' => array(), 'totalCount' => 0)
            ),
            array(
                array('keywords' => 'am'),
                array('lastSeen' => 7, 'reviews' => array(7), 'totalCount' => 1)
            ),
            array(
                array('keywords' => 'mine'),
                array('lastSeen' => 7, 'reviews' => array(8,7), 'totalCount' => 2)
            ),
            array(
                array('max' => 1, 'keywords' => 'mine'),
                array('lastSeen' => 8, 'reviews' => array(8), 'totalCount' => 2)
            ),
            array(
                array('max' => 1, 'after' => 8, 'keywords' => 'mine'),
                array('lastSeen' => 7, 'reviews' => array(7), 'totalCount' => 2)
            ),
            array(
                array('change' => 3),
                array('lastSeen' => null, 'reviews' => array(), 'totalCount' => 0)
            ),
            array(
                array('change' => 2),
                array('lastSeen' => 7, 'reviews' => array(7), 'totalCount' => 1)
            ),
            array(
                array('participants' => 'nonadmin'),
                array('lastSeen' => 9, 'reviews' => array(9), 'totalCount' => 1)
            ),
            array(
                array('project' => 'test'),
                array('lastSeen' => 7, 'reviews' => array(8,7), 'totalCount' => 2)
            ),
            array(
                array('state' => 'needsRevision'),
                array('lastSeen' => 9, 'reviews' => array(9), 'totalCount' => 1)
            ),
            array(
                array('passesTests' => 'false'),
                array('lastSeen' => 7, 'reviews' => array(7), 'totalCount' => 1)
            ),
            array(
                array('passesTests' => 'true'),
                array('lastSeen' => 8, 'reviews' => array(8), 'totalCount' => 1)
            ),
            array(
                array('hasReviewers' => 1),
                array('lastSeen' => 9, 'reviews' => array(9), 'totalCount' => 1)
            ),
            array(
                array('hasReviewers' => 'true'),
                array('lastSeen' => 9, 'reviews' => array(9), 'totalCount' => 1)
            ),
            array(
                array('hasReviewers' => ''),
                array('lastSeen' => 9, 'reviews' => array(9), 'totalCount' => 1)
            ),
            array(
                array('hasReviewers' => 'false'),
                array('lastSeen' => 7, 'reviews' => array(8,7), 'totalCount' => 2)
            ),
            array(
                array('hasReviewers' => '0'),
                array('lastSeen' => 7, 'reviews' => array(8,7), 'totalCount' => 2)
            ),
        );
    }

    /**
     * Helper functions
     */
    protected function createChange(
        $content = 'xyz123',
        $description = 'change description',
        $filespec = '//depot/main/foo/test.txt'
    ) {
        $file = new File($this->p4);
        $file->setFilespec($filespec)
            ->open()
            ->setLocalContents($content)
            ->submit($description);

        return $file->getChange();
    }

    protected function createPendingChange(
        $content = 'xyz123',
        $description = 'change description',
        $filespec = '//depot/main/foo/test.txt'
    ) {
        $change = new Change($this->p4);
        $change->setDescription($description)->save();

        $file = new File($this->p4);
        $file->setFilespec($filespec);
        $file->setLocalContents($content);
        $file->add($change->getId());

        $this->p4->run('shelve', array('-c', $change->getId(), '//...'));
        $this->p4->run('revert', array('//...'));

        $change->setUser('nonadmin')->save();

        return $change;
    }

    protected function get($path, $params = null)
    {
        $this->resetApplication();

        $query = $params instanceof Parameters ? $params : new Parameters($params);
        $this->getRequest()
            ->setMethod(Request::METHOD_GET)
            ->setQuery($query);

        $this->dispatch($path);

        return $this->getResponse();
    }

    protected function post($path, $params)
    {
        $this->resetApplication();

        $post = $params instanceof Parameters ? $params : new Parameters($params);
        $this->getRequest()
             ->setMethod(Request::METHOD_POST)
             ->setPost($post);

        $this->dispatch($path);

        return $this->getResponse();
    }

    protected function postJson($path, $params)
    {
        $this->resetApplication();

        $params  = $params instanceof Parameters ? $params->toArray() : $params;
        $request = $this->getRequest();
        $headers = $request->getHeaders();
        $headers->addHeaderLine('Content-type: application/json');
        $request->setHeaders($headers);
        $request->setMethod(Request::METHOD_POST)
                ->setContent(json_encode($params));

        $this->dispatch($path);

        return $this->getResponse();
    }
}
