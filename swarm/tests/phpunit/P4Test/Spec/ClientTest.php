<?php
/**
 * Test methods for the P4 Client class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Client;

class ClientTest extends TestCase
{
    /**
     * Test initial conditions.
     */
    public function testInitialConditions()
    {
        $clients = Client::fetchAll();
        $this->assertSame(1, count($clients), 'Expected clients at start.');
        $client = $clients->first();
        $this->assertSame($this->getP4Params('client'), $client->getId(), 'Expected client name.');
        $this->assertSame(
            $this->getP4Params('user'),
            $client->getOwner(),
            'Expected client owner.'
        );
        $this->assertSame(
            $this->getP4Params('clientRoot') .'/superuser',
            $client->getRoot(),
            'Expected client root.'
        );
        $this->assertSame('', $client->getHost(), 'Expected client host.');
        $this->assertSame('', $client->getDescription(), 'Expected client description.');
        $this->assertSame('local', $client->getLineEnd(), 'Expected client line ending.');
        $this->assertSame(
            array(
                'noallwrite', 'noclobber', 'nocompress', 'unlocked',
                'nomodtime', 'normdir',
            ),
            $client->getOptions(),
            'Expected options'
        );
        $this->assertSame(
            array(
                array(
                    'depot'  => '//depot/...',
                    'client' => '//'. $this->getP4Params('client') .'/...',
                ),
            ),
            $client->getView(),
            'Expected client view.'
        );

        $this->assertTrue(
            Client::exists($this->getP4Params('client')),
            'Expect configured client to exist.'
        );
        $this->assertFalse(
            Client::exists('foobar'),
            'Expect bogus client to not exist.'
        );
        $this->assertFalse(
            Client::exists(123),
            'Expect invalid client to not exist.'
        );
    }

    /**
     * Test a fresh in-memory Client object.
     */
    public function testFreshObject()
    {
        $client = new Client;
        $this->assertSame(
            null,
            $client->getUpdateDateTime(),
            'Expected update datetime'
        );
        $this->assertSame(
            null,
            $client->getAccessDateTime(),
            'Expected access datetime'
        );
        $this->assertSame(
            null,
            $client->getRoot(),
            'Expected null client root'
        );
    }

    /**
     * Test accessors/mutators.
     */
    public function testAccessorsMutators()
    {
        $client = new Client;
        $tests = array(
            'Client'      => 'zclient',
            'Description' => 'zdesc',
            'Host'        => 'zhost',
            'LineEnd'     => 'zle',
            'Owner'       => 'bob',
            'Root'        => 'zroot',
        );

        foreach ($tests as $key => $value) {
            $client->set($key, $value);
            $this->assertSame($value, $client->get($key), "Expected value for $key");
        }

        $client->set('View', array('a view'));
        $this->assertSame(
            array(array('depot' => 'a', 'client' => 'view')),
            $client->get('View'),
            'Expected view.'
        );
    }

    /**
     * Test setView.
     */
    public function testSetView()
    {
        $badTypeError = "Each view entry must be a 'depot' and 'client' array or a string.";
        $badFormatError = "Each view entry must contain two paths, no more, no less.";
        $tests = array(
            array(
                'label'  => __LINE__ .': null',
                'view'   => null,
                'expect' => false,
                'error'  => 'View must be passed as array.',
            ),
            array(
                'label'  => __LINE__ .': empty array',
                'view'   => array(),
                'expect' => array(),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array containing int',
                'view'   => array(12),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with empty string',
                'view'   => array(''),
                'expect' => false,
                'error'  => $badFormatError,
            ),
            array(
                'label'  => __LINE__ .': array with bogus string',
                'view'   => array('qstring'),
                'expect' => false,
                'error'  => $badFormatError,
            ),
            array(
                'label'  => __LINE__ .': array with string, integer',
                'view'   => array('a string', 12),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with string, bogus string',
                'view'   => array('a string', 'bogus'),
                'expect' => false,
                'error'  => $badFormatError,
            ),
            array(
                'label'  => __LINE__ .': array with string',
                'view'   => array('a string'),
                'expect' => array(
                    array('depot' => 'a', 'client' => 'string'),
                ),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array with strings',
                'view'   => array('a string', '"another" string', "third 'entry'"),
                'expect' => array(
                    array('depot' => 'a',       'client' => 'string'),
                    array('depot' => 'another', 'client' => 'string'),
                    array('depot' => 'third',   'client' => "'entry'"),
                ),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array with empty array',
                'view'   => array(array()),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with good array + bad array',
                'view'   => array(
                    array('depot' => 'depot', 'client' => 'client'),
                    array(),
                ),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with array, no client',
                'view'   => array(array('depot' => 'a')),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with array, no depot',
                'view'   => array(array('client' => 'a')),
                'expect' => false,
                'error'  => $badTypeError,
            ),
            array(
                'label'  => __LINE__ .': array with array + extra',
                'view'   => array(
                    array('depot' => 'depot', 'client' => 'client', 'a' => 'b')
                ),
                'expect' => array(
                    array('depot' => 'depot', 'client' => 'client'),
                ),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array with good array + string',
                'view'   => array(
                    array('depot' => 'depot', 'client' => 'client'),
                    'another client',
                ),
                'expect' => array(
                    array('depot' => 'depot',   'client' => 'client'),
                    array('depot' => 'another', 'client' => 'client'),
                ),
                'error'  => false,
            ),
            array(
                'label'  => __LINE__ .': array with good arrays',
                'view'   => array(
                    array('depot' => 'depot',  'client' => 'client'),
                    array('depot' => 'builds', 'client' => 'buildClient'),
                    array('depot' => 'cms',    'client' => 'cmsClient'),
                ),
                'expect' => array(
                    array('depot' => 'depot',  'client' => 'client'),
                    array('depot' => 'builds', 'client' => 'buildClient'),
                    array('depot' => 'cms',    'client' => 'cmsClient'),
                ),
                'error'  => false,
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $client = new Client;
            try {
                $client->setView($test['view']);
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
                    $client->getView(),
                    "$label - expected view after set"
                );
            }
        }
    }

    /**
     * Test addView.
     */
    public function testAddView()
    {
        $error = "Each view entry must be a 'depot' and 'client' array or a string.";
        $tests = array(
            array(
                'label'     => __LINE__ .': null, null',
                'depot'     => null,
                'client'    => null,
                'error'     => $error,
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': numeric, numeric',
                'depot'     => 1,
                'client'    => 2,
                'error'     => $error,
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': null, string',
                'depot'     => null,
                'client'    => 'string',
                'error'     => $error,
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': string, null',
                'depot'     => 'string',
                'client'    => null,
                'error'     => $error,
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': numeric, string',
                'depot'     => 1,
                'client'    => 'string',
                'error'     => $error,
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': string, numeric',
                'depot'     => 'string',
                'client'    => 1,
                'error'     => $error,
                'expect'    => array(),
            ),
            array(
                'label'     => __LINE__ .': empty, empty',
                'depot'     => 'depot',
                'client'    => 'client',
                'error'     => false,
                'expect'    => array(
                    array(
                        'depot'     => 'depot',
                        'client'    => 'client',
                    ),
                ),
            ),
        );

        foreach ($tests as $test) {
            $label = $test['label'];
            $client = new Client;
            try {
                $client->addView($test['depot'], $test['client']);
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
                    $client->getView(),
                    "$label - expected view after set"
                );
            }
        }

    }

    /**
     * test the touchup view function
     */
    public function testTouchUpView()
    {
        $expected = array(
            array(
                'depot' => '//depot/foo/...',
                'client' => '//newclient/foo/...',
            ),
            array(
                'depot' => '//depot/foo/...',
                'client' => '//newclient/foo/...',
            ),
            array(
                'depot' => '//depot/foo/...',
                'client' => '//newclient/foo/...',
            )
        );

        $client = new Client;
        $client->setView(
            array(
                '//depot/foo/... //oldclient-1/foo/...',
                '//depot/foo/... //oldClient2/foo/...',
                '//depot/foo/... //newclient/foo/...'
            )
        );
        $client->setId('newclient')->touchUpView();

        $this->assertSame($expected, $client->getView());
    }

    /**
     * Test setOptionsSubmitOptions.
     */
    public function testSetOptionsSubmitOptions()
    {
        $tests = array(
            array(
                'label'   => __LINE__ .': null',
                'options' => null,
                'expect'  => false,
                'error'   => true,
            ),
            array(
                'label'   => __LINE__ .': empty string',
                'options' => '',
                'expect'  => array(),
                'error'   => false,
            ),
            array(
                'label'   => __LINE__ .': string',
                'options' => 'bob',
                'expect'  => array('bob'),
                'error'   => false,
            ),
            array(
                'label'   => __LINE__ .': integer',
                'options' => 3,
                'expect'  => false,
                'error'   => true,
            ),
            array(
                'label'   => __LINE__ .': array',
                'options' => array(),
                'expect'  => array(),
                'error'   => false,
            ),
        );

        $types = array ('Options', 'SubmitOptions');
        foreach ($types as $type) {
            $typeLabel = ($type == 'SubmitOptions') ? 'Submit Options' : $type;
            $setMethod = "set$type";
            $getMethod = "get$type";
            foreach ($tests as $test) {
                $label = $test['label'];
                $client = new Client;
                try {
                    $client->$setMethod($test['options']);
                    if ($test['error']) {
                        $this->fail("$label - unexpected success");
                    }
                } catch (\InvalidArgumentException $e) {
                    if ($test['error']) {
                        $this->assertSame(
                            "$typeLabel must be an array or string",
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
                        $client->$getMethod(),
                        "$label - expected $type after set"
                    );
                }
            }
        }
    }

    /**
     * Test setId, setDescription, setHost, setLineEnd, setOwner, setRoot.
     */
    public function testSetIdDescriptionHostLineEndOwnerRoot()
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

        $types = array('Id', 'Description', 'Host', 'LineEnd', 'Owner', 'Root');
        foreach ($types as $type) {
            $typeLabel = ($type == 'LineEnd') ? 'Line End' : $type;
            $setMethod = "set$type";
            $getMethod = "get$type";

            foreach ($tests as $test) {
                $label = $test['label'] ." ($type)";
                $client = new Client;

                // setup the expected error message
                $expectedError = "$typeLabel must be a string or null.";
                // Client id validation fails on empty string now, which
                // is the only exception (so far), so we special case it here.
                if ($type == 'Id') {
                    $expectedError = 'Cannot set id. Id is invalid.';

                    if (preg_match('/empty string/', $label)) {
                        $test['error'] = true;
                    }
                }

                try {
                    $client->$setMethod($test['value']);
                    if ($test['error']) {
                        $this->fail("$label - unexpected success");
                    }
                } catch (\InvalidArgumentException $e) {
                    if ($test['error']) {
                        $this->assertSame(
                            $expectedError,
                            $e->getMessage(),
                            "$label - Expected error message."
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
                        $test['value'],
                        $client->$getMethod(),
                        "$label - expected $type after set"
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
        // 'test-client' will exist out of the gate; add a couple more to make it a real test.
        $this->createClients();

        $byOwner = Client::fetchAll(array(Client::FETCH_BY_OWNER => 'user1'));

        $this->assertSame(
            2,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test2-client',
            $byOwner->first()->getId(),
            'Expected first result client to match'
        );

        $this->assertSame(
            'user1',
            $byOwner->first()->getOwner(),
            'Expected first result user to match'
        );

        $this->assertSame(
            'test3-client',
            $byOwner->nth(1)->getId(),
            'Expected second result client to match'
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
                Client::fetchAll(array(Client::FETCH_BY_OWNER => $value));

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
        // 'test-client' will exist out of the gate; add a couple more to make it a real test.
        $this->createClients();

        $byOwner = Client::fetchAll(array(Client::FETCH_BY_NAME => 'test3-*'));

        $this->assertSame(
            2,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test3-client',
            $byOwner->first()->getId(),
            'Expected first result client to match'
        );

        $this->assertSame(
            'test3-clientb',
            $byOwner->nth(1)->getId(),
            'Expected second result client to match'
        );

        // Verify invalid names causes error
        $tests = array(
            __LINE__ .' empty string' => "",
            __LINE__ .' bool'         => false
        );

        foreach ($tests as $label => $value) {
            try {
                Client::fetchAll(array(Client::FETCH_BY_NAME => $value));

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
        // 'test-client' will exist out of the gate; add a couple more to make it a real test.
        $this->createClients();

        $byOwner = Client::fetchAll(
            array(
                Client::FETCH_BY_NAME  => 'test3-*',
                Client::FETCH_BY_OWNER => 'user1'
            )
        );

        $this->assertSame(
            1,
            count($byOwner),
            'Expected matching number of results'
        );

        $this->assertSame(
            'test3-client',
            $byOwner->first()->getId(),
            'Expected first result client to match'
        );
    }

    /**
     * Make some clients for testing fetch all.
     */
    protected function createClients()
    {
        $client = new Client;
        $client->setId('test2-client')
               ->setOwner('user1')
               ->setRoot(DATA_PATH . '/clients/test2-client')
               ->save();
        $client->setId('test3-client')
               ->setOwner('user1')
               ->setRoot(DATA_PATH . '/clients/test3-client')
               ->save();
        $client->setId('test3-clientb')
               ->setOwner('user2')
               ->setRoot(DATA_PATH . '/clients/test3-clientb')
               ->save();
    }

    /**
     * Test creation of temp objects.
     */
    public function testMakeTemp()
    {
        // ensure we only have one client at start
        $this->assertSame(
            array('test-client'),
            Client::fetchAll()->invoke('getId')
        );

        $client = Client::makeTemp(array('Root' => DATA_PATH));

        $callbackCount = 0;
        $client2       = Client::makeTemp(
            array('Root' => DATA_PATH),
            function ($client, $defaultCallback) use (&$callbackCount) {
                $callbackCount++;
                $defaultCallback($client);
            }
        );

        $this->assertSame(
            3,
            count(Client::fetchAll()),
            'expected matching number of clients after our adds'
        );

        $client->getConnection()->disconnect();

        $this->assertSame(
            array('test-client'),
            Client::fetchAll()->invoke('getId'),
            'expected matching number of clients after disconnect'
        );

        $this->assertSame(
            1,
            $callbackCount,
            'expected our callback count to have incremented'
        );
    }
}
