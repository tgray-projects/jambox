<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Users;

use Users\Authentication\BasicAuthListener;
use Zend\Mvc\MvcEvent;

class Module
{
    /**
     * Connect to queue event manager to clear cache on user updates
     *
     * @param   MvcEvent    $event  the bootstrap event
     * @return  void
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $events      = $services->get('queue')->getEventManager();

        // invalidate user cache on user form-commits and deletes
        $events->attach(
            array('task.user', 'task.userdel'),
            function ($event) use ($services) {
                $p4Admin = $services->get('p4_admin');

                // ignore git-fusion-reviews-* users - these are regularly updated
                // and used internally by git-fusion in ways that don't concern us
                if (strpos($event->getParam('id'), 'git-fusion-reviews-') === 0) {
                    return;
                }

                try {
                    $cache = $p4Admin->getService('cache');
                    $cache->invalidateItem('users');
                } catch (\Exception $e) {
                    $services->get('logger')->err($e);
                }
            },
            100
        );

        // invalidate group cache on group form-commits and deletes
        $events->attach(
            array('task.group', 'task.groupdel'),
            function ($event) use ($services) {
                $p4Admin = $services->get('p4_admin');

                try {
                    $cache = $p4Admin->getService('cache');
                    $cache->invalidateItem('groups');
                } catch (\Exception $e) {
                    $services->get('logger')->err($e);
                }
            },
            100
        );

        // validate credentials when using basic-auth
        $basicAuthListener = new BasicAuthListener;
        $basicAuthListener->attach($application->getEventManager());
    }

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
