<?php

namespace Studip\Cache;

use Memcached;
use Psr\Cache\CacheItemInterface;

/**
 * Cache implementation using memcached.
 *
 * @author    Marcus Lunzenauer <mlunzena@uos.de>
 * @copyright (c) Authors
 * @license GPL2 or any later version
 * @since   5.0
 */
class MemcachedCache extends Cache
{
    use KeyTrait;

    private Memcached $memcache;

    /**
     * @return string A translateable display name for this cache class.
     */
    public static function getDisplayName(): string
    {
        return _('Memcached');
    }

    public function __construct($servers)
    {
        if (!extension_loaded('memcached')) {
            throw new \Exception('Memcache extension missing.');
        }

        $prefix = \Config::get()->STUDIP_INSTALLATION_ID;
        $this->memcache = new Memcached(md5(json_encode($servers)));

        if (count($this->memcache->getServerList()) === 0) {
            // Set options (see https://www.php.net/manual/en/memcached.addservers.php#118940)
            $this->memcache->setOption(Memcached::OPT_DISTRIBUTION, Memcached::DISTRIBUTION_CONSISTENT);
            $this->memcache->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2);
            $this->memcache->setOption(Memcached::OPT_REMOVE_FAILED_SERVERS, true);
            $this->memcache->setOption(Memcached::OPT_RETRY_TIMEOUT, 1);
            $this->memcache->setOption(Memcached::OPT_PREFIX_KEY, $prefix);

            $status = $this->memcache->addServers(
                array_map(
                    function ($server) {
                        return [$server['hostname'], (int) $server['port']];
                    },
                    $servers
                )
            );

            if (!$status) {
                throw new \Exception('Could not add memcached servers');
            }
        }
    }

    /**
     * Expire item from the cache.
     *
     * Example:
     *
     *   # expires foo
     *   $cache->expire('foo');
     *
     * @param   string $arg a single key.
     * @returns void
     */
    public function expire($arg)
    {
        $key = $this->getCacheKey($arg);
        $this->memcache->delete($key);
    }

    /**
     * Expire all items from the cache.
     */
    public function flush()
    {
        $this->memcache->flush();
    }

    /**
     * Return statistics.
     *
     * @StudipCache::getStats()
     *
     * @return array|array[]
     */
    public function getStats(): array
    {
        $stats = $this->memcache->getStats();

        if ($stats === false) {
            throw new \Exception(
                'Unable to retrieve Memcached stats: ' . $this->memcache->getResultMessage()
            );
        }

        return $stats;
    }

    /**
     * Return the Vue component name and props that handle configuration.
     *
     * @see Cache::getConfig()
     *
     * @return array
     */
    public static function getConfig(): array
    {
        $currentCache = \Config::get()->SYSTEMCACHE;

        // Set default config for this cache
        $currentConfig = [
            'servers' => []
        ];

        // If this cache is set as system cache, use config from global settings.
        if ($currentCache['type'] == __CLASS__) {
            $currentConfig = $currentCache['config'];
        }

        return [
            'component' => 'MemcachedCacheConfig',
            'props' => $currentConfig
        ];
    }

    /**
     * @inheritDoc
     */
    public function getItem(string $key): CacheItemInterface
    {
        $item = new Item($key);
        $value = $this->memcache->get($this->getCacheKey($key));
        if ($this->memcache->getResultCode() !== Memcached::RES_NOTFOUND) {
            // Set the value, even if it is the boolean value false:
            $item->setHit();
            $item->set($value);
        }
        return $item;
    }

    /**
     * @inheritDoc
     */
    public function hasItem(string $key): bool
    {
        return $this->memcache->checkKey($this->getCacheKey($key));
    }

    /**
     * @inheritDoc
     */
    public function save(CacheItemInterface $item): bool
    {
        $expiration = $this->getExpiration($item);
        if ($expiration < 1) {
            // The item would expire immediately.
            return false;
        }

        $real_key = $this->getCacheKey($item->getKey());
        return $this->memcache->set($real_key, $item->get(), $expiration);
    }
}
