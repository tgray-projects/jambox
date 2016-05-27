<?php
/**
 * Application config for testing the TestControllerCase class.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

return array(
    'modules' => array(
        'Foo',
        'Bar',
    ),
    'module_listener_options' => array(
        'module_paths' => array(__DIR__ . '/module'),
    ),
);
