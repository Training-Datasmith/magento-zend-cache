# Architecture: magento-zend-cache

## Purpose

Magento's fork of Zend Framework 1's caching library. Provides a two-tier frontend/backend caching system with multiple storage backends (file, Memcached, APC, Redis) and multiple frontend strategies (core, output buffering, per-function, per-page).

## Directory Structure

```
library/Zend/Cache/
  Cache.php            — Factory: Zend_Cache::factory($frontend, $backend, $options)
  Core.php             — Core cache logic: load, save, remove, clean; tags support
  Manager.php          — Named cache manager: register and retrieve multiple caches
  Frontend/
    Output.php         — Buffers PHP output; caches rendered HTML blocks
    Page.php           — Caches entire HTTP responses as static files
    Function.php       — Caches function return values
    Class.php          — Caches method return values on a class
    File.php           — Expires cache based on source file modification time
    Capture.php        — Captures output during a request for later reuse
  Backend/
    Interface.php      — Backend contract: load, save, remove, clean, getIds, getTags
    Extended_Interface.php — Extended backend: getIds, getTags, getIdsMatchingTags, etc.
    Backend.php        — Base backend with common option handling
    File.php           — File-based backend (one file per cache entry)
    Memcached.php      — Memcache extension backend
    Libmemcached.php   — Memcached extension backend
    Apc.php            — APC/APCu backend
    Two_Levels.php     — Stacked backend: fast (APC) + slow (File) tiers
    Static.php         — Writes cache as static .html files; served by web server
    Black_Hole.php     — No-op backend for testing/disabling cache
    Zend_Server.php    — Zend Server shared memory backend
    Win_Cache.php      — WinCache backend (Windows only)
    Xcache.php         — XCache backend
```

## Key Design Decisions

- **Frontend/Backend separation**: Frontends define the caching contract (what to cache); backends define the storage medium — any frontend works with any backend
- **Tag-based invalidation**: All backends (that implement `ExtendedInterface`) support cache tags, enabling bulk invalidation of related cache entries
- **Two-level backend**: `Two_Levels` uses a fast in-process cache (APC) for reads and a durable cache (File/Memcached) for persistence; this is Magento's typical production configuration

## Extension Points

- Implement `Zend_Cache_Backend_Interface` to add a new storage backend (e.g., Redis)
- Implement `Zend_Cache_Frontend_*` convention to add a new caching strategy

## Dependency Flow

```
Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions)
  → Core frontend (load/save/clean)
  → File/Memcached/Apc backend (physical storage)
```
