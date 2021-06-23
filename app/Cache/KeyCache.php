<?php

namespace Neocom\JWK\Cache;

use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Neocom\JWK\Contracts\Cache\KeyCache as KeyCacheContract;
use Neocom\JWK\Utils\RedisUtils;

class KeyCache implements KeyCacheContract
{
    private $cacheKeyPrefix;

    public function __construct()
    {
        $this->cacheKeyPrefix = 'keys';
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $group, string $key)
    {
        $cacheKey = $this->formatKey($group, $key);

        $data = Cache::get($cacheKey, false);

        if ($data === false) {
            return null;
        }

        return $this->decodeData($data);
    }

    /**
     * {@inheritdoc}
     */
    public function store(string $group, string $key, $data, $ttl = null)
    {
        $cacheKey = $this->formatKey($group, $key);

        $data = $this->encodeData($data);

        return Cache::put($cacheKey, $data, $ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $group, string $key)
    {
        $cacheKey = $this->formatKey($group, $key);

        return Cache::has($cacheKey);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $group, string $key = '')
    {
        $cacheKey = $this->formatKey($group, $key);

        return Cache::forget($cacheKey);
    }

    /**
     * {@inheritdoc}
     */
    public function purge(string $group)
    {
        // Get the list of keys for this group
        $keys = $this->keys($group);

        // Loop through each key in the list
        $keys = collect($keys)->map(function($key) {
            return $this->addKeyPrefix($key);
        })->toArray();

        // Loop through each key, removing it from the cache. Bail if any removal fails
        foreach ($keys as $key) {
            if (! Cache::forget($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        return $this->purge('*');
    }

    /**
     * {@inheritdoc}
     */
    public function keys(string $group = '')
    {
        // This only supports redis
        if (Cache::getDefaultDriver() !== 'redis') {
            return null;
        }

        // Configure array of parameters to our key formatter
        $params = [ $group ];
        if ($group !== '*') {
            $params[] = '*';
        }

        // Get our key
        $cacheKey = $this->formatKey(...$params);
        $cacheKey = $this->addRedisPrefix($cacheKey);

        // Use scan to loop through each available key under our prefix
        $keys = RedisUtils::scanForKeys($cacheKey);

        // Strip the redis prefix and our prefix from each returned key
        $keys = collect($keys)->map(function ($key) {
            $key = $this->removeRedisPrefix($key);
            $key = $this->removeKeyPrefix($key);
            return $key;
        })->toArray();

        return $keys;
    }

    /**
     * Encode data for the cache
     *
     * @param mixed $data  Data to encode
     * @return string
     */
    protected function encodeData($data)
    {
        return json_encode($data);
    }

    /**
     * Decode data from the cache
     *
     * @param string $data  Encoded Data
     * @return mixed  Decoded data
     */
    protected function decodeData($data)
    {
        return json_decode($data, true);
    }

    /**
     * Format a key for the cache
     *
     * @param string ...$keys  Key path for the current cache entry
     * @return string
     */
    protected function formatKey(...$keys)
    {
        // Format the key and add the prefix
        $key = $this->formatKeyInternal(...$keys);
        $key = $this->addKeyPrefix($key);

        return $key;
    }

    protected function formatKeyInternal(...$keys)
    {
        // Remove any blank elements from the list of keys
        $keys = array_filter($keys);

        // Create the format string
        $key = Str::of('%s:')
            ->repeat(count($keys))
            ->rtrim(':')
            ->pipe(function ($format) use ($keys) {
                return sprintf($format, ...$keys);
            });

        return (string) $key;
    }

    /**
     * Add the cache prefix to the key
     *
     * @param string $key
     * @return string
     */
    protected function addKeyPrefix($key)
    {
        return $this->addPrefix($key, $this->cacheKeyPrefix.':');
    }

    /**
     * Remove the cache prefix to the key
     *
     * @param string $key
     * @return string
     */
    protected function removeKeyPrefix($key)
    {
        return $this->removePrefix($key, $this->cacheKeyPrefix.':');
    }

    /**
     * Add the default redis prefix to the key
     *
     * @param string $key
     * @return string
     */
    protected function addRedisPrefix($key)
    {
        return $this->addPrefix($key, $this->getRedisPrefix());
    }

    /**
     * Remove the default redis cache prefix to the key
     *
     * @param string $key
     * @return string
     */
    protected function removeRedisPrefix($key)
    {
        return $this->removePrefix($key, $this->getRedisPrefix());
    }

    /**
     * Add a prefix to a string
     *
     * @param string $key
     * @param string $prefix
     * @return string
     */
    protected function addPrefix($key, $prefix)
    {
        // Get the formatted prefix
        $prefix = $this->formatKeyInternal($prefix);

        // Add the prefix and return it
        return $prefix.$key;
    }

    /**
     * Remove a prefix to a string
     *
     * @param string $key
     * @param string $prefix
     * @return string
     */
    protected function removePrefix($key, $prefix)
    {
        // Get the formatted prefix
        $prefix = $this->formatKeyInternal($prefix);

        // Strip the prefix from the key if it exists and return it
        return Str::removePrefix($key, $prefix);
    }

    /**
     * Get the prefix used by redis
     *
     * @return string
     */
    protected function getRedisPrefix()
    {
        // Get the global redis prefixes
        /** @var RedisStore $redisStore */
        $redisStore = Cache::store('redis');
        $redisPrefix = $redisStore->getPrefix();

        return $redisPrefix;
    }
}
