<?php
/**
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE in top-level folder of this distribution.
 */
 
namespace Teampage;
 
/**
 * This modules shows an example of how to add your own retro-style high score page.
 * Scores are number of changelists submitted by the user.
 *
 */
 
class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
 
    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
        );
    }
}
