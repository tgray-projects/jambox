<?php
/**
 * Perforce Workshop
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage\View\Helper;

use Zend\View\Helper\AbstractHelper;

class Message extends AbstractHelper
{
    /**
     * Returns the markup for messages on the page
     *
     * @return  string  the messages html
     */
    public function __invoke()
    {
        $view    = $this->getView();
        $message = $view->message;

        if (!isset($message)) {
            return '';
        }

        $html = '<div class="global-notification border-box">';
        foreach ($message as $alert) {
            $html .= '<div class="alert alert-block alert-';
            $html .= ($alert['type']) ?: 'info';
            $html .= '"><button type="button" class="close" data-dismiss="alert">&times;</button>';
            $html .= $alert['body'];
            $html .= '</div>';
        }
        $thml .= '</div>';

        return $html;
    }
}
