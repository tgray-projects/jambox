<?php
/**
 * Perforce Swarm
 *
 * @copyright   2014 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Application\Support;

class SystemData
{
    protected $systemInfo;

    public function __construct($config = null)
    {
        // ensure the config has been sanitized (values for keys related to tickets or passwords are scrubbed)
        $this->systemInfo = array('config' => $this->sanitize($config));
    }

    /**
     * Returns the system data for the requested key, or for all keys if false is given.
     *
     * @param   string|bool $key    key for specific system data element, or false for everything
     * @return  mixed       all collected system data, the data for a specific key, or false if the specified key was
     *                      not found
     */
    public function getData($key = false)
    {
        $this->collectData();

        if ($key === false) {
            return $this->systemInfo;
        }

        if (isset($this->systemInfo[$key])) {
            return $this->systemInfo[$key];
        }

        return false;
    }

    /**
     * Collects all desired data from the local system (process usage, OS type, current date/time with timezone, etc).
     * For Windows, we just return the sanitized config.
     *
     * @return  array   all system data we were able to collect based on the available commands
     */
    private function collectData()
    {
        // if we're on a windows box, or we've already collected data, bail early
        if (stripos(php_uname('s'), 'Windows') !== false || isset($this->systemInfo['uname'])) {
            return $this->systemInfo;
        }

        // collect all the data we can, based on what commands are available
        $this->systemInfo['uname'] = php_uname('a');
        $this->systemInfo['disk']  = $this->collect('df',    '-h');
        $this->systemInfo['date']  = $this->collect('date',  '++"%Y-%m-%d %H:%m:%S[%z/%Z]"');

        if (stripos(php_uname('s'), 'Linux')) {
            // Linux distributions
            $this->systemInfo['process'] = $this->collect(
                'top',
                '-bn1',
                function ($line) {
                    return preg_match('/(top|tasks|mem|cpu|\s*pid)|apache|httpd/i', $line);
                }
            );
        } else {
            // BSD variants, including OSX (Darwin)
            $this->systemInfo['process'] = $this->collect(
                'top',
                '-l1',
                function ($line) {
                    return preg_match('/(load avg|cpu|physmem|vm|disks|\s*pid)|apache|httpd/i', $line);
                }
            );
        }

        return $this->systemInfo;
    }

    /**
     * Wrapper for collecting data from system commands. This function will run the command with the supplied
     * arguments, apply an optional filter, and will return the output of the command as an array if it is
     * more than one line, or a string if it is a single line of output. Returns false on failure.
     *
     * @param   string              $command    command to run
     * @param   array               $args       arguments to the system command
     * @param   callable|null       $filter     optional filter to apply to the output before returning
     * @return  array|string|bool   output from the command as an array if multi-line, string if single line, or false
     *                              on failure
     */
    protected function collect($command, $args = array(), $filter = null)
    {
        if (!$this->commandExists($command)) {
            return false;
        }

        list($output, $result) = $this->executeCommand($command, $args);

        if ($filter !== null && is_callable($filter)) {
            $output = array_filter($output, $filter);
        }

        // if there's only one element (e.g. 'date' command) just send that rather than a superfluous array
        return count($output) == 1 ? $output[0] : $output;
    }

    /**
     * Executes $command on the system with $args as command-line arguments.
     *
     * @param   string  $command    name of the command to run
     * @param   array   $args       array of arguments (will be escaped)
     * @return  array   first element is the output from the command, second element is boolean reflecting success
     */
    protected function executeCommand($command, $args = array())
    {
        $args    = is_array($args) ? $args : array($args);
        $command = implode(' ', array_map('escapeshellarg', array_merge(array($command), $args)));
        exec($command, $output, $result);
        return array($output, (bool) !$result);
    }

    /**
     * Tests whether the specified command exists on the system.
     *
     * @param   string          $command    name of the command to check for existence
     * @return  bool            true if the command can be found, false otherwise
     */
    protected function commandExists($command)
    {
        list($output, $result) = $this->executeCommand('which', array($command));
        return (bool) $result;
    }

    /**
     * Sanitizes an array by scrubbing values for keys that contain the word 'password' or 'ticket'.
     *
     * @param   array   $config     the array/config to sanitize
     * @return  array   sanitized array/config
     */
    protected function sanitize($config)
    {
        if (!is_array($config) || empty($config)) {
            return $config;
        }

        array_walk_recursive(
            $config,
            function (&$value, $key) {
                // obscure potentially-sensitive keys
                if (preg_match('/^(password|ticket)$/i', $key)) {
                    $value = '<' . strtoupper($key) . '>';
                }
            }
        );

        return $config;
    }
}
