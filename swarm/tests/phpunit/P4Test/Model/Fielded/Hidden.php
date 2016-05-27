<?php
/**
 * An implementation containing a hidden field
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Model\Fielded;

use P4\Model\Fielded\FieldedAbstract;

class Hidden extends FieldedAbstract
{
    protected $fields = array('foo', 'bar', 'baz', 'hidden' => array('hidden' => true));
}
