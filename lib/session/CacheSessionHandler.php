<?php
/**
 * Session handler for using Stud.IP Cache as session storage
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      André Noack <noack@data-quest.de>
 */
namespace Studip\Session;

use SessionHandlerInterface;
use SessionIdInterface;
use SessionUpdateTimestampHandlerInterface;
use Studip\Cache\Cache;
use Studip\Cache\Factory;

class CacheSessionHandler implements
    SessionHandlerInterface,
    SessionIdInterface,
    SessionUpdateTimestampHandlerInterface
{

    private const CACHE_KEY_PREFIX = 'session_data';

    private int $session_lifetime = 7200;

    private Cache $cache;

    public function __construct(?int $session_lifetime = null)
    {
        if ($session_lifetime) {
            $this->session_lifetime = $session_lifetime;
        }
        $this->cache = Factory::getCache();
    }

    public function close(): bool
    {
        return true;
    }

    public function destroy(string $id): bool
    {
        $cache_key = self::CACHE_KEY_PREFIX . '/' . $id;
        $this->cache->expire($cache_key);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return false;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $cache_key = self::CACHE_KEY_PREFIX . '/' . $id;
        return $this->cache->read($cache_key) ?: '';
    }

    public function write(string $id, string $data): bool
    {
        $cache_key = self::CACHE_KEY_PREFIX . '/' . $id;
        return $this->cache->write($cache_key, $data, $this->session_lifetime);
    }

    public function create_sid(): string
    {
        return session_create_id();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }

    public function validateId(string $id): bool
    {
        return (bool) $this->read($id);
    }
}
