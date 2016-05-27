<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace TeampageTest\Controller;

use P4\File\File;
use ModuleTest\TestControllerCase;

class IndexControllerTest extends TestControllerCase
{

    public function testHighscoreAction()
    {
        $this->dispatch('/team');
        $this->assertModule('Teampage');
        $this->assertRoute('team');
        $this->assertResponseStatusCode(200);
    }
}
