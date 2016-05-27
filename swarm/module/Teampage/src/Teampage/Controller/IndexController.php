<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */
 
namespace Teampage\Controller;

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
    public function teampageAction()
    {

        return new ViewModel(
            array(
                 'title' => 'Team Page'
            )
        );
    }
}
