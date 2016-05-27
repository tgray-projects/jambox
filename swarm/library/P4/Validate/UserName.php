<?php
/**
 * Validates string for suitability as a Perforce user name.
 * Extends key-name validator to provide a place to customize
 * validation.
 *
 * @copyright   2011 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4\Validate;

class UserName extends KeyName
{
    protected $allowSlashes     = true;     // SLASH
    protected $allowRelative    = true;     // REL
}
