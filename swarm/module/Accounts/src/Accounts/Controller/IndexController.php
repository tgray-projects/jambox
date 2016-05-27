<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Accounts\Controller;

use P4\Exception;
use P4\Spec\Protections;
use P4\Uuid\Uuid;

use Users\Model\Group;
use Users\Model\User;
use Users\Authentication;

use Zend\Http\Client\Adapter\Curl;
use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Json\Json;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Validator\EmailAddress;
use Zend\View\Model\JsonModel;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    /**
     * see if the user exists already, if so, login instead
     * see if we can create users, if not, return with error
     * validate user's requested username
     * save the user in config somewhere (?), send email to validate account
     * when user clicks link, try to create the user, if so, pass to login
     * fail condition
     *
     * @return \Zend\View\Model\JsonModel|\Zend\View\Model\ViewModel
     */
    public function signupAction()
    {

        $request       = $this->getRequest();
        $services      = $this->getServiceLocator();
        $config        = $services->get('Configuration');

        if ($request->isPost()) {
            $username = $request->getPost('user');
            $email    = $request->getPost('email');
            $password = $request->getPost('password');
            $remember = $request->getPost('signupRemember');

            if (empty($username) || empty($email) || empty($password)) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'Username, email, and password are all required fields.',
                    )
                );
            }

            // validate username - no / chars; set up filter to alphanumeric
            $pattern = "/^\w+$/";
            if (!(bool) preg_match($pattern, $username)) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'Username must contain only alphanumeric and underscore characters.',
                    )
                );
            }

            // get the auth service which will init our session.
            $p4Admin = $services->get('p4_admin');

            // check to see if the user already exists
            // normalize the passed user information into an array of zero
            // or more 'candidate' accounts.
            $candidates = array();
            foreach (User::fetchAll(null, $p4Admin) as $candidate) {
                if ($candidate->getEmail() === $email) {
                    $candidates[] = $candidate->getId();
                } elseif ($candidate->getId() == $username) {
                    $candidates[] = $username;
                }
            }

            // User exists already, provide error
            if (count($candidates) > 0) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'User with that username or email address already exists.',
                    )
                );
            }

            $validator = new EmailAddress();
            // disable tld validation to accomodate new tlds; if it's invalid, they won't get the email
            $validator->getHostnameValidator()->useTldCheck(false);
            // default message is too long for dialog, rephrase
            $validator->setMessage('Email should be of the form local-part@hostname', 'emailAddressInvalidFormat');

            if (!$validator->isValid($email)) {
                $errors = array();
                foreach ($validator->getMessages() as $id => $message) {
                    $errors[] = $message;
                }

                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => htmlentities(implode("\n", $errors)),
                    )
                );
            }

            $p4Super = $services->get('p4_super');

            // create the user now
            try {
                $user = new User;
                $user->setEmail($email);
                $user->setFullName($username);
                $user->setId($username);
                $user->setPassword($password);
                $user->setConnection($p4Super);
                $user->save();
            } catch (Exception $e) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'Could not reserve user account.  Please contact an administrator.',
                    )
                );
            }

            // clear the users cache
            $p4Admin->getService('cache')->invalidateItem('users');

            // if skipping email validation, try to enable the user, if failure, display error and stop
            if ($config['accounts']['skip_email_validation']) {
                if ($this->enableUser($username)) {
                    return $this->loginRedirect($username);
                } else {
                    return new ViewModel(
                        array(
                            'error'   => 'Unable to process signup at this time, please contact the administrator.'
                        )
                    );
                }
            }

            // email validation, continue
            // user does not exist, create temporary file and send email
            // don't include password
            $data = json_encode(
                array(
                    'username' => $username,
                    'password' => '',
                    'expiry'   => strtotime('+3 days'),
                    'email'    => $email,
                    'remember' => $remember
                )
            );

            $file = (string)new Uuid;
            $path = DATA_PATH . '/signup/';

            // make the path if it doesn't exist
            if (!is_dir($path)) {
                if ($result = mkdir($path, 0755, true) === false) {
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'error'     => 'Could not make path.',
                        )
                    );
                }
            }

            if ($result = file_put_contents($path . $file, $data) === false) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'Could not write file.',
                    )
                );
            }

            // email settings
            $mail = array(
                'subject'       => "Your Perforce Workshop Account",
                'to'            => $email,
                'fromAddress'   => $config['mail']['sender'],
                'messageId'     => '<signup-' . $username . '@swarm>',
                'htmlTemplate'  => __DIR__ . '/../../../view/mail/signup-html.phtml',
                'textTemplate'  => __DIR__ . '/../../../view/mail/signup-text.phtml'
            );

            $message  = $services->get('mail_composer')->compose(
                $mail,
                array('username' => $username, 'token' => $file)
            );

            $mailer = $services->get('mailer');
            $mailer->send($message);

            return new JsonModel(
                array(
                    'isValid'   => true,
                    'message'   => 'Account created - check your email for details.'
                )
            );
        }

        // prepare view for signup form
        $user    = isset($_COOKIE['remember']) ? $_COOKIE['remember'] : '';
        $partial = $request->getQuery('format') === 'partial';
        $view    = new ViewModel(
            array(
                 'partial'    => $partial,
                 'user'       => $user,
                 'remember'   => strlen($user) != 0,
                 'statusCode' => $this->getResponse()->getStatusCode()
            )
        );
        $view->setTerminal($partial);

        return $view;
    }

    /**
     * Handles account verification and creation.  Processes the url the user
     * was mailed in the signupAction and creates the account, then clears
     * the temporary file from the data directory.
     *
     * @return \Zend\View\Model\ViewModel|\Zend\View\Model\JsonModel
     */
    public function verifyAction()
    {
        $services = $this->getServiceLocator();
        $config   = $services->get('Configuration');

        // if email validation is disabled, 404 out - this page does not exist.
        if ($config['accounts']['skip_email_validation']) {
            $this->getResponse()->setStatusCode(404);
            return false;
        }

        $token      = $this->getEvent()->getRouteMatch()->getParam('token');
        $verifyUuid = new Uuid;

        try {
            $verifyUuid->set($token);
        } catch (\InvalidArgumentException $e) {
            $services->get('logger')->warn('Could not verify token: ' . $e->getMessage() . '.');
            return new ViewModel(
                array(
                    'error'   => 'Invalid token submitted.  If you feel this message is in error, '
                                . 'please re-register to receive a new token or contact your administrator.'
                )
            );
        }

        $filename = DATA_PATH . '/signup/' . $token;
        $filename = realpath($filename);
        if (strpos($filename, realpath(DATA_PATH  . '/signup/')) !== 0) {
            $services->get('logger')->warn(
                'Could not verify token: could not find file ' . $filename. ' in data path.'
            );
            return new ViewModel(
                array(
                    'error'   => 'Invalid token submitted.  If you feel this message is in error, '
                               . 'please re-register to receive a new token or contact your administrator.'
                )
            );
        }

        $account  = @file_get_contents($filename);

        if ($account === false) {
            return new ViewModel(
                array(
                    'error'   => 'Unable to process signup at this time, please contact your administrator.'
                )
            );
        }

        // username, password, expiry, email, remember
        $account  = json_decode($account, true);
        $account += array('username' => null, 'password' => null, 'email' => null, 'remember' => null);
        $username = $account['username'];
        $email    = $account['email'];
        $remember = $account['remember'];

        $time = time();
        if ($account['expiry'] < $time) {
            return new ViewModel(
                array(
                    'error'   => 'Your signup url has expired.  '
                                . 'Please re-register for an account to generate a new one.'
                )
            );
        }

        if ($this->enableUser($username)) {
            // email settings
            $mail = array(
                'subject'       => "Your Perforce Workshop Account",
                'to'            => $email,
                'fromAddress'   => $config['mail']['sender'],
                'messageId'     => '<signup-' . $username . '@swarm>',
                'htmlTemplate'  => __DIR__ . '/../../../view/mail/confirm-html.phtml',
                'textTemplate'  => __DIR__ . '/../../../view/mail/confirm-text.phtml'
            );

            $message  = $services->get('mail_composer')->compose(
                $mail,
                array('username' => $username)
            );

            $mailer = $services->get('mailer');
            $mailer->send($message);

            // clear the temp file
            if ($result = @unlink($filename) === false) {
                $services->get('logger')->warn('Could not remove temp account signup file ' . $filename . '.');
            }

            return $this->loginRedirect($username, $remember);
        } else {
            return new ViewModel(
                array(
                    'error'   => 'Unable to process signup at this time, please contact the administrator.'
                )
            );
        }
    }

    /**
     * Helper function to set the authentication cookie and display the login form.  Used in
     * signupAction (if email validation is disabled) and in verifyAction.
     *
     * @param string    $username   The user to set the cookie for and to attempt to login.
     * @param bool      $remember   Whether or not to set the cookie.
     * @return ViewModel
     */
    protected function loginRedirect($username, $remember = true)
    {
        // log the user in and send them toRoute('home')
        // handle remember cookie
        if ($remember) {
            headers_sent() ?: setcookie('remember', $username, time() + 365*24*60*60, '/', '', false, true);

            // fake out the cookie in this request as it
            // influences the session cookie's duration
            $_COOKIE['remember'] = $username;
        }

        // as the caller of this method is either a signup form submit (no email auth)
        // or the user hitting the url directly, if partial, return json
        if ($this->getRequest()->getQuery('format') == 'partial') {
             return $this->forward()->dispatch(
                 'Users\Controller\Index',
                 array(
                    'action' => 'login'
                 )
             );
        }

        // display the login page, so they can sign in
        $view = new ViewModel(
            array(
                'user'       => $username,
                'remember'   => true,
                'statusCode' => $this->getResponse()->getStatusCode(),
                'message' => array(
                    'type' => 'success',
                    'body' => 'Account created successfully.  Please sign in below.'
                )
            )
        );
        $view->setTemplate('accounts/index/login.phtml');
        return $view;
    }

    /**
     * Enables the user by adding them to the 'registered' group.  Creates the group if it does not exist.
     *
     * @param string    $username   The id of the user to enable.
     * @return bool                 Whether or not the process succeeded.
     */
    protected function enableUser($username)
    {
        $services = $this->getServiceLocator();

        $p4Admin = $services->get('p4_admin');
        $p4Super = $services->get('p4_super');

        // set up protections, limit the user to write access to their own path
        $protections = Protections::fetch($p4Super);
        $protections->addProtection(
            'write',
            'user',
            $username,
            '*',
            '//guest/' . $username . '/...'
        );
        $protections->save();

        // make registered group, if it does not exist, and clear cache
        if (!Group::exists('registered', $p4Admin)) {
            Group::fromArray(
                array('Owners' => array($p4Admin->getUser()), Group::ID_FIELD => 'registered'),
                $p4Super
            )->save();
            $p4Admin->getService('cache')->invalidateItem('groups');
        }

        // add to registered group
        try {
            // verify that user can be fetched as expected
            // a problem with the fetch operation triggers exception and error
            User::fetch($username, $p4Admin);
            Group::fetch('registered', $p4Super)->addUser($username)->save();

            // clear the users and groups cache
            $p4Admin->getService('cache')->invalidateItem('users');
            $p4Admin->getService('cache')->invalidateItem('groups');
        } catch (Exception $e) {
            // exception, show error
            $services->get('logger')->err($e);
            return false;
        }

        return true;
    }

    protected function deleteAction()
    {
        $request        = $this->getRequest();
        $services       = $this->getServiceLocator();
        $p4Admin        = $services->get('p4_admin');
        $p4Super        = $services->get('p4_super');
        $username       = $request->getPost('username');
        $currentUser    = $services->get('user');

        $services->get('permissions')->enforce('authenticated');
        if (!$services->get('permissions')->is('admin')) {
            $this->getResponse()->setStatusCode(403);
            return new JsonModel(
                array(
                     'isValid' => false,
                     'message' => 'Only admins can delete users.',
                )
            );
        }

        if ($request->isPost()) {
            // check if trying to delete current user
            if ($currentUser->getId() == $username) {
                return new JsonModel(
                    array(
                        'isValid' => false,
                        'message' => 'Deleting admin user is not allowed.',
                    )
                );
            }

            // check if trying to delete swarm admin user
            if ($p4Admin->getUser() == $username) {
                return new JsonModel(
                    array(
                        'isValid' => false,
                        'message' => 'Deleting Swarm admin user is not allowed.',
                    )
                );
            }
            try {
                // check if user exists
                if (!User::exists($username, $p4Super)) {
                    return new JsonModel(
                        array(
                            'isValid'   => false,
                            'message'     => 'User does not exist.',
                        )
                    );
                }
                // remove user from all groups
                $groups = Group::fetchAll(array(GROUP::FETCH_BY_USER => $username), $p4Super);
                foreach ($groups as $group) {
                    try {
                        $users = $group->getUsers();
                        $userIndex = array_search($username, $users);
                        if ($userIndex != false) {
                            unset($users[$userIndex]);
                            $group->setUsers($users)->save();
                        }
                    } catch (Exception $e) {
                        return new JsonModel(
                            array(
                                'isValid' => false,
                                'message' => $e->getMessage(),
                            )
                        );
                    }
                }

                // delete the user
                try {
                    $userObj = new User();
                    $userObj->setId($username);
                    $userObj->setConnection($p4Super);
                    $result = $userObj->delete();

                    // clear user and group cache
                    $p4Admin->getService('cache')->invalidateItem('users');
                    $p4Admin->getService('cache')->invalidateItem('groups');
                    if (!($result->getData(0) == 'User ' . $username . ' deleted.')) {
                        return new JsonModel(
                            array(
                                'isValid' => false,
                                'message' => 'Could not delete user account.  ' . $result->getData(0),
                            )
                        );
                    }
                    return new JsonModel(
                        array(
                            'isValid' => true,
                            'message' => 'Account deleted',
                        )
                    );
                } catch (Exception $e) {
                    return new JsonModel(
                        array(
                            'isValid' => false,
                            'message' => $e->getMessage(),
                        )
                    );
                }
            } catch (Exception $e) {
                return new JsonModel(
                    array(
                        'isValid'   => false,
                        'error'     => 'Could not delete user account.  Please contact an administrator.',
                    )
                );
            }
        }

        $partial = ($request->getQuery('format') === 'partial');
        $username = $this->getEvent()->getRouteMatch()->getParam('user');

        $view = new ViewModel(
            array(
                'partial' => $partial,
                'username' => $username,
            )
        );

        $view->setTerminal($partial);
        return $view;
    }
}
