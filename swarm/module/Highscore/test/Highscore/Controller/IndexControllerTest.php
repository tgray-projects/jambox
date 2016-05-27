<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace HighscoreTest\Controller;

use P4\File\File;
use ModuleTest\TestControllerCase;

class IndexControllerTest extends TestControllerCase
{

    public function testHighscoreAction()
    {
        $file = new File;
        $file->setFilespec('//depot/a/foo');
        $file->setLocalContents('bar');
        $file->add();
        $file->submit('test');

        $this->dispatch('/highscore');

        $body   = $this->getResponse()->getBody();

        var_dump($body);

        $this->assertModule('Teampage');
        $this->assertRoute('highscore');
        $this->assertAction('highscore');
        $this->assertResponseStatusCode(200);
    }
}
