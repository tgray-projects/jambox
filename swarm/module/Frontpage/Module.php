<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage;

use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\ModuleManager;
use Zend\ModuleManager\ModuleEvent;

class Module
{
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getParam('application');
        $application->getEventManager()->attach('dispatch', array($this, 'setTemplate'), -100);
    }

    public function setTemplate($event)
    {
        $matches = $event->getRouteMatch();
        $route   = $matches->getMatchedRouteName();

        // only override user index controller "home" route
        if (0 !== strpos($route, 'home', 0)) {
            return;
        }

        $viewModel = $event->getViewModel();
        $children  = $viewModel->getChildren();
        $viewModel->clearChildren();
        foreach ($children as $child) {
            if ($child->getTemplate() == 'users/index/index') {
                $child->setTemplate('frontpage/index/index');
            }
            $viewModel->addChild($child);
        }
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
