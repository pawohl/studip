<?php

namespace Studip\Cache;

use Config;
use DBSchemaVersion;
use MessageBox;
use PageLayout;
use ReflectionClass;
use StudipCacheOperation;
use UnexpectedValueException;

/**
 * This factory retrieves the instance of StudipCache configured for use in
 * this Stud.IP installation.
 *
 * @package    studip
 * @subpackage lib
 *
 * @author    Marco Diedrich (mdiedric@uos)
 * @author    Marcus Lunzenauer (mlunzena@uos.de)
 * @copyright 2007 (c) Authors
 * @since     1.6
 * @license   GPL2 or any later version
 */
class Factory
{
    /**
     * the default cache class
     *
     * @var string
     */
    const DEFAULT_CACHE_CLASS = DbCache::class;

    /**
     * singleton instance
     *
     * @var Cache|null
     */
    private static ?Cache $cache = null;


    /**
     * config instance
     *
     * @var Config|null
     */
    private static ?Config $config = null;


    /**
     * Returns the currently used config instance
     *
     * @return Config        an instance of class Config used by this factory to
     *                       determine the class of the actual implementation of
     *                       the StudipCache interface; if no config was set, it
     *                       returns the instance returned by Config#getInstance
     * @see Config
     */
    public static function getConfig()
    {
        return self::$config ?? Config::getInstance();
    }


    /**
     * @param Config $config an instance of class Config which will be used to
     *                       determine the class of the implementation of interface
     *                       StudipCache
     */
    public static function setConfig(Config $config)
    {
        self::$config = $config;
        self::$cache = NULL;
    }

    /**
     * Resets the configuration and voids the cache instance.
     */
    public static function unconfigure()
    {
        self::$cache = NULL;
    }

    /**
     * Returns a cache instance.
     *
     * @param bool $apply_proxied_operations Whether or not to apply any
     *                                       proxied (disable this in tests!)
     * @return Cache the cache instance
     */
    public static function getCache(bool $apply_proxied_operations = true): Cache
    {
        if (self::$cache === null) {
            $proxied = !$GLOBALS['CACHING_ENABLE']
                && isset($GLOBALS['GLOBAL_CACHING_ENABLE'])
                && $GLOBALS['GLOBAL_CACHING_ENABLE'];

            self::$cache = self::loadSystemCache();

            if ($proxied) {
                // If proxy should be used, inject it. Otherwise apply pending
                // operations, if any.
                self::$cache = new Proxy(self::$cache);
            } elseif ($GLOBALS['CACHING_ENABLE'] && $apply_proxied_operations) {
                // Even if the above condition will try to eliminate most
                // failures, the following operation still needs to be wrapped
                // in a try/catch block. Otherwise there are no means to
                // execute migration 166 which creates the neccessary tables
                // for said operation.
                try {
                    StudipCacheOperation::apply(self::$cache);
                } catch (\Exception $e) {
                }
            }
        }

        return self::$cache;
    }

    /**
     * Loads the system's configured cache.
     *
     * @param bool $enforce_configured_cache Define whether the cache should
     *                                       be loaded regardless of global
     *                                       activation
     */
    public static function loadSystemCache(bool $enforce_configured_cache = false): Cache
    {
        if (!$GLOBALS['CACHING_ENABLE'] && !$enforce_configured_cache) {
            return new MemoryCache();
        }

        try {
            $class = self::loadCacheClass();
            $args = self::retrieveConstructorArguments();

            $cache = self::instantiateCache($class, $args);
        } catch (\Exception $e) {
            error_log(__METHOD__ . ': ' . $e->getMessage());

            if ($GLOBALS['CACHE_EXIT_ON_FAILURE']) {
                header('HTTP/1.1 500 Internal Server Error');
                die(_('Momentan ist das Stud.IP System nicht erreichbar, bitte versuchen Sie es später noch einmal.'));
            }

            PageLayout::addBodyElements(MessageBox::error(__METHOD__ . ': ' . $e->getMessage()));

            $class = self::DEFAULT_CACHE_CLASS;
            $cache = new $class();
        }

        return $cache;
    }

    /**
     * Load configured cache class and return its name.
     *
     * @return string  the name of the configured cache class
     */
    public static function loadCacheClass()
    {
        $cacheConfig = self::getConfig()->SYSTEMCACHE;

        $cache_class = $cacheConfig['type'] ?: null;

        # default class
        if ($cache_class === null) {
            $version = new DBSchemaVersion();
            if ($version->get(1) < 224) {
                // db cache is not yet available, use StudipMemoryCache
                return MemoryCache::class;
            }

            return self::DEFAULT_CACHE_CLASS;
        }

        if (!class_exists($cache_class)) {
            throw new UnexpectedValueException("Could not find class: '$cache_class'");
        }

        return $cache_class;
    }

    /**
     * Return an array of arguments required for instantiation of the cache
     * class.
     *
     * @return array  the array of arguments
     */
    public static function retrieveConstructorArguments()
    {
        $cacheConfig = self::getConfig()->SYSTEMCACHE;

        return $cacheConfig ?: [];
    }

    /**
     * Return an instance of a given class using some arguments. Unless the
     * memory cache is instantiated, the cache will be wrapped in a wrapper
     * class that uses a memory cache to reduce accesses to the cache.
     *
     * @param string $class     the name of the class
     * @param array  $arguments an array of arguments to be used by the constructor
     *
     * @return Cache  an instance of the specified class
     * @throws \ReflectionException
     */
    public static function instantiateCache($class, $arguments)
    {
        $reflection_class = new ReflectionClass($class);
        $cache = (is_array($arguments['config']) && count($arguments['config']) > 0)
               ? $reflection_class->newInstanceArgs($arguments['config'])
               : $reflection_class->newInstance();

        if ($class !== MemoryCache::class) {
            return new Wrapper($cache);
        }

        return $cache;
    }
}
