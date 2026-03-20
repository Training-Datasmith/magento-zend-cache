<?php

declare (strict_types=1);
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Backend
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @see Zend_Cache_Backend_ExtendedInterface
 */
#require_once 'Zend/Cache/Backend/ExtendedInterface.php';
/**
 * @see Zend_Cache_Backend
 */
#require_once 'Zend/Cache/Backend.php';
/**
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Backend
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_cache_backend_two_Levels extends Zend_Cache_Backend implements Zend_cache_backend_extended_Interface
{
    /**
     * Available options
     *
     * =====> (string) slow_backend :
     * - Slow backend name
     * - Must implement the Zend_Cache_Backend_ExtendedInterface
     * - Should provide a big storage
     *
     * =====> (string) fast_backend :
     * - Flow backend name
     * - Must implement the Zend_Cache_Backend_ExtendedInterface
     * - Must be much faster than slow_backend
     *
     * =====> (array) slow_backend_options :
     * - Slow backend options (see corresponding backend)
     *
     * =====> (array) fast_backend_options :
     * - Fast backend options (see corresponding backend)
     *
     * =====> (int) stats_update_factor :
     * - Disable / Tune the computation of the fast backend filling percentage
     * - When saving a record into cache :
     *     1               => systematic computation of the fast backend filling percentage
     *     x (integer) > 1 => computation of the fast backend filling percentage randomly 1 times on x cache write
     *
     * =====> (boolean) slow_backend_custom_naming :
     * =====> (boolean) fast_backend_custom_naming :
     * =====> (boolean) slow_backend_autoload :
     * =====> (boolean) fast_backend_autoload :
     * - See Zend_Cache::factory() method
     *
     * =====> (boolean) auto_fill_fast_cache
     * - If true, automatically fill the fast cache when a cache record was not found in fast cache, but did
     *   exist in slow cache. This can be usefull when a non-persistent cache like APC or Memcached got
     *   purged for whatever reason.
     *
     * =====> (boolean) auto_refresh_fast_cache
     * - If true, auto refresh the fast cache when a cache record is hit
     *
     * @var array available options
     */
    protected $_options = ['slow_backend' => 'File', 'fast_backend' => 'Apc', 'slow_backend_options' => [], 'fast_backend_options' => [], 'stats_update_factor' => 10, 'slow_backend_custom_naming' => false, 'fast_backend_custom_naming' => false, 'slow_backend_autoload' => false, 'fast_backend_autoload' => false, 'auto_fill_fast_cache' => true, 'auto_refresh_fast_cache' => true];
    /**
     * Slow Backend
     *
     * @var Zend_Cache_Backend_ExtendedInterface
     */
    protected $_slow_backend;
    /**
     * Fast Backend
     *
     * @var Zend_Cache_Backend_ExtendedInterface
     */
    protected $_fast_backend;
    /**
     * Cache for the fast backend filling percentage
     *
     * @var int
     */
    protected $_fast_backend_filling_percentage;
    /**
     * Constructor
     *
     * @param  array $options Associative array of options
     * @throws Zend_Cache_Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_options['slow_backend'] === null) {
            Zend_Cache::throw_exception('slow_backend option has to set');
        } elseif ($this->_options['slow_backend'] instanceof Zend_cache_backend_extended_Interface) {
            $this->_slow_backend = $this->_options['slow_backend'];
        } else {
            $this->_slow_backend = Zend_Cache::_make_backend($this->_options['slow_backend'], $this->_options['slow_backend_options'], $this->_options['slow_backend_custom_naming'], $this->_options['slow_backend_autoload']);
            if (!in_array('Zend_Cache_Backend_ExtendedInterface', class_implements($this->_slow_backend))) {
                Zend_Cache::throw_exception('slow_backend must implement the Zend_Cache_Backend_ExtendedInterface interface');
            }
        }
        if ($this->_options['fast_backend'] === null) {
            Zend_Cache::throw_exception('fast_backend option has to set');
        } elseif ($this->_options['fast_backend'] instanceof Zend_cache_backend_extended_Interface) {
            $this->_fast_backend = $this->_options['fast_backend'];
        } else {
            $this->_fast_backend = Zend_Cache::_make_backend($this->_options['fast_backend'], $this->_options['fast_backend_options'], $this->_options['fast_backend_custom_naming'], $this->_options['fast_backend_autoload']);
            if (!in_array('Zend_Cache_Backend_ExtendedInterface', class_implements($this->_fast_backend))) {
                Zend_Cache::throw_exception('fast_backend must implement the Zend_Cache_Backend_ExtendedInterface interface');
            }
        }
        $this->_slow_backend->set_directives($this->_directives);
        $this->_fast_backend->set_directives($this->_directives);
    }
    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param  string $id cache id
     * @return mixed|false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    public function test($id)
    {
        $fast_test = $this->_fast_backend->test($id);
        if ($fast_test) {
            return $fast_test;
        }
        return $this->_slow_backend->test($id);
    }
    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data            Datas to cache
     * @param  string $id              Cache id
     * @param  array $tags             Array of strings, the cache record will be tagged by each string entry
     * @param  int   $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @param  int   $priority         integer between 0 (very low priority) and 10 (maximum priority) used by some particular backends
     * @return boolean true if no problem
     */
    public function save($data, $id, $tags = [], $specific_lifetime = false, $priority = 8): bool
    {
        $usage = $this->_get_fast_filling_percentage('saving');
        $bool_fast = true;
        $lifetime = $this->get_lifetime($specific_lifetime);
        $prepared_data = $this->_prepare_data($data, $lifetime, $priority);
        if ($priority > 0 && 10 * $priority >= $usage) {
            $fast_lifetime = $this->_get_fast_lifetime($lifetime, $priority);
            $bool_fast = $this->_fast_backend->save($prepared_data, $id, [], $fast_lifetime);
            $bool_slow = $this->_slow_backend->save($prepared_data, $id, $tags, $lifetime);
        } else {
            $bool_slow = $this->_slow_backend->save($prepared_data, $id, $tags, $lifetime);
            if ($bool_slow === true) {
                $bool_fast = $this->_fast_backend->remove($id);
                if (!$bool_fast && !$this->_fast_backend->test($id)) {
                    // some backends return false on remove() even if the key never existed. (and it won't if fast is full)
                    // all we care about is that the key doesn't exist now
                    $bool_fast = true;
                }
            }
        }
        return $bool_fast && $bool_slow;
    }
    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * Note : return value is always "string" (unserialization is done by the core not by the backend)
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return string|false cached datas
     */
    public function load($id, $do_not_test_cache_validity = false)
    {
        $result_fast = $this->_fast_backend->load($id, $do_not_test_cache_validity);
        if ($result_fast === false) {
            $result_slow = $this->_slow_backend->load($id, $do_not_test_cache_validity);
            if ($result_slow === false) {
                // there is no cache at all for this id
                return false;
            }
        }
        $array = $result_fast !== false ? unserialize($result_fast, ['allowed_classes' => false]) : unserialize($result_slow, ['allowed_classes' => false]);
        //In case no cache entry was found in the FastCache and auto-filling is enabled, copy data to FastCache
        if ($result_fast === false && $this->_options['auto_fill_fast_cache']) {
            $prepared_data = $this->_prepare_data($array['data'], $array['lifetime'], $array['priority']);
            $this->_fast_backend->save($prepared_data, $id, [], $array['lifetime']);
        } elseif ($this->_options['auto_refresh_fast_cache']) {
            if ($array['priority'] == 10) {
                // no need to refresh the fast cache with priority = 10
                return $array['data'];
            }
            $new_fast_lifetime = $this->_get_fast_lifetime($array['lifetime'], $array['priority'], time() - $array['expire']);
            // we have the time to refresh the fast cache
            $usage = $this->_get_fast_filling_percentage('loading');
            if ($array['priority'] > 0 && 10 * $array['priority'] >= $usage) {
                // we can refresh the fast cache
                $prepared_data = $this->_prepare_data($array['data'], $array['lifetime'], $array['priority']);
                $this->_fast_backend->save($prepared_data, $id, [], $new_fast_lifetime);
            }
        }
        return $array['data'];
    }
    /**
     * Remove a cache record
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    public function remove($id): bool
    {
        $bool_fast = $this->_fast_backend->remove($id);
        $bool_slow = $this->_slow_backend->remove($id);
        return $bool_fast && $bool_slow;
    }
    /**
     * Clean some cache records
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @throws Zend_Cache_Exception
     * @return boolean true if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = [])
    {
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                $bool_fast = $this->_fast_backend->clean(Zend_Cache::CLEANING_MODE_ALL);
                $bool_slow = $this->_slow_backend->clean(Zend_Cache::CLEANING_MODE_ALL);
                return $bool_fast && $bool_slow;
            case Zend_Cache::CLEANING_MODE_OLD:
                return $this->_slow_backend->clean(Zend_Cache::CLEANING_MODE_OLD);
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                $ids = $this->_slow_backend->get_ids_matching_tags($tags);
                $res = true;
                foreach ($ids as $id) {
                    $bool = $this->remove($id);
                    $res = $res && $bool;
                }
                return $res;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                $ids = $this->_slow_backend->get_ids_not_matching_tags($tags);
                $res = true;
                foreach ($ids as $id) {
                    $bool = $this->remove($id);
                    $res = $res && $bool;
                }
                return $res;
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                $ids = $this->_slow_backend->get_ids_matching_any_tags($tags);
                $res = true;
                foreach ($ids as $id) {
                    $bool = $this->remove($id);
                    $res = $res && $bool;
                }
                return $res;
            default:
                Zend_Cache::throw_exception('Invalid mode for clean() method');
                break;
        }
    }
    /**
     * Return an array of stored cache ids
     *
     * @return array array of stored cache ids (string)
     */
    public function get_ids()
    {
        return $this->_slow_backend->get_ids();
    }
    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function get_tags()
    {
        return $this->_slow_backend->get_tags();
    }
    /**
     * Return an array of stored cache ids which match given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of matching cache ids (string)
     */
    public function get_ids_matching_tags($tags = [])
    {
        return $this->_slow_backend->get_ids_matching_tags($tags);
    }
    /**
     * Return an array of stored cache ids which don't match given tags
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @return array array of not matching cache ids (string)
     */
    public function get_ids_not_matching_tags($tags = [])
    {
        return $this->_slow_backend->get_ids_not_matching_tags($tags);
    }
    /**
     * Return an array of stored cache ids which match any given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of any matching cache ids (string)
     */
    public function get_ids_matching_any_tags($tags = [])
    {
        return $this->_slow_backend->get_ids_matching_any_tags($tags);
    }
    /**
     * Return the filling percentage of the backend storage
     *
     * @return int integer between 0 and 100
     */
    public function get_filling_percentage()
    {
        return $this->_slow_backend->get_filling_percentage();
    }
    /**
     * Return an array of metadatas for the given cache id
     *
     * The array must include these keys :
     * - expire : the expire timestamp
     * - tags : a string array of tags
     * - mtime : timestamp of last modification time
     *
     * @param string $id cache id
     * @return array array of metadatas (false if the cache id is not found)
     */
    public function get_metadatas($id)
    {
        return $this->_slow_backend->get_metadatas($id);
    }
    /**
     * Give (if possible) an extra lifetime to the given cache id
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extra_lifetime)
    {
        return $this->_slow_backend->touch($id, $extra_lifetime);
    }
    /**
     * Return an associative array of capabilities (booleans) of the backend
     *
     * The array must include these keys :
     * - automatic_cleaning (is automating cleaning necessary)
     * - tags (are tags supported)
     * - expired_read (is it possible to read expired cache records
     *                 (for doNotTestCacheValidity option for example))
     * - priority does the backend deal with priority when saving
     * - infinite_lifetime (is infinite lifetime can work with this backend)
     * - get_list (is it possible to get the list of cache ids and the complete list of tags)
     *
     * @return array associative of with capabilities
     */
    public function get_capabilities(): array
    {
        $slow_backend_capabilities = $this->_slow_backend->get_capabilities();
        return ['automatic_cleaning' => $slow_backend_capabilities['automatic_cleaning'], 'tags' => $slow_backend_capabilities['tags'], 'expired_read' => $slow_backend_capabilities['expired_read'], 'priority' => $slow_backend_capabilities['priority'], 'infinite_lifetime' => $slow_backend_capabilities['infinite_lifetime'], 'get_list' => $slow_backend_capabilities['get_list']];
    }
    /**
     * Prepare a serialized array to store datas and metadatas informations
     *
     * @param string $data data to store
     * @param int $lifetime original lifetime
     * @param int $priority priority
     * @return string serialize array to store into cache
     */
    private function _prepare_data($data, $lifetime, $priority): string
    {
        $lt = $lifetime;
        if ($lt === null) {
            $lt = 9999999999;
        }
        return serialize(['data' => $data, 'lifetime' => $lifetime, 'expire' => time() + $lt, 'priority' => $priority]);
    }
    /**
     * Compute and return the lifetime for the fast backend
     *
     * @param int $lifetime original lifetime
     * @param int $priority priority
     * @param int $maxLifetime maximum lifetime
     * @return int lifetime for the fast backend
     */
    private function _get_fast_lifetime($lifetime, $priority, $max_lifetime = null)
    {
        if ($lifetime <= 0) {
            // if no lifetime, we have an infinite lifetime
            // we need to use arbitrary lifetimes
            $fast_lifetime = (int) (2592000 / (11 - $priority));
        } else {
            // prevent computed infinite lifetime (0) by ceil
            $fast_lifetime = (int) ceil($lifetime / (11 - $priority));
        }
        if ($max_lifetime >= 0 && $fast_lifetime > $max_lifetime) {
            return $max_lifetime;
        }
        return $fast_lifetime;
    }
    /**
     * PUBLIC METHOD FOR UNIT TESTING ONLY !
     *
     * Force a cache record to expire
     *
     * @param string $id cache id
     */
    public function ___expire($id)
    {
        $this->_fast_backend->remove($id);
        $this->_slow_backend->___expire($id);
    }
    private function _get_fast_filling_percentage(string $mode)
    {
        if ($mode == 'saving') {
            // mode saving
            if ($this->_fast_backend_filling_percentage === null) {
                $this->_fast_backend_filling_percentage = $this->_fast_backend->get_filling_percentage();
            } else {
                $rand = random_int(1, $this->_options['stats_update_factor']);
                if ($rand == 1) {
                    // we force a refresh
                    $this->_fast_backend_filling_percentage = $this->_fast_backend->get_filling_percentage();
                }
            }
        } else if ($this->_fast_backend_filling_percentage === null) {
            $this->_fast_backend_filling_percentage = $this->_fast_backend->get_filling_percentage();
        }
        return $this->_fast_backend_filling_percentage;
    }
}