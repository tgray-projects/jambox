<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Foo\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel(
            array(
                'get'   => $this->getRequest()->getQuery(),
                'post'  => $this->getRequest()->getPost()
            )
        );
    }

    public function redirectAction()
    {
        return $this->redirect()->toRoute('foo-test');
    }

    public function testAction()
    {
    }
}
