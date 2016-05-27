<?php
/**
 * Perforce Swarm
 *
 * @copyright   2015 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Markdown\View\Helper;

use Zend\View\Helper\AbstractHelper;

class PurifiedMarkdown extends AbstractHelper
{
    /**
     * Generates html from the supplied markdown text.  Runs content through HTMLPurifier to remove
     * XSS vulnerabilities.
     *
     * @param  string   $value  markdown text to be parsed
     * @return string   parsesed and escaped (for html context) result
     */
    public function __invoke($value)
    {
        $parsedown  = new \Parsedown();
        $parsedown->setMarkupEscaped(true);

        $contents = $parsedown->text($value);

        // don't allow arbitrary html in our markdown
        $config = \HTMLPurifier_Config::createDefault();

        $cache = DATA_PATH . '/cache';
        if (is_dir($cache) && is_writable($cache)) {
            $config->set('Cache.SerializerPath', $cache);
        }
        $config->set('Attr.EnableID', true);
        $config->set('Attr.IDPrefix', 'md_');

        $purifier = new \HTMLPurifier($config);
        $contents = $purifier->purify($contents);
        return $contents;
    }
}
