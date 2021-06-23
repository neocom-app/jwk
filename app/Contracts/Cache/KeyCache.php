<?php

namespace Neocom\JWK\Contracts\Cache;

interface KeyCache
{
    /**
     * Get an individual key / key set from the cache
     *
     * @param string $group  The cache group
     * @param string $key  The cache key
     * @return mixed The cached data
     */
    public function get(string $group, string $key);

    /**
     * Store an individual key or key set in the cache
     *
     * @param string $group  The cache group
     * @param string $key  The cache key
     * @param mixed $data  The data to store
     * @param \DateTimeInterface|\DateInterval|int  $ttl
     * @return bool
     */
    public function store(string $group, string $key, $data, $ttl = null);

    /**
     * Whether a key or key set is stored in the cache
     *
     * @param string $group  The cache group
     * @param string $key  The cache key
     * @return bool
     */
    public function has(string $group, string $key);

    /**
     * Remove an entry from the cache
     *
     * @param string $group  The cache group
     * @param string $key  The cache key
     * @return bool
     */
    public function delete(string $group, string $key = '');

    /**
     * Remove all entries in a group from the cache
     *
     * @param string $group  The cache group
     * @return bool
     */
    public function purge(string $group);

    /**
     * Remove all entries from the cache
     *
     * @return bool
     */
    public function flush();

    /**
     * Get the list of currently stored cached entries
     *
     * @param string $group  Key Cache Group
     * @return string[]  List of cache entries
     */
    public function keys(string $group = '');
}
