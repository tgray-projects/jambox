<?php
/**
 * Test methods for the P4 Branch class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Branch;

class BranchTest extends TestCase
{
    /**
     * Test initial conditions.
     */
    public function testInitialConditions()
    {
        $branches = Branch::fetchAll();
        $this->assertSame(0, count($branches), 'Expected branches at start.');

        $this->assertFalse(
            Branch::exists('foobar'),
            'Expect bogus branch to not exist.'
        );
        $this->assertFalse(
            Branch::exists(123),
            'Expect invalid branch to not exist.'
        );
    }

    /**
     * Test a fresh in-memory Branch object.
     */
    public function testFreshObject()
    {
        $branch = new Branch;
        $this->assertSame(
            null,
            $branch->getUpdateDateTime(),
            'Expected update datetime'
        );
        $this->assertSame(
            null,
            $branch->getAccessDateTime(),
            'Expected access datetime'
        );
    }

    /**
     * Test accessors/mutators.
     */
    public function testAccessorsMutators()
    {
        $branch = new Branch;
        $tests = array(
            'Branch'        => 'zbranch',
            'Owner'         => 'bob',
            'Description'   => 'zdescription',
            'Options'       => 'zoptions',
        );

        foreach ($tests as $key => $value) {
            $branch->set($key, $value);
            $this->assertSame($value, $branch->get($key), "Expected value for $key");
        }

        $view = array(
            array('source' => '//depot/aview/*', 'target' => '//depot/bview/*')
        );
        $branch->set('View', $view);
        $this->assertSame(
            $view,
            $branch->get('View'),
            'Expected view.'
        );
    }

    /**
     * Test setView.
     */
    public function testSetView()
    {
        $badTypeError = "Each view entry must be a 'source' and 'target' array or a string.";
        $badFormatError = "Each view entry must contain two depot paths, no more, no less.";
        $tests = array(
            array(
                'branch'  => __LINE__ .': null',
                'view'   => null,
                'expect' => false,
                'error'  => 'View must be passed as array.',
            ),
            array(
                'branch'  => __LINE__ .': empty array',
                'view'   => array(),
                'expect' => array(),
                'error'  => false,
            ),
            array(
                'branch'  => __LINE__ .': array containing int',
                'view'   => array(12),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'branch'  => __LINE__ .': array with empty string',
                'view'   => array(''),
                'expect' => false,
                'error'  => $badFormatError,
            ),
            array(
                'branch'  => __LINE__ .': array with bogus string',
                'view'   => array('qstring'),
                'expect' => false,
                'error'  => $badFormatError,
            ),
            array(
                'branch'  => __LINE__ .': array with string, integer',
                'view'   => array('a string', 12),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'branch'  => __LINE__ .': array with string, bogus string',
                'view'   => array('a string', 'bogus'),
                'expect' => false,
                'error'  => $badFormatError,
            ),
            array(
                'branch'  => __LINE__ .': array with string',
                'view'   => array('a string'),
                'expect' => array(
                    array('source' => 'a', 'target' => 'string'),
                ),
                'error'  => false,
            ),
            array(
                'branch'  => __LINE__ .': array with strings',
                'view'   => array('a string', '"another" string', "third 'entry'"),
                'expect' => array(
                    array('source' => 'a',       'target' => 'string'),
                    array('source' => 'another', 'target' => 'string'),
                    array('source' => 'third',   'target' => "'entry'"),
                ),
                'error'  => false,
            ),
            array(
                'branch'  => __LINE__ .': array with empty array',
                'view'   => array(array()),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'branch'  => __LINE__ .': array with good array + bad array',
                'view'   => array(
                    array('source' => 'depot', 'target' => 'client'),
                    array(),
                ),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'branch'  => __LINE__ .': array with array, no target',
                'view'   => array(array('source' => 'a')),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'branch'  => __LINE__ .': array with array, no source',
                'view'   => array(array('target' => 'a')),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'branch'  => __LINE__ .': array with array + extra',
                'view'   => array(
                    array('source' => 'depot', 'target' => 'client', 'a' => 'b')
                ),
                'expect' => array(
                    array('source' => 'depot', 'target' => 'client'),
                ),
                'error'  => false,
            ),
            array(
                'branch'  => __LINE__ .': array with good array + string',
                'view'   => array(
                    array('source' => 'depot', 'target' => 'client'),
                    'path1 path2',
                ),
                'expect' => array(
                    array('source' => 'depot',   'target' => 'client'),
                    array('source' => 'path1', 'target' => 'path2'),
                ),
                'error'  => false,
            ),
            array(
                'branch'  => __LINE__ .': array with good arrays',
                'view'   => array(
                    array('source' => 'depot',  'target' => 'client'),
                    array('source' => 'builds', 'target' => 'buildClient'),
                    array('source' => 'cms',    'target' => 'cmsClient'),
                ),
                'expect' => array(
                    array('source' => 'depot',  'target' => 'client'),
                    array('source' => 'builds', 'target' => 'buildClient'),
                    array('source' => 'cms',    'target' => 'cmsClient'),
                ),
                'error'  => false,
            ),
        );

        foreach ($tests as $test) {
            $title = $test['branch'];
            $branch = new Branch;
            try {
                $branch->setView($test['view']);
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
                    $test['expect'],
                    $branch->getView(),
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
        $tests = array(
            array(
                'label'     => __LINE__ .': null, null',
                'source'     => null,
                'target'    => null,
                'error'     => "Each view entry must be a 'source' and 'target' array or a string.",
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': numeric, numeric',
                'source'     => 1,
                'target'    => 2,
                'error'     => "Each view entry must be a 'source' and 'target' array or a string.",
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': null, string',
                'source'     => null,
                'target'    => 'string',
                'error'     => "Each view entry must be a 'source' and 'target' array or a string.",
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': string, null',
                'source'     => 'string',
                'target'    => null,
                'error'     => "Each view entry must be a 'source' and 'target' array or a string.",
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': numeric, string',
                'source'     => 1,
                'target'    => 'string',
                'error'     => "Each view entry must be a 'source' and 'target' array or a string.",
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': string, numeric',
                'source'     => 'string',
                'target'    => 1,
                'error'     => "Each view entry must be a 'source' and 'target' array or a string.",
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': empty, empty',
                'source'     => 'source',
                'target'    => 'target',
                'error'     => false,
                'expect'    => array(
                    array(
                        'source'    => 'source',
                        'target'    => 'target',
                    ),
                ),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $branch = new Branch;
            try {
                $branch->addView($test['source'], $test['target']);
                if ($test['error']) {
                    $this->fail("$label - unexpected success");
                }
            } catch (\InvalidArgumentException $e) {
                if ($test['error']) {
                    $this->assertSame(
                        $test['error'],
                        $e->getMessage(),
                        'Expected error message.'
                    );
                } else {
                    $this->fail("$label - unexpected argument exception.");
                }
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                $this->fail($e->getMessage());
            } catch (\Exception $e) {
                $this->fail(
                    "$label - Unexpected exception (". get_class($e)
                    .") - ". $e->getMessage()
                );
            }

            if (!$test['error']) {
                $this->assertSame(
                    $test['expect'],
                    $branch->getView(),
                    "$label - expected view after set"
                );
            }
        }
    }

    /**
     * Test setId, setDescription, setOptions, setOwners
     */
    public function testSetIdDescriptionOptionsOwner()
    {
        $tests = array(
            array(
                'title' => __LINE__ .': null',
                'value' => null,
                'error' => false,
            ),
            array(
                'title' => __LINE__ .': empty string',
                'value' => '',
                'error' => false,
            ),
            array(
                'title' => __LINE__ .': string',
                'value' => 'bob',
                'error' => false,
            ),
            array(
                'title' => __LINE__ .': integer',
                'value' => 3,
                'error' => true,
            ),
        );

        $types = array('Id', 'Description', 'Options', 'Owner');
        foreach ($types as $type) {
            $setMethod = "set$type";
            $getMethod = "get$type";

            foreach ($tests as $test) {
                $title = $test['title'] ." ($type)";
                $branch = new Branch;

                // setup the expected error message
                $expectedError = "$type must be a string or null.";

                // Id has a different error; set it here
                if ($type == 'Id') {
                    $expectedError = 'Cannot set id. Id is invalid.';

                    // Id fails on empty string, unlike the others, tweak here
                    if (preg_match('/empty string/', $title)) {
                        $test['error'] = true;
                    }
                }

                try {
                    $branch->$setMethod($test['value']);
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
                        $branch->$getMethod(),
                        "$title - expected $type after set"
                    );
                }
            }
        }
    }

    /**
     * Test fetchAll filtered by Owner
     */
    public function testFetchAllByOwner()
    {
        $branch = new Branch;
        $view = array('//depot/testa/... //depot/testb/...');
        $branch->setId('test2-branch')->setOwner('user1')->setView($view)->save();
        $branch->setId('test3-branch')->setOwner('user1')->setView($view)->save();
        $branch->setId('test3-branchb')->setOwner('user2')->setView($view)->save();


        $byOwner = Branch::fetchAll(array(Branch::FETCH_BY_OWNER => 'user1'));

        $this->assertSame(
            2,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test2-branch',
            $byOwner->first()->getId(),
            'Expected first result branch to match'
        );

        $this->assertSame(
            'user1',
            $byOwner->first()->getOwner(),
            'Expected first result user to match'
        );

        $this->assertSame(
            'test3-branch',
            $byOwner->nth(1)->getId(),
            'Expected second result branch to match'
        );

        $this->assertSame(
            'user1',
            $byOwner->nth(1)->getOwner(),
            'Expected second result user to match'
        );

        // Verify invalid names causes error
        $tests = array(
            __LINE__.' int'     => 10,
            __LINE__.' bool'    => false
        );

        foreach ($tests as $branch => $value) {
            try {
                Branch::fetchAll(array(Branch::FETCH_BY_OWNER => $value));

                $this->fail($branch.': Expected filter to fail');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    'Filter by Owner expects a non-empty string as input',
                    $e->getMessage(),
                    $branch.':Unexpected Exception message'
                );
            } catch (\Exception $e) {
                $this->fail(
                    $branch.':Unexpected Exception ('. get_class($e) .'): '. $e->getMessage()
                );
            }
        }

    }

    /**
     * Test fetchAll filtered by Name
     */
    public function testFetchAllByName()
    {
        // 'test-branch' will exist out of the gate; add a couple more to make it a real test.

        $branch = new Branch;
        $view   = array('//depot/testa/... //depot/testb/...');
        $branch->setId('test2-branch')->setOwner('user1')->setView($view)->save();
        $branch->setId('test3-branch')->setOwner('user1')->setView($view)->save();
        $branch->setId('test3-branchb')->setOwner('user2')->setView($view)->save();


        $byOwner = Branch::fetchAll(array(Branch::FETCH_BY_NAME => 'test3-*'));

        $this->assertSame(
            2,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test3-branch',
            $byOwner->first()->getId(),
            'Expected first result branch to match'
        );

        $this->assertSame(
            'test3-branchb',
            $byOwner->nth(1)->getId(),
            'Expected second result branch to match'
        );

        // Verify invalid names causes error
        $tests = array(
            __LINE__.' empty string'    => "",
            __LINE__.' bool'            => false
        );

        foreach ($tests as $branch => $value) {
            try {
                Branch::fetchAll(array(Branch::FETCH_BY_NAME => $value));

                $this->fail($branch.': Expected filter to fail');
            } catch (\PHPUnit\Framework\AssertionFailedError $e) {
                throw $e;
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    'Filter by Name expects a non-empty string as input',
                    $e->getMessage(),
                    $branch.':Unexpected Exception message'
                );
            } catch (\Exception $e) {
                $this->fail(
                    $branch.':Unexpected Exception ('. get_class($e) .'): '. $e->getMessage()
                );
            }
        }
    }

    /**
     * Test fetchAll filtered by Owner and filtered by Name
     */
    public function testFetchAllByOwnerAndName()
    {
        // 'test-branch' will exist out of the gate; add a couple more to make it a real test.

        $branch = new Branch;
        $view = array('//depot/testa/... //depot/testb/...');
        $branch->setId('test2-branch')->setOwner('user1')->setView($view)->save();
        $branch->setId('test3-branch')->setOwner('user1')->setView($view)->save();
        $branch->setId('test3-branchb')->setOwner('user2')->setView($view)->save();


        $byOwner = Branch::fetchAll(
            array(
                Branch::FETCH_BY_NAME  => 'test3-*',
                Branch::FETCH_BY_OWNER => 'user1'
            )
        );

        $this->assertSame(
            1,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test3-branch',
            $byOwner->first()->getId(),
            'Expected first result branch to match'
        );
    }
}
