<?php
/**
 * Perforce Swarm
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace MarkdownTest\Controller;

use ModuleTest\TestControllerCase;
use Projects\Model\Project;
use P4\File\File;

class IndexControllerTest extends TestControllerCase
{
     /**
     * Test to ensure that the project page displays properly with no readme file present.
     */
    public function testProjectNoReadme()
    {
        // create project to test with
        $project = new Project($this->p4);
        $project->set(
            array(
                'id'      => 'prj',
                'members' => array('foo-member'),
                'creator' => 'foo-member',
                'owners'  => array()
            )
        )->save();

        $this->dispatch('/project/readme/prj');
        $this->assertRoute('project-readme');
        $this->assertRouteMatch('markdown', 'markdown\controller\indexcontroller', 'project');
        $this->assertResponseStatusCode(200);
        $result = $this->getResult();

        $this->assertInstanceOf('Zend\View\Model\JsonModel', $result);
        $this->assertSame('', $result->getVariable('readme'));
    }

    /**
     * Test the markdown rendering in place.
     */
    public function testMarkdownFile()
    {
        $markdown = '# Celerique habitantum

## Pro ubi respondit flammae

Lorem markdownum, obscenas ut audacia coeunt pulchro excidit obstitit vulnera!
Medio est non protinus, *ne* et elige!

    computing.redundancy_power_olap(webSymbolic);
    if (internalOfNosql > dpi + agp + desktop(printer_offline)) {
        mbrVlogCircuit(truncate, cd, word_software * burn_on);
    }
    siteMemory(nybble_string, tag_output_vista.lionMacHibernate(5, artificial));

Temporis circum. Non cogit dira cave iubent rursus pleno ritu inferius meus;
flatuque agmina me. Mira ad vicimus, minus delet, ab tetigisse solet in adiuvet
dubitas. Suo monuit coniunx ordine germanam.';

        // add a file to the depot
        $file = new File;
        $file->setFilespec('//depot/foo/testfile1.md')
            ->open()
            ->setLocalContents($markdown)
            ->submit('change test');

        $this->dispatch('/files/depot/foo/testfile1.md');

        $result = $this->getResult();
        $this->assertRoute('file');
        $this->assertRouteMatch('files', 'files\controller\indexcontroller', 'file');
        $this->assertResponseStatusCode(200);
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        $this->assertSame('depot/foo/testfile1.md', $result->getVariable('path'));
        $this->assertInstanceOf('P4\File\File', $result->getVariable('file'));
        $this->assertQueryContentContains('h1', 'testfile1.md');

        // verify markdown
        $this->assertQueryContentContains('h1', 'Celerique habitantum');
        $this->assertQueryContentContains('h2', 'Pro ubi respondit flammae');
        $this->assertQueryContentContains('em', 'ne');
        $this->assertQueryContentContains('pre code', 'computing.redundancy_power_olap(webSymbolic);');
    }

    /**
     * Test the markdown file rendering, does not cover all cases, but a few well known ones.
     */
    public function testDangerousMarkdownFile()
    {
        $markdown = '[some text](javascript:alert(\'xss\'))

> hello <a name="n"
> href="javascript:alert(\'xss\')">*you*</a>

{@onclick=alert(\'hi\')}some paragraph

[xss](http://"onmouseover="alert(1))';
        // add a file to the depot
        $file = new File;
        $file->setFilespec('//depot/foo/dangereux.md')
            ->open()
            ->setLocalContents($markdown)
            ->submit('change test');

        $this->dispatch('/files/depot/foo/dangereux.md');

        $result = $this->getResult();
        $this->assertRoute('file');
        $this->assertRouteMatch('files', 'files\controller\indexcontroller', 'file');
        $this->assertResponseStatusCode(200);
        $this->assertInstanceOf('Zend\View\Model\ViewModel', $result);
        $this->assertSame('depot/foo/dangereux.md', $result->getVariable('path'));
        $this->assertInstanceOf('P4\File\File', $result->getVariable('file'));
        $this->assertQueryContentContains('h1', 'dangereux.md');

        // verify markdown
        $this->assertQueryContentContains('div.markdown a', 'some text');
        $this->assertQueryContentContains(
            'div.markdown',
            'hello <a name="n"' . "\n" . 'href="javascript:alert(\'xss\')">'//<em>'//you</em>'//&lt;/a&gt;'
        );
        $this->assertQueryContentContains('div.markdown', "{@onclick=alert('hi')}some paragraph");
        $this->assertNotQuery(
            'div.markdown a[href=""]',
            'xss'
        );
    }
}
