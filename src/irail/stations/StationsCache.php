<?php

namespace irail\stations;

use Cache\Adapter\Apc\ApcCachePool;
use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Psr\Cache\InvalidArgumentException;

class StationsCache
{
    private static null|ArrayCachePool|ApcCachePool $cache = null;
    const APC_PREFIX = "|Irail|Stations|";
    const APC_TTL = 0; // Store forever (or until restart). Cache can be manually cleared too.

    /**
     * Create a cache pool if it does not exist.
     * @return ApcCachePool|AbstractCachePool The cache pool
     */
    public static function createCachePool(): ArrayCachePool|ApcCachePool
    {
        if (self::$cache == null) {
            // Try to use APC when available
            if (extension_loaded('apc')) {
                self::$cache = new ApcCachePool();
            } else {
                // Fall back to array cache
                self::$cache = new ArrayCachePool();
            }
        }

        return self::$cache;
    }

    /**
     * Get an item from the cache.
     *
     * @param String $key The key to search for.
     * @return bool|object|array The cached object if found. If not found, false.
     */
    static function getFromCache(string $key): mixed
    {
        self::createCachePool();
        $key = self::APC_PREFIX . $key;
        if (self::$cache->hasItem($key)) {
            return self::$cache->getItem($key)->get();
        } else {
            return false;
        }
    }

    /**
     * Store an object in cache
     *
     * @param string       $key The key identifier for this object
     * @param object|array $value The object to store
     * @param int          $ttl How long this item should be kept in cache
     * @throws InvalidArgumentException
     */
    static function setCache(string $key, object|array $value, int $ttl = 0): void
    {
        self::createCachePool();
        $key = self::APC_PREFIX . $key;
        $item = self::$cache->getItem($key);

        $item->set($value);
        if ($ttl > 0) {
            $item->expiresAfter($ttl);
        }

        self::$cache->save($item);
    }
}