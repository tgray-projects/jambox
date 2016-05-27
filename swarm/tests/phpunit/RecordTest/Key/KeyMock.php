<?php
/**
 * This is a test implementation of the Record\Key\AbstractKey.
 *
 * It is used to thoroughly exercise the base functionality so latter implementors
 * can focus on testing only their own additions/modifications.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace RecordTest\Key;

use P4\Connection\ConnectionInterface as Connection;
use Record\Key\AbstractKey;

class KeyMock extends AbstractKey
{
    const KEY_PREFIX            = 'swarm-test-';
    const KEY_COUNT             = 'swarm-test:count';

    public $fields       = array(
        'type'           => array(
            'index'      => 1001
        ),
        'streams'        => array(
            'index'      => 1002
        ),
        'words'          => array(
            'index'      => 1003,
            'indexWords' => true
        )
    );

    /**
     * Retrieves all records that match the passed options.
     * Extends parent to compose a search query when fetching by stream or type.
     *
     * @param   array       $options    an optional array of search conditions and/or options
     *                                  supported options are:
     *                                   FETCH_MAXIMUM - set to integer value to limit to the first
     *                                                   'max' number of entries.
     *                                     FETCH_AFTER - set to an id _after_ which we start collecting
     *                                 FETCH_BY_STREAM - set to a stream to limit results (e.g. 'user-joe')
     *                                   FETCH_BY_TYPE - set to a type to limit results (e.g. 'change')
     * @param   Connection  $p4         the perforce connection to run on
     * @return  array       the array of zero or more matching activity objects
     */
    public static function fetchAll(array $options, Connection $p4)
    {
        // normalize options
        $options += array('streams' => null, 'type' => null, 'words' => null);

        // build a search expression for type and/or stream.
        $options[static::FETCH_SEARCH] = static::makeSearchExpression(
            array(
                 'type'    => $options['type'],
                 'streams' => $options['streams'],
                 'words'   => $options['words']
            )
        );

        return parent::fetchAll($options, $p4);
    }

    /**
     * Add a stream that this model should be indexed under.
     *
     * Based on what the concrete 'Activity' model will likely do but
     * intended to just generically represent a 'multi-value' index.
     *
     * @param   string  $name   the stream name (e.g. streama, streamb)
     * @return  KeyMock         provides fluent interface
     */
    public function addStream($name)
    {
        $streams   = (array) $this->get('streams');
        $streams[] = $name;

        return $this->set('streams', array_unique($streams));
    }
}
