<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */
namespace Record\Cache;

/**
 * Memory efficient iterator for arrays that have been written AND indexed by ArrayWriter
 */
class ArrayReader implements \ArrayAccess, \Iterator
{
    protected $file   = null;
    protected $handle = null;
    protected $index  = null;

    /**
     * Setup a new array reader
     *
     * @param string    $file   the file to read array from
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    public function openFile()
    {
        $file      = $this->file;
        $indexFile = $file . ArrayWriter::INDEX_SUFFIX;
        if (!is_string($file) || !file_exists($file)) {
            throw new \RuntimeException("Cannot open file '" . $file . "'. File does not exist.");
        }

        $this->handle = @fopen($file, 'r');
        if ($this->handle === false) {
            throw new \RuntimeException("Unable to open file ('" . $file . "') for reading.");
        }

        // if anything goes wrong past this point, make sure we close/unlock our file
        try {
            // wait for a read lock to ensure file is not being actively written to
            $locked = flock($this->handle, LOCK_SH);
            if ($locked === false) {
                throw new \RuntimeException("Unable to lock file ('" . $file . "') for reading.");
            }

            if (!file_exists($indexFile)) {
                throw new \RuntimeException("Cannot open index file '" . $indexFile . "'. File does not exist.");
            }

            // read the entire index into memory (impractical to stream)
            $this->index = unserialize(file_get_contents($indexFile));
            if ($this->index === false) {
                throw new \RuntimeException("Cannot unserialize index file ('" . $indexFile . "').");
            }
        } catch (\Exception $e) {
            $this->closeFile();
            throw $e;
        }

        return $this;
    }

    public function closeFile()
    {
        if (!is_resource($this->handle)) {
            throw new \RuntimeException("Cannot close file. File is not open.");
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);

        return $this;
    }

    /**
     * Do a case-insensitive lookup for the given key - first match wins
     *
     * @param   mixed   $key    the array key to look for
     * @return  mixed   the matching key (in original case) or false if no match
     */
    public function noCaseLookup($key)
    {
        // note we make a local-scope (lazy) copy to avoid mucking the cursor
        $index = $this->index;
        foreach ($index as $candidate => $value) {
            if (strcasecmp($key, $candidate) === 0) {
                return $candidate;
            }
        }

        return false;
    }

    public function offsetExists($key)
    {
        // attempt to match PHP's key casting behavior
        // http://php.net/manual/en/language.types.array.php
        if (is_object($key) || is_array($key)) {
            return false;
        }
        if (is_null($key)) {
            $key = "";
        }
        if (!is_string($key) && !is_int($key)) {
            $key = (int) $key;
        }
        return array_key_exists($key, $this->index);
    }

    public function offsetGet($key)
    {
        if (!$this->offsetExists($key)) {
            return null;
        }

        $offset = $this->index[$key][0];
        $length = $this->index[$key][1];
        fseek($this->handle, $offset);

        // need to wrap serialized key/value in array format 'a:1{...}'
        // so that it will unserialize correctly into key/value
        $entry = unserialize('a:1:{' . fread($this->handle, $length) . '}');
        return $entry ? current($entry) : false;
    }

    public function offsetSet($key, $value)
    {
        throw new \BadMethodCallException("Cannot set element. Array is read-only.");
    }

    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException("Cannot unset element. Array is read-only.");
    }

    public function current()
    {
        return $this->offsetGet(key($this->index));
    }

    public function key()
    {
        return key($this->index);
    }

    public function next()
    {
        next($this->index);
    }

    public function rewind()
    {
        reset($this->index);
    }

    public function valid()
    {
        return key($this->index) !== null;
    }
}
