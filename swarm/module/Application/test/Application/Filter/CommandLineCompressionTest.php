<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace ApplicationTest\Filter;

use Application\Filter\Compress\CommandLineCompression;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Zend\Loader\AutoloaderFactory;
use Zend\Filter\Exception\RuntimeException;

class CommandLineCompressionTest extends \PHPUnit_Framework_TestCase
{
    protected $dir;

    public function setUp()
    {
        parent::setUp();
        $this->dir = tempnam(sys_get_temp_dir(), 'test-');
        @unlink($this->dir);
        @mkdir($this->dir);
        AutoloaderFactory::factory(
            array(
                'Zend\Loader\StandardAutoloader' => array(
                    'namespaces' => array(
                        'Application' => BASE_PATH . '/module/Application/src/Application'
                    )
                )
            )
        );
    }

    /**
     * @expectedException RuntimeException
     */
    public function testUnknownMethod()
    {
        new CommandLineCompression(array('method' => 'foo'));
    }

    /**
     * @expectedException RuntimeException
     */
    public function testArchiveNotWritable()
    {
        new CommandLineCompression(array('archive' => '/foo'));
    }

    public function testCommandNotFound()
    {
        try {
            $filter = new CommandLineCompression;
            $filter->setMethods(array('foo' => 'bar'))
                   ->setMethod('foo');
        } catch (RuntimeException $e) {
            $this->assertTrue(strpos($e->getMessage(), 'Command not found:') !== false);
        }
    }

    public function testAutoSelect()
    {
        $filter = null;
        $archive = tempnam(null, "swarmtest-");
        try {
            $filter = new CommandLineCompression(array('autoSelect' => true, 'archive' => $archive));
            $filter->setMethods(array('foo' => 'bar', 'luke' => 'vader', 'zip' => 'zip', 'tgz' => 'tar'))
                   ->setMethod('foo');
            $this->assertNotEquals($filter->getMethod(), '');
            $this->assertNotEquals($filter->getMethod(), 'foo');
            $this->assertNotEquals($filter->getMethod(), 'luke');
            $this->assertTrue($filter->getMethod() == 'zip' || $filter->getMethod() == 'tgz');
        } catch (RuntimeException $e) {
            // if the command wasn't found, that's ok as long as it's tar (meaning we've gone through everything)
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->assertSame($filter->getMethod(), 'tgz');
            }
            // re-throw otherwise
            throw $e;
        }
        unlink($archive);
    }

    public function testZipSingleFile()
    {
        $file   = 'foo.txt';
        $filter = null;
        try {
            $filter = new CommandLineCompression;
            $this->setupFiles(array($file));
            $filter->compress($this->dir);
            $this->cleanupFiles();

            // test to see that the archive was created and contains the file we added
            $output = $this->executeCommand('unzip', array('-t', $filter->getArchive()));
            $this->assertTrue(strpos($output[1], 'testing: ./' . $file) > 0);
            $this->assertTrue(strpos($output[2], 'No errors detected ') >= 0);
            unlink($filter->getArchive());
        } catch (RuntimeException $e) {
            // if the command wasn't found, we can skip this test
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->cleanupFiles();
                $filter && @unlink($filter->getArchive());
                $this->markTestSkipped($e->getMessage());
            }
            // otherwise, re-throw
            throw $e;
        }
    }

    public function testTarSingleFile()
    {
        $file   = 'foo.txt';
        $filter = null;
        try {
            $filter = new CommandLineCompression(array('method' => 'tgz'));
            $this->setupFiles(array($file));

            $filter->compress($this->dir);
            $this->cleanupFiles();

            $output = $this->executeCommand('tar', array('-tzf', $filter->getArchive()));
            $this->assertTrue(strpos($output[0], $file) >= 0);
            unlink($filter->getArchive());
        } catch (RuntimeException $e) {
            // if the command wasn't found, we can skip this test
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->cleanupFiles();
                $filter && @unlink($filter->getArchive());
                $this->markTestSkipped($e->getMessage());
            }
            // otherwise, re-throw
            throw $e;
        }
    }

    public function testTarMultiFile()
    {
        $files = array('foo.txt', 'bar.txt');
        $filter = null;
        try {
            $filter = new CommandLineCompression(array('method' => 'tgz'));
            $this->setupFiles($files);

            $filter->compress($this->dir);
            $this->cleanupFiles();

            $output = $this->executeCommand('tar', array('-tzf', $filter->getArchive()));
            $this->assertTrue(count($output) > 0);
            $this->assertTrue(strpos($output[0], $files[0]) >= 0);
            $this->assertTrue(strpos($output[1], $files[1]) >= 0);
            unlink($filter->getArchive());
        } catch (RuntimeException $e) {
            // if the command wasn't found, we can skip this test
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->cleanupFiles();
                $filter && @unlink($filter->getArchive());
                $this->markTestSkipped($e->getMessage());
            }
            // otherwise, re-throw
            throw $e;
        }
    }

    public function testTarNested()
    {
        $files  = array('bar.txt', 'baz/yoda.txt', 'foo.txt', 'yordle/teemo.txt');
        $dirs   = array('baz', 'yordle');
        $filter = null;
        try {
            $filter = new CommandLineCompression(array('method' => 'tgz'));
            $this->setupFiles($files, $dirs);

            // archive the path
            $filter->compress($this->dir);

            // clean up
            $this->cleanupFiles();

            // ensure the archive was created properly
            $output = $this->executeCommand('tar', array('-tzf', $filter->getArchive()));
            sort($output);

            // unzip lists the files sorted in alphabetic order
            for ($i=0; $i < count($files); $i++) {
                $this->assertTrue(array_search('./'+$files[$i], $output) !== false);
            }

            unlink($filter->getArchive());
        } catch (RuntimeException $e) {
            // if the command wasn't found, we can skip this test
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->cleanupFiles();
                $filter && @unlink($filter->getArchive());
                $this->markTestSkipped($e->getMessage());
            }
            // otherwise, re-throw
            throw $e;
        }
    }

    public function testZipMultiFile()
    {
        $files  = array('foo.txt', 'bar.txt');
        $filter = null;
        try {
            $filter = new CommandLineCompression;
            $this->setupFiles($files);

            // make an archive
            $filter->compress($this->dir);

            // clean up
            $this->cleanupFiles();

            $zip = new \ZipArchive();
            if ($zip->open($filter->getArchive()) !== true) {
                throw RuntimeException("Cannot open ".$filter->getArchive());
            }
            for ($i=0; $i<sizeof($files); $i++) {
                $this->assertTrue($zip->locateName('./'.$files[$i]) !== false);
            }
            unlink($filter->getArchive());
        } catch (RuntimeException $e) {
            // if the command wasn't found, we can skip this test
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->cleanupFiles();
                $filter && @unlink($filter->getArchive());
                $this->markTestSkipped($e->getMessage());
            }
            // otherwise, re-throw
            throw $e;
        }
    }

    public function testZipNested()
    {
        $files  = array('bar.txt', 'baz/yoda.txt', 'foo.txt', 'yordle/teemo.txt');
        $dirs   = array('baz', 'yordle');
        $filter = null;
        try {
            $filter = new CommandLineCompression;
            $this->setupFiles($files, $dirs);

            // archive the path
            $filter->compress($this->dir);

            // clean up
            $this->cleanupFiles();

            // ensure the archive was created properly
            $zip = new \ZipArchive();
            $zip->open($filter->getArchive());

            // ensure that all files are in the zip archive
            for ($i=0; $i < count($files); $i++) {
                $this->assertTrue($zip->locateName('./'.$files[$i]) !== false);
            }

            unlink($filter->getArchive());
        } catch (RuntimeException $e) {
            // if the command wasn't found, we can skip this test
            if (strpos($e->getMessage(), 'Command not found:') !== false) {
                $this->cleanupFiles();
                $filter && @unlink($filter->getArchive());
                $this->markTestSkipped($e->getMessage());
            }
            // otherwise, re-throw
            throw $e;
        }
    }

    /**
     * Removes test directory and any associated files.
     */
    protected function cleanupFiles()
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $name => $object) {
            $name = realpath($name);
            if (is_file($name)) {
                @unlink($name);
            } else {
                @rmdir($name);
            }
        }
        @rmdir($this->dir);
    }

    protected function executeCommand($command, $args = array())
    {
        exec(
            implode(
                ' ',
                array_map(
                    'escapeshellarg',
                    array_merge(array($this->getCommand($command)), $args)
                )
            ),
            $output,
            $result
        );
        return $output;
    }

    /**
     * Returns the absolute path to the specified command, if it exists on the system.
     *
     * @param   string              $command    command name (e.g. unzip, file, tar)
     * @return  string              absolute path to the specified command
     * @throws  RuntimeException    thrown if the command does not exist on the system
     */
    protected function getCommand($command)
    {
        $output = array();
        exec('which ' . escapeshellarg($command), $output, $result);
        if (count($output)) {
            return $output[0];
        }
        throw new RuntimeException('Command not found: ' . $command);
    }

    /**
     * Creates specified directory and files on disk with some garbage data.
     *
     * @param   array       $files  list of files (relative names) to create
     * @param   array|null  $dirs   list of directories (relative) to create
     */
    protected function setupFiles($files, $dirs = null)
    {
        // create base directory
        @mkdir($this->dir, 0700, true);

        // create any required directories
        if ($dirs) {
            foreach ($dirs as $dir) {
                @mkdir($this->dir . '/' . $dir, 0700, true);
            }
        }

        // add files
        foreach ($files as $file) {
            file_put_contents($this->dir . '/' . $file, '012310231020');
        }
    }
}
