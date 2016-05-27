<?php
/**
 * Perforce Swarm, Community Development
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Markdown;

use Files\Format\Handler as FormatHandler;
use Zend\Mvc\MvcEvent;

class Module
{
    /**
     * Add a preview handler for markdown files in the file browser.
     * Note that files > 1MB will be cropped for performance reasons.
     */
    public function onBootstrap(MvcEvent $event)
    {
        $application = $event->getApplication();
        $services    = $application->getServiceManager();
        $formats     = $services->get('formats');

        $formats->addHandler(
            new FormatHandler(
                // can-preview callback
                function ($file, $extension, $mimeType, $request) {
                    // don't render markdown when diffing
                    if ($request && $request->getUri()->getPath() == '/diff') {
                        return false;
                    }
                    return in_array(
                        $extension,
                        array('markdown', 'mdown', 'mkdn', 'md', 'mkd', 'mdwn', 'mdtxt', 'mdtext')
                    );
                },
                // render-preview callback
                function ($file, $extension, $mimeType) use ($services) {
                    $helpers          = $services->get('ViewHelperManager');
                    $purifiedMarkdown = $helpers->get('purifiedMarkdown');

                    $maxSize  = 1048576; // 1MB
                    $contents = $file->getDepotContents(
                        array(
                            $file::UTF8_CONVERT  => true,
                            $file::UTF8_SANITIZE => true,
                            $file::MAX_FILESIZE  => $maxSize
                        )
                    );

                    return '<div class="view view-md markdown">'
                    .   $purifiedMarkdown($contents)
                    .  '</div>';
                }
            ),
            'markdown'
        );

        // override the view template
        $application->getEventManager()->attach('dispatch', array($this, 'setTemplate'), -100);
    }

    /**
     * Attaches to the setTemplate event to override the project view template.  This allows us to change how
     * the project page is rendered.
     *
     * @param MvcEvent $event
     */
    public function setTemplate($event)
    {
        $matches = $event->getRouteMatch();
        $route   = $matches->getMatchedRouteName();

        // only override project index controller "project" route
        if ($route !== 'project') {
            return;
        }

        $viewModel = $event->getViewModel();
        $children  = $viewModel->getChildren();
        $viewModel->clearChildren();
        foreach ($children as $child) {
            if ($child->getTemplate() == 'projects/index/project') {
                $child->setTemplate('markdown/index/project');
            }
            $viewModel->addChild($child);
        }
    }

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\StandardAutoloader' => array(
                'namespaces' => array(
                    __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
                ),
            ),
            'Zend\Loader\ClassMapAutoloader' => array(
                array(
                    'Parsedown'           => BASE_PATH . '/library/Parsedown/Parsedown.php',
                    'HTMLPurifier_Config' => BASE_PATH . '/library/HTMLPurifier/HTMLPurifier.standalone.php',
                    'HTMLPurifier'        => BASE_PATH . '/library/HTMLPurifier/HTMLPurifier.standalone.php',
                ),
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}
