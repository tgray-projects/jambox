<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Notify\Controller;


use Zend\Mail\Message;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;

class IndexController extends AbstractActionController
{
    /**
     * Show the form or send the message.
     *
     * @return \Zend\View\Model\JsonModel|\Zend\View\Model\ViewModel
     */
    public function indexAction()
    {
        $request  = $this->getRequest();
        $services = $this->getServiceLocator();
        $config   = $services->get('config');


        if ($request->isPost()) {
            $comment = $request->getPost('comment');

            if (empty($comment)) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'Username, email, and password are all required fields.',
                    )
                );
            }

            $subjectIndex = $request->getPost('subject');
            $currentUser  = $services->get('user');

            $mail = array(
                'subject'       => $config['notify']['subjects'][$subjectIndex],
                'to'            => $config['notify']['to'],
                'fromAddress'   => $currentUser->getEmail(),
                'messageId'     => '<comment-' . $currentUser->getId() . '@swarm>',
                'htmlTemplate'  => __DIR__ . '/../../../view/mail/notify-html.phtml',
                'textTemplate'  => __DIR__ . '/../../../view/mail/notify-text.phtml'
            );
            $message  = $services->get('mail_composer')->compose(
                $mail,
                array(
                    'user'    => $currentUser->getId(),
                    'name'    => $currentUser->getFullName(),
                    'comment' => $comment
                )
            );

            $mailer = $services->get('mailer');
            $mailer->send($message);

            return new JsonModel(
                array(
                    'isValid'   => true,
                    'error'     => 'Account created - check your email for details.'
                )
            );
        }

        $partial = $request->getQuery('format') === 'partial';
        $view    = new ViewModel(
            array(
                'partial'   => $partial,
                'subjects'  => $config['notify']['subjects'],
                'to'        => $config['notify']['to'],
            )
        );
        $view->setTerminal($partial);

        return $view;
    }
}
