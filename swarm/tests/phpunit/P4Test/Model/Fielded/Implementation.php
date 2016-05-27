<?php
/**
 * An implementation of the model abstract class for testing.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test\Model\Fielded;

use P4\Model\Fielded\FieldedAbstract;

class Implementation extends FieldedAbstract
{
    protected $fields = array('foo' => null, 'bar' => null, 'baz' => null);

    /**
     * Get model fields.
     *
     * @return  array   list of field names.
     */
    public function getFields()
    {
        return array_keys($this->fields);
    }

    /**
     * Get model field value.
     *
     * @param   string  $field  name of field to get value of.
     * @return  mixed   value of field.
     */
    public function get($field)
    {
        return isset($this->fields[$field]) ? $this->fields[$field] : null;
    }

    /**
     * Check if model has field.
     *
     * @param   string  $field  name of field to check for.
     * @return  bool    true if model has field; false otherwise.
     */
    public function hasField($field)
    {
        return array_key_exists($field, $this->fields);
    }

    /**
     * Set model values.
     *
     * @param   array   $values     values to set on model.
     */
    public function set($values)
    {
        $this->fields = $values;
    }
}
