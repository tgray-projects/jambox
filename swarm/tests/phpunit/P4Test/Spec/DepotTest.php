<?php
/**
 * Test methods for the P4 Depot class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4Test\TestCase;
use P4\Spec\Depot;
use P4\Spec\Client;
use P4\Spec\Exception\NotFoundException;
use P4\Spec\Exception\Exception as SpecException;

class DepotTest extends TestCase
{
    /**
     * Test initial conditions.
     */
    public function testInitialConditions()
    {
        // assume there is one local depot
        $depots = Depot::fetchAll();
        $this->assertSame(1, count($depots), 'Expected depots at start.');

        $depot = $depots->first();

        $this->assertSame('depot', $depot->getId(), "Expected depot 'depot' at start.");
        $this->assertSame('local', $depot->getType(), "Expected local depot at start.");
        $this->assertSame('depot/...', $depot->getMap(), "Expected mapping of depot at start.");
    }

    /**
     * Test fetch() method.
     */
    public function testFetch()
    {
        // create new depot
        $depot = new Depot;
        $depot
            ->setId('foo-depot')
            ->set(
                array(
                    'Type'  => 'local',
                    'Map'   => 'foo/...'
                )
            )
            ->save();

        $depot = Depot::fetch('foo-depot');
        $this->assertTrue(
            $depot instanceof Depot,
            "Expected fetch returns instance of P4\Depot."
        );
        $this->assertSame(
            'local',
            $depot->getType(),
            "Expected type of fetched depot."
        );
        $this->assertSame(
            'foo/...',
            $depot->getmap(),
            "Expected type of fetched depot."
        );

        // verify fetching a non-existant depot throws an exception
        $depot->delete();
        try {
            Depot::fetch('foo-depot');
        } catch (NotFoundException $e) {
            // expected exception
            $this->assertTrue(true);
        }
    }

    /**
     * Test exist() method.
     */
    public function testExist()
    {
        // verify required fields (depot, type, map) must be set before save
        $depot = new Depot;
        try {
            $depot->save();
            $this->fail("Unexpected possibility of saving empty depot.");
        } catch (SpecException $e) {
            // expected exception
            $this->assertTrue(true);
        }

        $depot->set(
            array(
                'Depot' => 'test',
                'Type'  => 'local',
                'Map'   => 'test/...'
            )
        );

        $depot->save();
        $this->assertTrue(Depot::exists('test'), "Expected existence of 'test' depot.");

        // query non-existant depot
        $this->assertFalse(Depot::exists('non-exist'), "Expected exist() returns false for non-existant depot.");
    }

    /**
     * Test accessors/mutators.
     */
    public function testAccessorsMutators()
    {
        $depot = new Depot;
        $tests = array(
            'Depot'         => 'tdepot',
            'Owner'         => 'town',
            'Description'   => 'tdesc',
            'Type'          => 'local',
            'Address'       => 'taddr',
            'Suffix'        => '.tsuf',
            'Map'           => 'tmap/...'
        );

        foreach ($tests as $key => $value) {
            $depot->set($key, $value);
            $this->assertSame($value, $depot->get($key), "Expected value for $key");
        }

        // verify again on fetched depot
        $expected = array(
            'Depot'         => 'tdepot',
            'Owner'         => 'town',
            'Description'   => "tdesc\n",
            'Type'          => 'local',
            'Map'           => 'tmap/...'
        );

        $depot->save();
        $depot = Depot::fetch('tdepot');

        foreach ($expected as $key => $value) {
            $this->assertSame($value, $depot->get($key), "Expected value for $key after fetch");
        }
    }

    /**
     * Verify that its possible to save a client with mapping the new depot into the view.
     */
    public function testCreateClient()
    {
        // create new deopt
        $depot = new Depot;
        $depot->set(
            array(
                'Depot'         => 'tdep',
                'Type'          => 'local',
                'Map'           => 'tdep/...'
            )
        );
        $depot->save();
        $this->assertTrue(Depot::exists('tdep'));

        // at this point we have to disconnect as Perforce doesn't let
        // creating new client with mapping a depot created by the same
        // connection
        // @todo remove when bug is fixed
        $this->p4->disconnect();

        // create client mapping the new depot
        $client = new Client;
        $client->set(
            array(
                'Client'        => 'foo',
                'Root'          => '/tmp/tcli',
                'View'          => array(
                    array(
                        'depot'     => '//tdep/...',
                        'client'    => '//foo/a/...'
                    )
                )
            )
        );
        $client->save();
        $this->assertTrue(Client::exists('foo'));
        $this->assertSame(
            array(
                0 => array(
                    'depot'     => '//tdep/...',
                    'client'    => '//foo/a/...'
                )
            ),
            Client::fetch('foo')->getView(),
            "Expected view of fetched client matches saved values."
        );
    }
}
