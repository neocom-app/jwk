<?php

namespace Neocom\JWK\Utils;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RedisUtils
{
    /**
     * Scan redis for all keys matching a pattern
     *
     * @param string $pattern  Pattern to match on
     */

    public static function scanForKeys(string $pattern)
    {
        // Get the actual redis connection
        $redisConnection = Redis::connection('cache');

        // Get the prefix
        $prefix = $redisConnection->client()->getOption(\Redis::OPT_PREFIX);

        // Scan function
        $scan = function (string $pattern, $redis) {
            $f = function ($cursor = null, $allResults = []) use ($pattern, $redis, &$f) {

                // If the cursor is at zero, return the results
                if ($cursor === 0) {
                    return $allResults;
                }

                // Call the scan function on the redis connection
                $redisResult = $redis->scan($cursor, ['match' => $pattern]);

                // If the results are false, return the results we've already got
                if ($redisResult === false) {
                    return $allResults;
                }

                // Get the returned cursor and results
                [ $cursor, $results ] = $redisResult;

                // Merge the results together
                $allResults = array_merge($allResults, $results);

                // Call recursively
                return $f($cursor, $allResults);
            };
            return $f();
        };

        // Append the global prefix to the pattern
        $pattern = $prefix.$pattern;

        // Execute the scan and remove any duplicate entries
        $keys = $scan($pattern, $redisConnection);
        $keys = array_unique($keys);

        // Strip the redis prefix from each of the keys since this is automatically added by the redis client
        $keys = array_map(function ($item) use ($prefix) {
            return Str::removePrefix($item, $prefix);
        }, $keys);

        return $keys;
    }
}
