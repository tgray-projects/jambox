<?php
/**
 * This is a test implementation of the P4\Spec\PluralAbstract.
 * It is used to thoroughly exercise the base plural spec functionality so latter implementors
 * can focus on testing only their own additions/modifications.
 *
 * This class happens to represent the 'job' type as this is the cleanest looking plural-spec.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Spec;

use P4\Connection\ConnectionInterface;
use P4\Spec\PluralAbstract;

class PluralMock extends PluralAbstract
{
    const SPEC_TYPE = 'job';
    const ID_FIELD  = 'Job';

    /**
     * Determine if the given job id exists.
     *
     * @param   string                  $id             the id to check for.
     * @param   ConnectionInterface     $connection     optional - a specific connection to use.
     * @return  bool    true if the given id matches an existing job.
     */
    public static function exists($id, ConnectionInterface $connection = null)
    {
        // if no connection given, use default.
        $connection = $connection ?: static::getDefaultConnection();

        $result = $connection->run("jobs", array("-e", "Job=$id"));
        return (bool) count($result->getData());
    }
}
