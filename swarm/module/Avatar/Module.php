<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Avatar;

use Zend\Mvc\MvcEvent;

class Module
{
    /**
     * Modify the project filter to:
     *  - allow users to upload an optional workshop avatar and splash image
     *
     * @param   MvcEvent    $event  the bootstrap event
     * @return  void
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application    = $event->getApplication();
        $services       = $application->getServiceManager();

        $filters        = $services->get('InputFilterManager');
        $projectFilter  = $filters->get('ProjectFilter');

        $projectFilter->add(
            array(
                'name'       => 'avatar',
                'required'   => false,
                'validators' => array(
                    array(
                        'name' => 'Digits'
                    )
                )
            )
        );

        $projectFilter->add(
            array(
                'name'      => 'splash',
                'required'  => false,
                'validators' => array(
                    array(
                        'name' => 'Digits'
                    )
                )
            )
        );

        $filters->setService('ProjectFilter', $projectFilter);
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
