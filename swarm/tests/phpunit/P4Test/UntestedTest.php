<?php
/**
 * Attempt to require_once all files under P4 folder to verify they show in
 * code coverage reports.
 *
 * @copyright   2012 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace P4Test;

class UntestedTest extends TestCase
{
    /**
     * Requires all files to ensure they show up in coverage
     */
    public function testRequireAllFiles()
    {
        $files = new \RecursiveDirectoryIterator(BASE_PATH . '/library/P4');
        foreach ($files as $fileName => $file) {
            if (!$files->isFile() || pathinfo($fileName, PATHINFO_EXTENSION) !== 'php') {
                continue;
            }

            include_once($fileName);
        }
    }
}
