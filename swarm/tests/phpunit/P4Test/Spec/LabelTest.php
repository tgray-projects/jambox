<?php
/**
 * Test methods for the P4 Label class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Label;
use P4\Spec\User;

class LabelTest extends TestCase
{
    /**
     * Test initial conditions.
     */
    public function testInitialConditions()
    {
        $labels = Label::fetchAll();
        $this->assertSame(0, count($labels), 'Expected labels at start.');

        $this->assertFalse(
            Label::exists('foobar'),
            'Expect bogus label to not exist.'
        );
        $this->assertFalse(
            Label::exists(123),
            'Expect invalid label to not exist.'
        );
    }

    /**
     * Test a fresh in-memory Label object.
     */
    public function testFreshObject()
    {
        $label = new Label;
        $this->assertSame(
            null,
            $label->getUpdateDateTime(),
            'Expected update datetime'
        );
        $this->assertSame(
            null,
            $label->getAccessDateTime(),
            'Expected access datetime'
        );
    }

    /**
     * Test accessors/mutators.
     */
    public function testAccessorsMutators()
    {
        $label = new Label;
        $tests = array(
            'Label'       => 'zlabel',
            'Owner'       => 'bob',
            'Description' => 'zdescription',
            'Options'     => 'zoptions',
            'Revision'    => 'zrevision',
        );

        foreach ($tests as $key => $value) {
            $label->set($key, $value);
            $this->assertSame($value, $label->get($key), "Expected value for $key");
        }

        $view = array('aview');
        $label->set('View', $view);
        $this->assertSame(
            $view,
            $label->get('View'),
            'Expected view.'
        );
    }

    /**
     * Test setView.
     */
    public function testSetView()
    {
        $badTypeError = "Each view entry must be a non-empty string.";

        $tests = array(
            array(
                'label'  => __LINE__ .': null',
                'view'   => null,
                'error'  => 'View must be passed as array.',
            ),
            array(
                'label'  => __LINE__ .': empty array',
                'view'   => array(),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array containing int',
                'view'   => array(12),
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with string',
                'view'   => array('a string'),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array with strings',
                'view'   => array('a string', 'another string', "third 'entry'"),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array with empty array',
                'view'   => array(array()),
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with good array + bad array',
                'view'   => array(
                    '//test/path',
                    array(),
                ),
                'error'  => $badTypeError,
            ),
        );

        foreach ($tests as $test) {
            $title = $test['label'];
            $label = new Label;

            try {
                $label->setView($test['view']);
                if ($test['error']) {
                    $this->fail("$title - unexpected success");
                }
            } catch (\InvalidArgumentException $e) {
                if ($test['error']) {
                    $this->assertSame(
                        $test['error'],
                        $e->getMessage(),
                        "$title - Expected error message."
                    );
                } else {
                    $this->fail("$title - unexpected argument exception.");
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                $this->fail(
                    "$title - Unexpected exception (". get_class($e)
                    .") - ". $e->getMessage()
                );
            }

            if (!$test['error']) {
                $expect = array_key_exists('expect', $test) ? $test['expect'] : $test['view'];
                $this->assertSame(
                    $expect,
                    $label->getView(),
                    "$title - expected view after set"
                );
            }
        }

    }

    /**
     * Test addView.
     */
    public function testAddView()
    {
        $error = "Each view entry must be a non-empty string.";
        $tests = array(
            array(
                'title'  => __LINE__ .': int',
                'view'   => 12,
                'error'  => $error,
            ),
            array(
                'title'  => __LINE__ .': string',
                'view'   => 'a string',
                'out'    => array('a string'),
                'error'  => false,
            ),
            array(
                'title'  => __LINE__ .': empty string',
                'view'   => '',
                'error'  => $error,
            ),
        );

        foreach ($tests as $test) {
            $title = $test['title'];
            $label = new Label;
            try {
                $label->addView($test['view']);
                if ($test['error']) {
                    $this->fail("$title - unexpected success");
                }
            } catch (\InvalidArgumentException $e) {
                if ($test['error']) {
                    $this->assertSame(
                        $test['error'],
                        $e->getMessage(),
                        'Expected error message.'
                    );
                } else {
                    $this->fail("$title - unexpected argument exception.");
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                $this->fail(
                    "$title - Unexpected exception (". get_class($e)
                    .") - ". $e->getMessage()
                );
            }

            if (!$test['error']) {
                $this->assertSame(
                    $test['out'],
                    $label->getView(),
                    "$title - expected view after set"
                );
            }
        }

    }

    /**
     * Test calling setOwner with a User
     */
    public function testSetOwnerByObject()
    {
        $user = new User;
        $user->setId('user1');

        $label = new Label;
        $label->setId('test')->setOwner($user);

        $this->assertSame(
            'user1',
            $label->getOwner(),
            'Expected matching owner'
        );

        $label->save();
        $this->assertSame(
            'user1',
            $label->getOwner(),
            'Expected matching owner post save'
        );

    }

    /**
     * Test setId, setDescription, setOptions, setRevision
     */
    public function testSetIdDescriptionOptionsOwnerRevision()
    {
        $tests = array(
            array(
                'label' => __LINE__ .': null',
                'value' => null,
                'error' => false,
            ),
            array(
                'label' => __LINE__ .': empty string',
                'value' => '',
                'error' => false,
            ),
            array(
                'label' => __LINE__ .': string',
                'value' => 'bob',
                'error' => false,
            ),
            array(
                'label' => __LINE__ .': integer',
                'value' => 3,
                'error' => true,
            ),
        );

        $types = array(
            'Id' => 'Cannot set id. Id is invalid.',
            'Description' =>  "Description must be a string or null.",
            'Options' =>  "Options must be a string or null.",
            'Owner' =>  "Owner must be a string, P4\Spec\User or null.",
            'Revision' =>  "Revision must be a string or null."
        );
        foreach ($types as $type => $expectedError) {
            $setMethod = "set$type";
            $getMethod = "get$type";

            foreach ($tests as $test) {
                $title = $test['label'] ." ($type)";
                $label = new Label;

                // id fails on empty string; adjust expectation here
                if ($type == 'Id' && preg_match('/empty string/', $title)) {
                    $test['error'] = true;
                }

                try {
                    $label->$setMethod($test['value']);
                    if ($test['error']) {
                        $this->fail("$title - unexpected success");
                    }
                } catch (\InvalidArgumentException $e) {
                    if ($test['error']) {
                        $this->assertSame(
                            $expectedError,
                            $e->getMessage(),
                            "$title - Expected error message."
                        );
                    } else {
                        $this->fail("$title - unexpected argument exception.");
                    }
                } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                    $this->fail($e->getMessage());
                } catch (\Exception $e) {
                    $this->fail(
                        "$title - Unexpected exception (". get_class($e)
                        .") - ". $e->getMessage()
                    );
                }

                if (!$test['error']) {
                    $this->assertSame(
                        $test['value'],
                        $label->$getMethod(),
                        "$title - expected $type after set"
                    );
                }
            }
        }
    }

    /**
     * Exercises the set revision function with quotes/hashes in it which could cause issue
     */
    public function testSetRevisionWithHash()
    {
        $tests = array(
            array(
                'value' => '#1',
                'out'   => '#1',
            ),
            array(
                'value' => '\'#1',
                'out'   => '\'#1',
            ),
        );

        foreach ($tests as $test) {
            $label = new Label;
            $label->setId('test');

            $label->setRevision($test['value']);

            $this->assertSame(
                $test['out'],
                $label->getRevision(),
                'Expected matching revision for input: '.$test['value']
            );

            $label->save();

            $this->assertSame(
                $test['out'],
                Label::fetch('test')->getRevision(),
                'Expected, post save, matching revision for input: '.$test['value']
            );
        }
    }

    /**
     * Test fetchAll filtered by Owner
     */
    public function testFetchAllByOwner()
    {
        $label = new Label;
        $label->setId('test2-label')->setOwner('user1')->save();
        $label->setId('test3-label')->setOwner('user1')->save();
        $label->setId('test3-labelb')->setOwner('user2')->save();


        $byOwner = Label::fetchAll(array(Label::FETCH_BY_OWNER => 'user1'));

        $this->assertSame(
            2,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test2-label',
            $byOwner->first()->getId(),
            'Expected first result label to match'
        );

        $this->assertSame(
            'user1',
            $byOwner->first()->getOwner(),
            'Expected first result user to match'
        );

        $this->assertSame(
            'test3-label',
            $byOwner->nth(1)->getId(),
            'Expected second result label to match'
        );

        $this->assertSame(
            'user1',
            $byOwner->nth(1)->getOwner(),
            'Expected second result user to match'
        );

        // Verify invalid names causes error
        $tests = array(
            __LINE__ .' int'  => 10,
            __LINE__ .' bool' => false
        );

        foreach ($tests as $label => $value) {
            try {
                Label::fetchAll(array(Label::FETCH_BY_OWNER => $value));

                $this->fail($label.': Expected filter to fail');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    'Filter by Owner expects a non-empty string as input',
                    $e->getMessage(),
                    $label.':Unexpected Exception message'
                );
            } catch (\Exception $e) {
                $this->fail(
                    $label.':Unexpected Exception ('. get_class($e) .'): '. $e->getMessage()
                );
            }
        }

    }

    /**
     * Test fetchAll filtered by Name
     */
    public function testFetchAllByName()
    {
        // 'test-label' will exist out of the gate; add a couple more to make it a real test.

        $label = new Label;
        $label->setId('test2-label')->setOwner('user1')->save();
        $label->setId('test3-label')->setOwner('user1')->save();
        $label->setId('test3-labelb')->setOwner('user2')->save();


        $byOwner = Label::fetchAll(array(Label::FETCH_BY_NAME => 'test3-*'));

        $this->assertSame(
            2,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test3-label',
            $byOwner->first()->getId(),
            'Expected first result label to match'
        );

        $this->assertSame(
            'test3-labelb',
            $byOwner->nth(1)->getId(),
            'Expected second result label to match'
        );

        // Verify invalid names causes error
        $tests = array(
            __LINE__ .' empty string' => "",
            __LINE__ .' bool'         => false
        );

        foreach ($tests as $label => $value) {
            try {
                Label::fetchAll(array(Label::FETCH_BY_NAME => $value));

                $this->fail($label.': Expected filter to fail');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    'Filter by Name expects a non-empty string as input',
                    $e->getMessage(),
                    $label.':Unexpected Exception message'
                );
            } catch (\Exception $e) {
                $this->fail(
                    $label.':Unexpected Exception ('. get_class($e) .'): '. $e->getMessage()
                );
            }
        }
    }

    /**
     * Test fetchAll filtered by Owner and filtered by Name
     */
    public function testFetchAllByOwnerAndName()
    {
        // 'test-label' will exist out of the gate; add a couple more to make it a real test.

        $label = new Label;
        $label->setId('test2-label')->setOwner('user1')->save();
        $label->setId('test3-label')->setOwner('user1')->save();
        $label->setId('test3-labelb')->setOwner('user2')->save();


        $byOwner = Label::fetchAll(
            array(
                Label::FETCH_BY_NAME  => 'test3-*',
                Label::FETCH_BY_OWNER => 'user1'
            )
        );

        $this->assertSame(
            1,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test3-label',
            $byOwner->first()->getId(),
            'Expected first result label to match'
        );
    }
}
