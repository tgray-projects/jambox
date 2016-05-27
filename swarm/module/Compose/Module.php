<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Compose;

use Activity\Model\Activity;
use Zend\Mvc\MvcEvent;
use P4\Spec\Change;
use P4\Spec\Exception\NotFoundException;

class Module
{
    /**
     * Connect to queue events to send email notifications
     *
     * @param   Event   $event  the bootstrap event
     * @return  void
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $manager     = $services->get('queue');
        $events      = $manager->getEventManager();

        // send email notifications for task events that prepare mail data.
        // we use a very low priority so that others can influence the message.
        $events->attach(
            '*',
            function ($event) use ($application, $services) {
                $mail     = $event->getParam('mail');
                $activity = $event->getParam('activity');
                if (!is_array($mail) || !$activity instanceof Activity) {
                    return;
                }

                // ignore 'quiet' events.
                $data  = (array) $event->getParam('data') + array('quiet' => null);
                $quiet = $event->getParam('quiet', $data['quiet']);
                if ($quiet === true || in_array('mail', (array) $quiet)) {
                    return;
                }

                // if we are configured not to email events involving restricted changes
                // and this event has a change attached, dig into the associated change.
                // if the associated change ends up being restricted, bail.
                if ((!isset($configs['security']['email_restricted_changes'])
                        || !$configs['security']['email_restricted_changes'])
                    && $activity->get('change')
                ) {

                    // try and re-use the event's change if it has a matching id otherwise do a fetch
                    $changeId = $activity->get('change');
                    $change   = $event ->getParam('change');

                    if (!$change instanceof Change || $change->getId() != $changeId) {
                        try {
                            $change = Change::fetch($changeId, $services->get('p4_admin'));
                        } catch (NotFoundException $e) {
                            // if we cannot fetch the change, we have to assume
                            // it's restricted and bail out of sending email
                            return;
                        }
                    }

                    // if the change is restricted, don't email just bail
                    if ($change->getType() == Change::RESTRICTED_CHANGE) {
                        return;
                    }
                }

                try {
                    $message  = $services->get('mail_composer')->compose(
                        $mail,
                        array(
                            'services'   => $services,
                            'activity'  => $activity,
                            'event'     => $event
                        )
                    );

                    $mailer = $services->get('mailer');
                    $mailer->send($message);
                } catch (\Exception $e) {
                    $services->get('logger')->err($e);
                }
            },
            -100
        );
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
