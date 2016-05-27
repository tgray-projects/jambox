<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Frontpage\View\Helper;

use Zend\View\Helper\AbstractHelper;

class ProjectGrid extends AbstractHelper
{
    /**
     * @param int $rows
     * @param int $cols
     * @param string $title
     * @return string
     */
    public function __invoke($count = 6, $title = 'Recent Projects')
    {
        $view = $this->getView();
        $more = $view->url('explore');
        $html = <<<EOT
<div class="projects-list projects-grid" data-type="active" data-presentation="grid" data-count="$count">
    <div class="grid-header">
        <div class="pull-left">
            <h4 class="gear">$title</h4>
        </div>
        <div>
            <small class="pull-right"><a href="$more">More >></a></small>
        </div>
    </div>
    <div class="grid-body well">
        <ul>
        </ul>
    </div>
</div>
EOT;

        return $html;
    }
}
