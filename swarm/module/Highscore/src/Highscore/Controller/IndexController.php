<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */
 
namespace Highscore\Controller;

use P4;
use P4\Spec\Change;
use Users\Model\User;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

 
class IndexController extends AbstractActionController
{
    /**
     * Determine the number of changelists submitted by each user
     *
     * @return ViewModel
     */
    public function highscoreAction()
    {
        $p4 = $this->getServiceLocator()->get('p4');
        // Array of users we don't want to include in the high score board
        $hitList = array('swarm', 'git-fusion-user', 'admin');
        try {
            $users = User::fetchAll(null, $this->p4);
            $allUsers = $users->invoke('getId');

            foreach($allUsers as $user) {
                if (!in_array($user, $hitList)) {
                    $changes = Change::fetchAll(
                        array(
                            Change::FETCH_BY_USER => $user,
                        ),
                        $p4
                    );
                    $userscore[$user] = sizeof($changes);
                }
            }
        } catch (P4_Exception $e) {

        }

        arsort($userscore);
        $username = array_keys($userscore);
        $numchanges = array_values($userscore);

        return new ViewModel(
            array(
                 'title' => 'High Scores',
                 'user' => $username,
                 'score' => $numchanges,
            )
        );
    }
}
