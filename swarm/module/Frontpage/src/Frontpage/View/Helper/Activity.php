<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage\View\Helper;

use Zend\View\Helper\AbstractHelper;

class Activity extends AbstractHelper
{
    /**
     * Returns the markup for an activity stream and injects a rss feed link.
     *
     * @param   string              $classes            optional - list of additional classes to set on the table
     * @return  string              the activity stream html
     */
    public function __invoke($classes = '')
    {
        $view   = $this->getView();

        // prepare markup for activity markup.
        $classes    = $view->escapeHtmlAttr($classes . ' stream-global');
        $html       = <<<EOT
            <table
                class="$classes table activity-stream">
                <thead>
                    <tr>
                        <th colspan="2">
                            <div class="pull-left">
                                <h4 class="gear">Workshop Activity</h4>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>

            <script type="text/javascript">
                $(function(){
                    frontpage.activity.load();
                });
            </script>
EOT;

        return $html;
    }
}
