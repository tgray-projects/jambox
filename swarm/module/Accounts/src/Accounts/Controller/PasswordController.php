<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Accounts\Controller;

use Application\Permissions\Exception\ForbiddenException;
use Users\Model\User;
use Users\Authentication;

use P4\Connection\Exception\CommandException;
use P4\Connection\Exception\ServiceNotFoundException;
use P4\Uuid\Uuid;

use Zend\Authentication\Result;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

/**
 * When the user requests a password change they are directed to the reset action
 * which handles displaying the form and setting a token.
 * If the user is unauthenticated, they are emailed a link to click to reset their
 * password.  Clicking the link takes them to the password change form.
 * If the user is authenticated they are taken to a form to change their password.
 */
class PasswordController extends AbstractActionController
{
    /**
     * Action to handle resetting an authenticated user's password.
     * If the user is not provided, return.
     * If POST, with user and password, reset the user's password to the provided
     * value.
     * If not POST, return the form.
     *
     * @return \Zend\View\Model\JsonModel
     */
    public function changeAction()
    {
        $request  = $this->getRequest();

        if ($request->isPost()) {
            $password = $request->getPost('current');
            $token    = $request->getPost('token');
            $new      = $request->getPost('new');
            $verify   = $request->getPost('verify');
            $username = $request->getPost('username');

            if ($new !== $verify) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'message'   => 'New password does not match verification value.',
                    )
                );
            }

            $services = $this->getServiceLocator();
            $p4Admin  = $services->get('p4_admin');

            // if token, we're resetting an unauthenticated user's password
            // validate token, reset, save, redirect to user page
            if ($token) {
                // check for invalid token
                if (!$this->verifyToken($username, $token)) {
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'message' => 'Invalid username or token provided.  Please request a new reset token.'
                        )
                    );
                }

                $p4Super = $services->get('p4_super');
                try {
                    $target = User::fetch($username, $p4Super);
                    $config = $target->getConfig();
                    $config->setRawValue('resetToken', null);
                    $config->setRawValue('tokenExpiryTime', null);
                    $target->setConfig($config);
                    $target->setPassword($new);
                    $target->save();

                    // log the user in with their new password
                    $auth = $services->get('auth');
                    $services->get('session')->start();

                    $adapter = new Authentication\Adapter($username, $new, $p4Admin);

                    // if unable to authenticate for some reason, let them login manually
                    if ($auth->authenticate($adapter)->getCode() !== Result::SUCCESS) {
                        return new JsonModel(
                            array(
                                'isValid'   => true,
                                'loggedIn'  => false,
                                'message'   => 'Your password has been reset, please log in.',
                            )
                        );
                    }

                    // regenerate our id if they logged in to avoid fixation
                    // this also allows the longer expiration to take affect
                    // if 'remember' was checked.
                    session_regenerate_id(true);
                } catch (\P4\Connection\Exception\CommandException $e) {
                    $message = $e->getMessage();
                    if (strpos($message, 'Command failed: ') !== false) {
                        $message = substr($message, strlen('Command failed: '));
                    }
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'message'   => $message,
                        )
                    );
                } catch (\Exception $e) {
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'message'   => 'Invalid user.',
                        )
                    );
                }
            } else {
                // else we're doing a regular password reset for an authenticated user
                // ensure request is for current user, as the request could have
                // been modified
                try {
                    $target      = User::fetch($username, $p4Admin);
                    $currentUser = $services->get('user');

                    if ($currentUser->getId() != $target->getId()) {
                        return new JsonModel(
                            array(
                                'isValid'   => false,
                                'message'   => 'Invalid user.',
                            )
                        );
                    }
                } catch (Exception $e) {
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'message'   => 'Invalid user.',
                        )
                    );
                }
                $auth = $services->get('auth');

                // re-open the session as we keep it closed by default and the auth adapter needs to write to it
                $services->get('session')->start();

                // verify current password
                $adapter = new Authentication\Adapter($username, $password, $p4Admin);
                try {
                    if ($auth->authenticate($adapter)->getCode() !== Result::SUCCESS) {
                        return new JsonModel(
                            array(
                                'isValid'   => false,
                                'message'   => 'Unable to authenticate for password change.',
                            )
                        );
                    }

                    // save new password
                    $target->setConnection($services->get('p4_super'));
                    $target->setPassword($new)->save();
                } catch (\Exception $e) {
                    $message = 'Unable to authenticate for password change.';
                    if (stristr($e->getMessage(), 'Command failed:')) {
                        $message = substr($e->getMessage(), strlen('Command failed: '));
                    }
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'message'   => $message,
                        )
                    );
                }
            }

            // invalidate user cache
            try {
                $p4Admin->getService('cache')->invalidateItem('users');
            } catch (ServiceNotFoundException $e) {
                // no cache? nothing to invalidate
            }

            return new JsonModel(
                array(
                    'isValid'   => true,
                    'loggedIn'  => true,
                    'message'   => 'Successfully changed password.',
                )
            );
        }

        // prepare view for change password form
        // always return status message when using the token
        $partial  = ($request->getQuery('format') === 'partial');
        $token    = $this->getEvent()->getRouteMatch()->getParam('token');
        $username = $this->getEvent()->getRouteMatch()->getParam('user');

        $view    = new ViewModel(
            array(
                'partial'  => $partial,
                'username' => $username,
                'token'    => $token
            )
        );
        $view->setTerminal($partial);

        return $view;
    }

    /**
     * Action to handle resetting an unauthenticated user's password.
     *
     * If GET, and the user is not provided, display the form.
     *
     * If POST and a valid user is provided, set token to indicate they have requested
     * a password reset, generate reset url, and send them an email with the url in it.
     *
     * If GET and user is set and the token is valid and the token has not been
     * used and the token has not expired, remove the token from the account,
     * reset the user's password and display the new password to them.
     *
     * http://www.andreabaccega.com/blog/2012/04/07/lost-password-reset-software-design-pattern/
     *
     * @return \Zend\View\Model\JsonModel | \Zend\View\Model\ViewModel
     */
    public function resetAction()
    {
        $request  = $this->getRequest();
        $services = $this->getServiceLocator();
        $config   = $services->get('config');
        if (!array_key_exists('p4_super', $config)) {
            throw new ForbiddenException('Cannot process reset.  Please contact the administrator.');
        }

        $p4Admin  = $services->get('p4_admin');
        $p4Super  = $services->get('p4_super');

        // handle both form submits
        if ($request->isPost()) {
            $identity = $request->getPost('identity');

            // ensure we've got a valid user identity
            $username = '';
            if (!User::exists($identity, $p4Admin)) {
                foreach (User::fetchAll(null, $p4Admin) as $candidate) {
                    if ($candidate->getEmail() === $identity) {
                        $username = $candidate->getId();
                        break;
                    }
                }
            } else {
                $username = $identity;
            }
            // both require a username
            if ($username === '') {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'message'   => 'Invalid user.',
                        'user'      => null
                    )
                );
            }

            // ensure user exists
            try {
                $user = User::fetch($username, $p4Admin);
            } catch (\Exception $e) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'message'   => 'Invalid user.',
                    )
                );
            }

            // if no token or password, we're setting the token
            // generate cryptographically secure token, save it and note the time, send email
            $token      = (string)new Uuid;
            $userConfig = $user->getConfig();

            $userConfig->setRawValue('resetToken', $token);
            $userConfig->setRawValue('tokenExpiryTime', strtotime('+1 day'));

            $user->setConfig($userConfig);

            // email settings
            $mail = array(
                'subject'       => "Your Perforce Workshop Account",
                'toUsers'       => array($username),
                'fromAddress'   => $config['mail']['sender'],
                'messageId'     => '<reset-' . $username . '@swarm>',
                'htmlTemplate'  => __DIR__ . '/../../../view/mail/reset-html.phtml',
                'textTemplate'  => __DIR__ . '/../../../view/mail/reset-text.phtml'
            );

            try {
                // use super connection to save changes
                $user->setConnection($p4Super);
                $user->save();

                $message  = $services->get('mail_composer')->compose(
                    $mail,
                    array('token' => $token, 'user' => $username)
                );

                $mailer = $services->get('mailer');
                $mailer->send($message);

                return new JsonModel(
                    array(
                        'isValid'   => true,
                        'message'   => 'Successfully sent reset email.'
                    )
                );
            } catch (\Exception $e) {
                $services->get('logger')->err($e);
            }

            return new JsonModel(
                array(
                    'isValid'   => false,
                    'message'   => 'Unable to process reset request.  Please contact an administrator.'
                )
            );
        }

        $token    = $this->getEvent()->getRouteMatch()->getParam('token');
        $username = $this->getEvent()->getRouteMatch()->getParam('user');

        // no username and no token, show form
        if (!$username && !$token) {
            return new ViewModel(
                array(
                    'partial' => $request->getQuery('format') === 'partial'
                )
            );
        }

        // missing some details, display error and form
        if (!$username || !$token) {
            return new ViewModel(
                array(
                    'partial' => $request->getQuery('format') === 'partial',
                    'message' => 'Invalid username or token provided.  Please request a new reset token.'
                )
            );
        }

        if (!$this->verifyToken($username, $token)) {
            return new ViewModel(
                array(
                    'partial' => $request->getQuery('format') === 'partial',
                    'message' => 'Invalid username or token provided.  Please request a new reset token.'
                )
            );
        }

        // token is valid, pass to change password to let the user set the new password
        // show form
        return $this->forward()->dispatch(
            'Accounts\Controller\Password',
            array(
                'action' => 'change',
                'user'   => $username,
                'token'  => $token,
                'format' => 'partial'
            )
        );
    }

    /**
     * Verifies that a token is valid for a given user.
     * Returns false if the user does not exist, if the token is invalid, or
     * the token is expired.
     *
     * @param string    $username   The user to which the token applies.
     * @param string    $token  The token to verify.
     * @return boolean  Whether or not the token is valid for this user.
     */
    protected function verifyToken($username, $token)
    {
        $services = $this->getServiceLocator();
        $p4Admin  = $services->get('p4_admin');

        // ensure user exists
        try {
            $user   = User::fetch($username, $p4Admin);
            $config = $user->getConfig();

            $userToken     = $config->getRawValue('resetToken');
            $userTokenTime = $config->getRawValue('tokenExpiryTime');
        } catch (\Exception $e) {
            return false;
        }

        $time = time();
        return ($userToken == $token && $userTokenTime >= $time);
    }
}
