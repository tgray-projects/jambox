<?php
/**
 * Perforce Swarm
 *
 * Builds a mail message from the supplied parameters, handling template
 * rendering and use of the system mail configuration.
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Compose\Message;

use \Zend\ServiceManager\ServiceLocatorInterface as ServiceLocator;
use Users\Model\User;
use Zend\Mail\Message;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;
use Zend\Stdlib\StringUtils;
use Zend\Validator\EmailAddress;
use Zend\View\Model\ViewModel;
use Zend\View\Resolver\TemplatePathStack;

class Compose
{
    protected $services = null;

    /**
     * Constructor to ensure the service locator is available for use.
     *
     * @param \Zend\ServiceManager\ServiceLocatorInterface $services
     */
    public function __construct(ServiceLocator $services)
    {
        $this->services = $services;
    }

    /**
     * This function uses the provided list of mail options to build a mail message,
     * passing the provided template data to the specified template.
     *
     * @param array $mailData       An array of mail options, used to build the email.
     * @param array $templateData   An array of data, passed to the ViewModel to build the mail template.
     * @throws Exception            Throws an exception if required mail data is not set or is invalid.
     */
    public function compose(array $mailData, $templateData = array())
    {
        if (!$this->services) {
            throw new \Exception('Services locator must be set before calling mail method.');
        }

        // normalize and validate message configuration
        $mailData += array(
            'to'           => null,
            'toUsers'      => null,
            'subject'      => null,
            'cropSubject'  => false,
            'fromAddress'  => null,
            'fromName'     => null,
            'fromUser'     => null,
            'messageId'    => null,
            'inReplyTo'    => null,
            'htmlTemplate' => null,
            'textTemplate' => null,
        );
        if (!is_readable($mailData['htmlTemplate'])) {
            $mailData['htmlTemplate'] = null;
        }
        if (!is_readable($mailData['textTemplate'])) {
            $mailData['textTemplate'] = null;
        }

        // early exit if no valid templates specified
        if (!$mailData['htmlTemplate'] && !$mailData['textTemplate']) {
            throw new \Exception(
                'No readable mail templates, cannot send email to ' . $mailData['to'] . ' from ' .
                $mailData['fromUser'] .' with subject ' . $mailData['subject'] . '.'
            );
        }

        // normalize mail configuration, start by ensuring all of the keys are at least present
        $configs = $this->services->get('config') + array('mail' => array());
        $config  = $configs['mail'] +
            array(
                'sender' => null,
                'recipients' => null,
                'subject_prefix' => null,
                'use_bcc' => null
            );

        // if sender has no value use the default
        $config['sender'] = $config['sender'] ?: 'notifications@' . $configs['environment']['hostname'];

        // if subject prefix was specified or is an empty string, use it.
        // for unspecified or null subject prefixes we use the default.
        $config['subject_prefix'] = $config['subject_prefix'] || $config['subject_prefix'] === ''
            ? $config['subject_prefix'] : '[Swarm]';

        // as a convenience, listeners may specify to/from as usernames
        // and we will resolve these into the appropriate email addresses.
        $to    = (array) $mailData['to'];
        $users = array_unique(array_merge((array) $mailData['toUsers'], (array) $mailData['fromUser']));
        if (count($users)) {
            $p4Admin = $this->services->get('p4_admin');
            $users   = User::fetchAll(array(User::FETCH_BY_NAME => $users), $p4Admin);
        }
        if (is_array($mailData['toUsers'])) {
            foreach ($mailData['toUsers'] as $toUser) {
                if (isset($users[$toUser])) {
                    $to[] = $users[$toUser]->getEmail();
                }
            }
        }
        if (isset($users[$mailData['fromUser']])) {
            $fromUser            = $users[$mailData['fromUser']];
            $mailData['fromAddress'] = $fromUser->getEmail()    ?: $mailData['fromAddress'];
            $mailData['fromName']    = $fromUser->getFullName() ?: $mailData['fromName'];
        }

        // remove any duplicate or empty recipient addresses
        $to = array_unique(array_filter($to, 'strlen'));

        // filter out invalid addresses from the list of recipients
        $validator = new EmailAddress();
        // disable tld validation to accomodate new tlds; if it's invalid, they won't get the email
        $validator->getHostnameValidator()->useTldCheck(false);

        $to = array_filter($to, array($validator, 'isValid'));

        // if we don't have any recipients, nothing more to do
        if (!$to && !$config['recipients']) {
            $this->services->get('logger')->debug('Mail recipients: ' . implode(', ', $to));
            throw new \Exception('No valid recipients, cannot send email.');
        }

        // if explicit recipients have been configured (e.g. for testing),
        // log the computed list of recipients for debug purposes.
        if ($config['recipients']) {
            $this->services->get('logger')->debug('Mail recipients: ' . implode(', ', $to));
        }

        // prepare view for rendering message template
        // customize view resolver to only look for the specific
        // templates we've been given (note we cloned view, so it's ok)
        $renderer  = clone $this->services->get('ViewManager')->getRenderer();
        $resolver  = new TemplatePathStack;
        $resolver->addPaths(array(dirname($mailData['htmlTemplate']), dirname($mailData['textTemplate'])));
        $renderer->setResolver($resolver);
        $viewModel = new ViewModel($templateData);

        // message has up to two parts (html and plain-text)
        $parts = array();
        if ($mailData['textTemplate']) {
            $viewModel->setTemplate(basename($mailData['textTemplate']));
            $text       = new MimePart($renderer->render($viewModel));
            $text->type = 'text/plain';
            $parts[]    = $text;
        }
        if ($mailData['htmlTemplate']) {
            $viewModel->setTemplate(basename($mailData['htmlTemplate']));
            $html       = new MimePart($renderer->render($viewModel));
            $html->type = 'text/html';
            $parts[]    = $html;
        }

        // prepare subject by applying prefix, collapsing whitespace and optionally cropping.
        $subject = $config['subject_prefix'] . ' ' . $mailData['subject'];
        if ($mailData['cropSubject']) {
            $utility  = StringUtils::getWrapper();
            $length   = strlen($subject);
            $subject  = $utility->substr($subject, 0, (int) $mailData['cropSubject']);
            $subject .= strlen($subject) < $length ? '...' : '';
        }
        $subject = preg_replace('/\s+/', " ", $subject);

        // build the mail message
        $body       = new MimeMessage();
        $body->setParts($parts);
        $message    = new Message();
        $recipients = $config['recipients'] ?: $to;
        if ($config['use_bcc']) {
            $message->setTo($config['sender'], 'Unspecified Recipients');
            $message->addBcc($recipients);
        } else {
            $message->addTo($recipients);
        }
        $message->setSubject($subject);
        $message->setFrom($config['sender'], $mailData['fromName']);
        $message->addReplyTo($mailData['fromAddress'] ?: $config['sender'], $mailData['fromName']);
        $message->setBody($body);
        $message->setEncoding('UTF-8');
        $message->getHeaders()->addHeaders(
            array_filter(
                array(
                    'Message-ID'      => $mailData['messageId'],
                    'In-Reply-To'     => $mailData['inReplyTo'],
                    'References'      => $mailData['inReplyTo'],
                    'X-Swarm-Host'    => $configs['environment']['hostname'],
                    'X-Swarm-Version' => VERSION,
                )
            )
        );

        // set alternative multi-part if we have both html and text templates
        // so that the client knows to show one or the other, not both
        if ($mailData['htmlTemplate'] && $mailData['textTemplate']) {
            $message->getHeaders()->get('content-type')->setType('multipart/alternative');
        }

        return $message;
    }
}
