<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Application\Filter\Compress;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Zend\Filter\Compress\AbstractCompressionAlgorithm;
use Zend\Filter\Exception\RuntimeException as RuntimeException;

class CommandLineCompression extends AbstractCompressionAlgorithm
{
    protected $command = null;
    protected $options = array(
        'archive'    => null,
        'autoSelect' => true,
        'method'     => 'zip'
    );
    protected $methods = array(
        'tgz' => 'tar',
        'zip' => 'zip'
    );

    public function __construct($options = null)
    {
        parent::__construct($options);

        // sanity check on archive method, ensuring it is supported and the command exists on the system
        $this->setMethod($this->getMethod());

        // sanity check on the archive file
        if ($this->getArchive() !== null) {
            $this->setArchive($this->getArchive());
        }
    }

    /**
     * Returns the current archive filename.
     *
     * @return  string|null current archive filename
     */
    public function getArchive()
    {
        return $this->options['archive'];
    }

    /**
     * Returns the current method auto selection value - whether we will look for other supported methods if the one the
     * user wants is not found.
     *
     * @return  bool    whether or not we will look for other methods if the user-specified one can't be found
     */
    public function getAutoSelect()
    {
        return $this->options['autoSelect'];
    }

    /**
     * Returns the current archive/compression method being used.
     *
     * @return string   current archive/compression mode
     */
    public function getMethod()
    {
        return $this->options['method'];
    }

    /**
     * Sets the name of the archive file to use, checking that the directory is writable or if the file already
     * exists that it is writable itself.
     *
     * @param   string              $archive    name (absolute path) to the archive file
     * @return  CommandLineCompression          maintain a fluent interface
     * @throws  RuntimeException    if the archive file can't be written to
     */
    public function setArchive($archive)
    {
        // ensure we can create files in the directory, and that if the file exists it is writable
        if (!is_writable(dirname($archive)) || (file_exists($archive) && !is_writable($archive))) {
            throw new RuntimeException('Cannot write to ' . $archive);
        }
        $this->options['archive'] = $archive;
        return $this;
    }

    /**
     * Sets whether method auto select is enabled. If enabled, we will look for other supported methods if the one
     * currently-set is not found.
     *
     * @param   bool                    $autoSelect whether auto selection is enabled or not
     * @return  CommandLineCompression  maintain a fluent interface
     */
    public function setAutoSelect($autoSelect)
    {
        $this->options['autoSelect'] = $autoSelect;
        return $this;
    }

    /**
     * Sets the current archive and compression method, as well as the command that does the work. This method
     * will sanity check to ensure that the method is supported by the class, and that the command itself
     * exists on the system.
     *
     * @param   string                  $method     method to use, such as 'zip' or 'tgz'
     * @return  CommandLineCompression  maintain a fluent interface
     * @throws  RuntimeException        thrown if the method isn't supported or the command is not found
     */
    public function setMethod($method)
    {
        $method  = strtolower($method);

        // ensure this is a supported method
        if (!isset($this->methods[$method])) {
            throw new RuntimeException("Unsupported method: '$method'");
        }

        $command = $this->getCommandPath($this->methods[$method]);

        if ($command == false && $this->getAutoSelect()) {
            // remove the method we were unable to find so we don't check it again
            unset($this->methods[$method]);

            // check all remaining methods until we find one that can be used
            foreach ($this->methods as $method => $command) {
                if ($command = $this->getCommandPath($command) !== false) {
                    break;
                }
            }
        }

        if ($command == false) {
            $this->options['method'] = '';
            $this->command           = '';
            throw new RuntimeException('Command not found: ' . $method);
        }

        $this->options['method'] = $method;
        $this->command           = $command;

        return $this;
    }

    /**
     * Sets the list of supported methods and their associated commands.
     *
     * @param   array                   $methods    map of method names to their commands
     * @return  CommandLineCompression  maintain a fluent interface
     */
    public function setMethods($methods)
    {
        $this->methods = $methods;
        return $this;
    }

    /**
     * Archives and compresses the given path, returning the archive filename on success or false on failure.
     *
     * @param   string              $dir  absolute path to archive
     * @return  bool|string         false on archive creation failure, absolute path to the archive on success
     * @throws  RuntimeException    thrown if the path the caller wants to archive does not exist or is unreadable
     */
    public function compress($dir)
    {
        if (!is_dir($dir) || !is_readable($dir)) {
            throw new RuntimeException('Archive source path does not exist, or is unreadable.');
        }

        // todo: move the directory up to the first child directory that contains files
        // save the current working directory and change into the source directory
        $cwd = getcwd();
        chdir($dir);

        // if no archive file is found, create one
        if (!$this->getArchive()) {
            $file = tempnam(sys_get_temp_dir(), 'archive');
            if ($file === false) {
                throw new RuntimeException('Could not create temporary file for archive build.');
            }
            $this->setArchive($file);
        }

        // calling our helper to do the actual work and return on success
        switch($this->getMethod()) {
            case "tgz":
                $archive = $this->compressTar($dir) ? $this->getArchive() : false;
                break;
            case "zip":
                $archive = $this->compressZip($dir) ? $this->getArchive() : false;
                break;
            default:
                $archive = false;
        }

        // change our working directory back to the original one
        chdir($cwd);
        return $archive;
    }

    /**
     * Not implemented. Needed only because we have to implement it as part of the parent class.
     *
     * @param   string  $value  the data to decompress
     * @return  bool    always returns false (method not implemented)
     */
    public function decompress($value)
    {
        return false;
    }

    /**
     * Returns the compression algorithm name. Required implementation as part of the parent class.
     *
     * @return  string  compression algorithm name
     */
    public function toString()
    {
        return "CommandLine";
    }

    /**
     * Helper function to create a tar archive in the current directory.
     *
     * @return  bool            true if successful, false otherwise
     */
    protected function compressTar()
    {
        $tarbin  = $this->methods[$this->getMethod()];
        $archive = $this->getArchive();
        $command = array();
        $command[] = escapeshellcmd($tarbin);
        $command[] = '-zcf';
        $command[] = escapeshellarg($archive);
        $command[] = '.';
        exec(implode(' ', $command), $output, $result);
        return !(bool) $result;
    }
    /**
     * Helper to create a zip archive in the current directory.
     *
     * @return  boolean         true if successful, false otherwise
     */
    protected function compressZip()
    {
        // get the list of files from the current path
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $zip = new \ZipArchive();
        if ($zip->open($this->getArchive()) == false) {
            return false;
        }

        foreach ($iterator as $name => $object) {
            if (is_file($name) && is_readable($name)) {
                $zip->addFile($name);
            }
        }
        return true;
    }
    /**
     * Returns the absolute path to the specified command name, or false if the command could not be found.
     *
     * @param   string      $command    name of the command to find (e.g. tar)
     * @return  string|bool absolute path to the specified command, or false if the command could not be found
     */
    protected function getCommandPath($command)
    {
        exec('which ' . escapeshellarg($command), $output, $result);
        if ((bool) $result) {
            return false;
        }
        return $output[0];
    }
}
