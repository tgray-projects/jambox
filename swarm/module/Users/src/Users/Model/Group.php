<?php
/**
 * Perforce Swarm
 *
 * @copyright   2013 Perforce Software. All rights reserved.
 * @license     Please see LICENSE.txt in top-level folder of this distribution.
 * @version     <release>/<patch>
 */

namespace Users\Model;

use P4\Connection\ConnectionInterface as Connection;
use P4\Connection\Exception\ServiceNotFoundException;
use P4\Model\Fielded\Iterator as FieldedIterator;
use P4\Spec\Exception\NotFoundException as SpecNotFoundException;
use Record\Cache\ArrayReader;
use Record\Cache\ArrayWriter;

class Group extends \P4\Spec\Group
{
    const   FETCH_NO_CACHE  = 'noCache';

    /**
     * Creates a new Group object and sets the passed values on it.
     *
     * @param   array       $values         array of values to set on the new group
     * @param   Connection  $connection     connection to set on the new group
     * @return  Group       the populated group
     */
    public static function fromArray($values, Connection $connection = null)
    {
        $group = new static($connection);
        $group->set($values);

        // if you provided an id; we defer populate to allow lazy loading.
        // in practice; we anticipate the object is already fully populated
        // so this really shouldn't make an impact.
        if (isset($values['Group'])) {
            $group->deferPopulate();
        }

        return $group;
    }

    /**
     * Extends exists to use cache if available.
     *
     * @param   string      $id             the id to check for.
     * @param   Connection  $connection     optional - a specific connection to use.
     * @return  bool        true if the given id matches an existing group.
     */
    public static function exists($id, Connection $connection = null)
    {
        try {
            $groups = static::getCachedData($connection);
            return isset($groups[$id]);
        } catch (ServiceNotFoundException $e) {
            return parent::exists($id, $connection);
        }
    }

    /**
     * Just get the list of member ids associated with the passed group.
     *
     * @param   string      $id         the id of the group to fetch members of.
     * @param   array       $options    optional - array of options to augment fetch behavior.
     *                                    FETCH_INDIRECT - used to also list indirect matches.
     * @param   Connection  $connection optional - a specific connection to use.
     * @return  array       an array of member ids
     */
    public static function fetchMembers($id, $options = array(), Connection $connection = null)
    {
        $seen    = array();
        $recurse = function ($id) use (&$recurse, &$seen, $connection) {
            $group     = Group::fetch($id, $connection);
            $users     = $group->getUsers();
            $seen[$id] = true;
            foreach ($group->getSubgroups() as $sub) {
                if (!isset($seen[$sub])) {
                    $users = array_merge($users, $recurse($sub));
                }
            }
            return $users;
        };

        // if indirect fetching is enabled; go recursive
        if (isset($options[static::FETCH_INDIRECT]) && $options[static::FETCH_INDIRECT]) {
            return array_unique($recurse($id));
        }

        return static::fetch($id, $connection)->getUsers();
    }

    /**
     * Extends fetch to use cache if available.
     *
     * @param   string          $id         the id of the entry to fetch.
     * @param   Connection      $connection optional - a specific connection to use.
     * @return  PluralAbstract  instance of the requested entry.
     * @throws  \InvalidArgumentException   if no id is given.
     */
    public static function fetch($id, Connection $connection = null)
    {
        $connection = $connection ?: static::getDefaultConnection();

        try {
            $groups = static::getCachedData($connection);
        } catch (ServiceNotFoundException $e) {
            return parent::fetch($id, $connection);
        }

        // if we have a cached group, turn it into an object
        if (isset($groups[$id])) {
            return static::fromArray($groups[$id], $connection);
        }

        throw new SpecNotFoundException("Cannot fetch group $id. Record does not exist.");
    }

    /**
     * Extends fetchAll to use cache if available.
     *
     * @param   array       $options    optional - array of options to augment fetch behavior.
     *                                  supported options are:
     *                                   FETCH_MAXIMUM - set to integer value to limit to the first
     *                                                   'max' number of entries.
     *                                                   *Note: Limits imposed client side.
     *                                 FETCH_BY_MEMBER - Not supported
     *                                   FETCH_BY_USER - get groups containing passed user (no wildcards).
     *                                  FETCH_INDIRECT - used with FETCH_BY_MEMBER or FETCH_BY_USER
     *                                                   to also list indirect matches.
     *                                   FETCH_BY_NAME - get the named group. essentially a 'fetch'
     *                                                   but performed differently (no wildcards).
     *                                                   *Note: not compatible with FETCH_BY_MEMBER
     *                                                          FETCH_BY_USER or FETCH_INDIRECT
     *                                  FETCH_NO_CACHE - set to true to avoid using the cache.
     *
     * @param   Connection  $connection optional - a specific connection to use.
     * @return  FieldedIterator         all matching records of this type.
     * @throws  \InvalidArgumentException       if FETCH_BY_MEMBER is used
     */
    public static function fetchAll($options = array(), Connection $connection = null)
    {
        // Validate the various options by having parent generate fetch all flags.
        // We don't actually use the flags but the option verification is valuable.
        static::getFetchAllFlags($options);

        if (isset($options[static::FETCH_BY_MEMBER]) && $options[static::FETCH_BY_MEMBER]) {
            throw new \InvalidArgumentException(
                "The User Group model doesn't support FETCH_BY_MEMBER."
            );
        }

        // normalize connection
        $connection = $connection ?: static::getDefaultConnection();

        // optionally avoid the cache
        if (isset($options[static::FETCH_NO_CACHE]) && $options[static::FETCH_NO_CACHE]) {
            return parent::fetchAll($options, $connection);
        }

        // if we have a cache service use it; otherwise let parent handle it
        try {
            $groups = static::getCachedData($connection);
        } catch (ServiceNotFoundException $e) {
            return parent::fetchAll($options, $connection);
        }

        // now that parent is done with options; normalize them
        // if we do this earlier it will cause issues with parent
        $options    = (array) $options + array(
            static::FETCH_MAXIMUM   => null,
            static::FETCH_BY_MEMBER => null,
            static::FETCH_BY_USER   => null,
            static::FETCH_INDIRECT  => null,
            static::FETCH_BY_NAME   => null
        );

        // always going to have an iterator as a result at this point; make it
        $result = new FieldedIterator;

        // Fetch by name is essentially a fetch that returns an iterator
        // handle that case early as it is simple
        if ($options[static::FETCH_BY_NAME]) {
            $id = $options[static::FETCH_BY_NAME];
            if (isset($groups[$id])) {
                $result[$id] = static::fromArray($groups[$id], $connection);
            }
            return $result;
        }

        // turn group arrays into objects and apply various filters if present
        $limit    = $options[static::FETCH_MAXIMUM];
        $user     = $options[static::FETCH_BY_USER];
        $indirect = $options[static::FETCH_INDIRECT];
        foreach ($groups as $id => $group) {
            // if max limiting, stop when/if we exceed max
            if ($limit && count($result) >= $limit) {
                break;
            }

            // if filtering by member, exclude groups that don't match
            if ($user && !static::isMember($user, $id, $indirect, $connection)) {
                continue;
            }

            // passes the filters, lets add it to the result
            $result[$id] = static::fromArray($group, $connection);
        }

        return $result;
    }

    /**
     * Test if the passed user is a direct (or if recursive is set, even indirect)
     * member of the specified group.
     *
     * @param   string      $user       the user id to check membership for
     * @param   string      $group      the group id we are looking in
     * @param   bool        $recursive  true if we are also checking sub-groups,
     *                                  false for only testing direct membership
     * @param   Connection  $connection optional - a specific connection to use.
     * @return  bool        true if user is a member of specified group (or sub-group if recursive), false otherwise
     * @throws  \InvalidArgumentException   if an invalidly formatted user of group id is passed
     */
    public static function isMember($user, $group, $recursive = false, Connection $connection = null)
    {
        // do basic input validation
        if (!static::isValidUserId($user)) {
            throw new \InvalidArgumentException(
                'Is Member expects a valid username.'
            );
        }
        if (!static::isValidId($group)) {
            throw new \InvalidArgumentException(
                'Is Member expects a valid group.'
            );
        }

        // try and get the group cache. if we fail, fall back to a live check
        try {
            $groups = static::getCachedData($connection);
        } catch (ServiceNotFoundException $e) {
            $groups = parent::fetchAll(
                array(
                     static::FETCH_BY_MEMBER => $user,
                     static::FETCH_INDIRECT  => $recursive
                ),
                $connection
            );

            return isset($groups[$group]);
        }

        // if the group they asked for doesn't exist, not a member
        if (!isset($groups[$group])) {
            return false;
        }

        // if the user is a direct member; return true
        if (in_array($user, $groups[$group]['Users'])) {
            return true;
        }

        // if recursion is on, check all sub-groups
        if ($recursive) {
            foreach ($groups[$group]['Subgroups'] as $sub) {
                if (static::isMember($user, $sub, true, $connection)) {
                    return true;
                }
            }
        }

        // if we make it to the end they aren't a member
        return false;
    }

    /**
     * Get the raw group cache (arrays of values). Populate cache if empty.
     *
     * The high-level flow of this is:
     *  - try to read cache, return if that works
     *  - if read fails, try to build cache
     *  - whether write works or not, try to read cache again
     *  - if read fails again, throw.
     *
     * @param   Connection      $connection     optional - a specific connection to use.
     * @return  ArrayReader     a memory efficient group iterator
     */
    public static function getCachedData(Connection $connection = null)
    {
        $connection = $connection ?: static::getDefaultConnection();
        $cache      = $connection->getService('cache');
        $file       = $cache->getFile('groups');

        // groups are cached with an index file, so we can use the streaming reader to save on memory
        // if this fails for any reason, assume that the group cache needs to be (re)built.
        try {
            $reader = new ArrayReader($file);
            return $reader->openFile();
        } catch (\Exception $e) {
            // we will attempt to rebuild the cache below
        }

        // this can take a while if there are lots of users/groups - let it run for 30m
        $limit = ini_get('max_execution_time');
        ini_set('max_execution_time', 30 * 60);

        // wrap cache rebuild in try/catch so we can make one last attempt at reading
        try {
            $writer = new ArrayWriter($file, true);
            $writer->createFile();

            // fetch all of the groups, but use the filter callback to stream them into the cache file
            parent::fetchAll(
                array(
                    Group::FETCH_FILTER_CALLBACK => function ($group) use ($writer) {
                        $writer->writeElement($group['Group'], $group);
                        return false;
                    }
                ),
                $connection
            );

            // need to close file to record array length
            $writer->closeFile();
        } catch (\Exception $writerException) {
            // writer can throw due to a race condition (another process just built the cache)
            // or due to a legitimate problem (such as bad file permissions), either way we
            // try to read again and if that fails then we re-throw this exception
        }

        // hard work is done, restore original time limit
        ini_set('max_execution_time', $limit);

        // return reader for newly cached groups
        try {
            $reader = new ArrayReader($file);
            return $reader->openFile();
        } catch (\Exception $readerException) {
            // we pick the best exception to re-throw below
        }

        // if we get this far we have a writer and/or a reader exception
        // the writer exception is more relevant, so favor it over the reader
        throw isset($writerException) ? $writerException : $readerException;
    }
}
