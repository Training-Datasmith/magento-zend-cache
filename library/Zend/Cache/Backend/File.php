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
 * @see Zend_Cache_Backend_Interface
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
class Zend_Cache_Backend_File extends Zend_Cache_Backend implements Zend_cache_backend_extended_Interface
{
    /**
     * Available options
     *
     * =====> (string) cache_dir :
     * - Directory where to put the cache files
     *
     * =====> (boolean) file_locking :
     * - Enable / disable file_locking
     * - Can avoid cache corruption under bad circumstances but it doesn't work on multithread
     * webservers and on NFS filesystems for example
     *
     * =====> (boolean) read_control :
     * - Enable / disable read control
     * - If enabled, a control key is embeded in cache file and this key is compared with the one
     * calculated after the reading.
     *
     * =====> (string) read_control_type :
     * - Type of read control (only if read control is enabled). Available values are :
     *   'md5' for a md5 hash control (best but slowest)
     *   'crc32' for a crc32 hash control (lightly less safe but faster, better choice)
     *   'adler32' for an adler32 hash control (excellent choice too, faster than crc32)
     *   'strlen' for a length only test (fastest)
     *
     * =====> (int) hashed_directory_level :
     * - Hashed directory level
     * - Set the hashed directory structure level. 0 means "no hashed directory
     * structure", 1 means "one level of directory", 2 means "two levels"...
     * This option can speed up the cache only when you have many thousands of
     * cache file. Only specific benchs can help you to choose the perfect value
     * for you. Maybe, 1 or 2 is a good start.
     *
     * =====> (int) hashed_directory_umask :
     * - deprecated
     * - Permissions for hashed directory structure
     *
     * =====> (int) hashed_directory_perm :
     * - Permissions for hashed directory structure
     *
     * =====> (string) file_name_prefix :
     * - prefix for cache files
     * - be really carefull with this option because a too generic value in a system cache dir
     *   (like /tmp) can cause disasters when cleaning the cache
     *
     * =====> (int) cache_file_umask :
     * - deprecated
     * - Permissions for cache files
     *
     * =====> (int) cache_file_perm :
     * - Permissions for cache files
     *
     * =====> (int) metatadatas_array_max_size :
     * - max size for the metadatas array (don't change this value unless you
     *   know what you are doing)
     *
     * @var array available options
     */
    protected $_options = ['cache_dir' => null, 'file_locking' => true, 'read_control' => true, 'read_control_type' => 'crc32', 'hashed_directory_level' => 0, 'hashed_directory_perm' => 0700, 'file_name_prefix' => 'zend_cache', 'cache_file_perm' => 0600, 'metadatas_array_max_size' => 100];
    /**
     * Array of metadatas (each item is an associative array)
     *
     * @var array
     */
    protected $_metadatas_array = [];
    /**
     * Constructor
     *
     * @param  array $options associative array of options
     * @throws Zend_Cache_Exception
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        if ($this->_options['cache_dir'] !== null) {
            // particular case for this option
            $this->set_cache_dir($this->_options['cache_dir']);
        } else {
            $this->set_cache_dir(self::get_tmp_dir() . DIRECTORY_SEPARATOR, false);
        }
        if (isset($this->_options['file_name_prefix'])) {
            // particular case for this option
            if (!preg_match('~^[a-zA-Z0-9_]+$~D', $this->_options['file_name_prefix'])) {
                Zend_Cache::throw_exception('Invalid file_name_prefix : must use only [a-zA-Z0-9_]');
            }
        }
        if ($this->_options['metadatas_array_max_size'] < 10) {
            Zend_Cache::throw_exception('Invalid metadatas_array_max_size, must be > 10');
        }
        if (isset($options['hashed_directory_umask'])) {
            // See #ZF-12047
            trigger_error("'hashed_directory_umask' is deprecated -> please use 'hashed_directory_perm' instead", E_USER_NOTICE);
            if (!isset($options['hashed_directory_perm'])) {
                $options['hashed_directory_perm'] = $options['hashed_directory_umask'];
            }
        }
        if (isset($options['hashed_directory_perm']) && is_string($options['hashed_directory_perm'])) {
            // See #ZF-4422
            $this->_options['hashed_directory_perm'] = octdec($this->_options['hashed_directory_perm']);
        }
        if (isset($options['cache_file_umask'])) {
            // See #ZF-12047
            trigger_error("'cache_file_umask' is deprecated -> please use 'cache_file_perm' instead", E_USER_NOTICE);
            if (!isset($options['cache_file_perm'])) {
                $options['cache_file_perm'] = $options['cache_file_umask'];
            }
        }
        if (isset($options['cache_file_perm']) && is_string($options['cache_file_perm'])) {
            // See #ZF-4422
            $this->_options['cache_file_perm'] = octdec($this->_options['cache_file_perm']);
        }
    }
    /**
     * Set the cache_dir (particular case of setOption() method)
     *
     * @param  string  $value
     * @param  boolean $trailingSeparator If true, add a trailing separator is necessary
     * @throws Zend_Cache_Exception
     * @return void
     */
    public function set_cache_dir($value, $trailing_separator = true)
    {
        if (!is_dir($value)) {
            Zend_Cache::throw_exception(sprintf('cache_dir "%s" must be a directory', $value));
        }
        if (!is_writable($value)) {
            Zend_Cache::throw_exception(sprintf('cache_dir "%s" is not writable', $value));
        }
        if ($trailing_separator) {
            // add a trailing DIRECTORY_SEPARATOR if necessary
            $value = rtrim(realpath($value), '\/') . DIRECTORY_SEPARATOR;
        }
        $this->_options['cache_dir'] = $value;
    }
    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param string $id cache id
     * @param boolean $doNotTestCacheValidity if set to true, the cache validity won't be tested
     * @return string|false cached datas
     */
    public function load($id, $do_not_test_cache_validity = false)
    {
        if (!$this->_test($id, $do_not_test_cache_validity)) {
            // The cache is not hit !
            return false;
        }
        $metadatas = $this->_get_metadatas($id);
        $file = $this->_file($id);
        $data = $this->_file_get_contents($file);
        if ($this->_options['read_control']) {
            $hash_data = $this->_hash($data, $this->_options['read_control_type']);
            $hash_control = $metadatas['hash'];
            if ($hash_data != $hash_control) {
                // Problem detected by the read control !
                $this->_log('Zend_Cache_Backend_File::load() / read_control : stored hash and computed hash do not match');
                $this->remove($id);
                return false;
            }
        }
        return $data;
    }
    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param string $id cache id
     * @return mixed false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    public function test($id)
    {
        clearstatcache();
        return $this->_test($id, false);
    }
    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string      $data             Datas to cache
     * @param  string      $id               Cache id
     * @param  array       $tags             Array of strings, the cache record will be tagged by each string entry
     * @param  boolean|int $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @return boolean true if no problem
     */
    public function save($data, $id, $tags = [], $specific_lifetime = false)
    {
        clearstatcache();
        $file = $this->_file($id);
        $path = $this->_path($id);
        if ($this->_options['hashed_directory_level'] > 0) {
            if (!is_writable($path)) {
                // maybe, we just have to build the directory structure
                $this->_recursive_mkdir_and_chmod($id);
                return false;
            }
        }
        if ($this->_options['read_control']) {
            $hash = $this->_hash($data, $this->_options['read_control_type']);
        } else {
            $hash = '';
        }
        $metadatas = ['hash' => $hash, 'mtime' => time(), 'expire' => $this->_expire_time($this->get_lifetime($specific_lifetime)), 'tags' => $tags];
        $res = $this->_set_metadatas($id, $metadatas);
        if (!$res) {
            $this->_log('Zend_Cache_Backend_File::save() / error on saving metadata');
            return false;
        }
        return $this->_file_put_contents($file, $data);
    }
    /**
     * Remove a cache record
     *
     * @param  string $id cache id
     * @return boolean true if no problem
     */
    public function remove($id): bool
    {
        $file = $this->_file($id);
        $bool_remove = $this->_remove($file);
        $bool_metadata = $this->_del_metadatas($id);
        return $bool_metadata && $bool_remove;
    }
    /**
     * Clean some cache records
     *
     * Available modes are :
     *
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param string $mode clean mode
     * @param array $tags array of tags
     * @return boolean true if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = [])
    {
        // We use this protected method to hide the recursive stuff
        clearstatcache();
        return $this->_clean($this->_options['cache_dir'], $mode, $tags);
    }
    /**
     * Return an array of stored cache ids
     *
     * @return array array of stored cache ids (string)
     */
    public function get_ids()
    {
        return $this->_get($this->_options['cache_dir'], 'ids', []);
    }
    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function get_tags()
    {
        return $this->_get($this->_options['cache_dir'], 'tags', []);
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
        return $this->_get($this->_options['cache_dir'], 'matching', $tags);
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
        return $this->_get($this->_options['cache_dir'], 'notMatching', $tags);
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
        return $this->_get($this->_options['cache_dir'], 'matchingAny', $tags);
    }
    /**
     * Return the filling percentage of the backend storage
     *
     * @throws Zend_Cache_Exception
     * @return int integer between 0 and 100
     */
    public function get_filling_percentage()
    {
        $free = disk_free_space($this->_options['cache_dir']);
        $total = disk_total_space($this->_options['cache_dir']);
        if ($total == 0) {
            Zend_Cache::throw_exception('can\'t get disk_total_space');
        } else {
            if ($free >= $total) {
                return 100;
            }
            return (int) (100.0 * ($total - $free) / $total);
        }
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
        $metadatas = $this->_get_metadatas($id);
        if (!$metadatas) {
            return false;
        }
        if (time() > $metadatas['expire']) {
            return false;
        }
        return ['expire' => $metadatas['expire'], 'tags' => $metadatas['tags'], 'mtime' => $metadatas['mtime']];
    }
    /**
     * Give (if possible) an extra lifetime to the given cache id
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extra_lifetime): bool
    {
        $metadatas = $this->_get_metadatas($id);
        if (!$metadatas) {
            return false;
        }
        if (time() > $metadatas['expire']) {
            return false;
        }
        $new_metadatas = ['hash' => $metadatas['hash'], 'mtime' => time(), 'expire' => $metadatas['expire'] + $extra_lifetime, 'tags' => $metadatas['tags']];
        $res = $this->_set_metadatas($id, $new_metadatas);
        if (!$res) {
            return false;
        }
        return true;
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
        return ['automatic_cleaning' => true, 'tags' => true, 'expired_read' => true, 'priority' => false, 'infinite_lifetime' => true, 'get_list' => true];
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
        $metadatas = $this->_get_metadatas($id);
        if ($metadatas) {
            $metadatas['expire'] = 1;
            $this->_set_metadatas($id, $metadatas);
        }
    }
    /**
     * Get a metadatas record
     *
     * @param  string $id  Cache id
     * @return array|false Associative array of metadatas
     */
    protected function _get_metadatas($id)
    {
        if (isset($this->_metadatas_array[$id])) {
            return $this->_metadatas_array[$id];
        }
        $metadatas = $this->_load_metadatas($id);
        if (!$metadatas) {
            return false;
        }
        $this->_set_metadatas($id, $metadatas, false);
        return $metadatas;
    }
    /**
     * Set a metadatas record
     *
     * @param  string $id        Cache id
     * @param  array  $metadatas Associative array of metadatas
     * @param  boolean $save     optional pass false to disable saving to file
     * @return boolean True if no problem
     */
    protected function _set_metadatas($id, $metadatas, $save = true): bool
    {
        if (count($this->_metadatas_array) >= $this->_options['metadatas_array_max_size']) {
            $n = (int) ($this->_options['metadatas_array_max_size'] / 10);
            $this->_metadatas_array = array_slice($this->_metadatas_array, $n);
        }
        if ($save) {
            $result = $this->_save_metadatas($id, $metadatas);
            if (!$result) {
                return false;
            }
        }
        $this->_metadatas_array[$id] = $metadatas;
        return true;
    }
    /**
     * Drop a metadata record
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    protected function _del_metadatas($id)
    {
        if (isset($this->_metadatas_array[$id])) {
            unset($this->_metadatas_array[$id]);
        }
        $file = $this->_metadatas_file($id);
        return $this->_remove($file);
    }
    /**
     * Clear the metadatas array
     *
     * @return void
     */
    protected function _clean_metadatas()
    {
        $this->_metadatas_array = [];
    }
    /**
     * Load metadatas from disk
     *
     * @param  string $id Cache id
     * @return array|false Metadatas associative array
     */
    protected function _load_metadatas($id)
    {
        $file = $this->_metadatas_file($id);
        $result = $this->_file_get_contents($file);
        if (!$result) {
            return false;
        }
        return @unserialize($result, ['allowed_classes' => false]);
    }
    /**
     * Save metadatas to disk
     *
     * @param  string $id        Cache id
     * @param  array  $metadatas Associative array
     * @return boolean True if no problem
     */
    protected function _save_metadatas($id, $metadatas): bool
    {
        $file = $this->_metadatas_file($id);
        $result = $this->_file_put_contents($file, serialize($metadatas));
        if (!$result) {
            return false;
        }
        return true;
    }
    /**
     * Make and return a file name (with path) for metadatas
     *
     * @param  string $id Cache id
     * @return string Metadatas file name (with path)
     */
    protected function _metadatas_file(string $id): string
    {
        $path = $this->_path($id);
        $file_name = $this->_id_to_file_name('internal-metadatas---' . $id);
        return $path . $file_name;
    }
    /**
     * Check if the given filename is a metadatas one
     *
     * @param  string $fileName File name
     * @return boolean True if it's a metadatas one
     */
    protected function _is_metadatas_file($file_name): bool
    {
        $id = $this->_file_name_to_id($file_name);
        if (substr($id, 0, 21) == 'internal-metadatas---') {
            return true;
        }
        return false;
    }
    /**
     * Remove a file
     *
     * If we can't remove the file (because of locks or any problem), we will touch
     * the file to invalidate it
     *
     * @param  string $file Complete file path
     * @return boolean True if ok
     */
    protected function _remove($file): bool
    {
        if (!is_file($file)) {
            return false;
        }
        if (!@unlink($file)) {
            # we can't remove the file (because of locks or any problem)
            $this->_log("Zend_Cache_Backend_File::_remove() : we can't remove {$file}");
            return false;
        }
        return true;
    }
    /**
     * Clean some cache records (protected method used for recursive stuff)
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
     * @param  string $dir  Directory to clean
     * @param  string $mode Clean mode
     * @param  array  $tags Array of tags
     * @throws Zend_Cache_Exception
     * @return boolean True if no problem
     */
    protected function _clean(string $dir, $mode = Zend_Cache::CLEANING_MODE_ALL, $tags = [])
    {
        if (!is_dir($dir)) {
            return false;
        }
        $result = true;
        $prefix = $this->_options['file_name_prefix'];
        $glob = @glob($dir . $prefix . '--*');
        if ($glob === false) {
            // On some systems it is impossible to distinguish between empty match and an error.
            return true;
        }
        $metadata_files = [];
        foreach ($glob as $file) {
            if (is_file($file)) {
                $file_name = basename($file);
                if ($this->_is_metadatas_file($file_name)) {
                    // In CLEANING_MODE_ALL, we drop anything, even remainings old metadatas files.
                    // To do that, we need to save the list of the metadata files first.
                    if ($mode == Zend_Cache::CLEANING_MODE_ALL) {
                        $metadata_files[] = $file;
                    }
                    continue;
                }
                $id = $this->_file_name_to_id($file_name);
                $metadatas = $this->_get_metadatas($id);
                if ($metadatas === false) {
                    $metadatas = ['expire' => 1, 'tags' => []];
                }
                switch ($mode) {
                    case Zend_Cache::CLEANING_MODE_ALL:
                        $result = $result && $this->remove($id);
                        break;
                    case Zend_Cache::CLEANING_MODE_OLD:
                        if (time() > $metadatas['expire']) {
                            $result = $this->remove($id) && $result;
                        }
                        break;
                    case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                        $matching = true;
                        foreach ($tags as $tag) {
                            if (!in_array($tag, $metadatas['tags'])) {
                                $matching = false;
                                break;
                            }
                        }
                        if ($matching) {
                            $result = $this->remove($id) && $result;
                        }
                        break;
                    case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                        $matching = false;
                        foreach ($tags as $tag) {
                            if (in_array($tag, $metadatas['tags'])) {
                                $matching = true;
                                break;
                            }
                        }
                        if (!$matching) {
                            $result = $this->remove($id) && $result;
                        }
                        break;
                    case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                        $matching = false;
                        foreach ($tags as $tag) {
                            if (in_array($tag, $metadatas['tags'])) {
                                $matching = true;
                                break;
                            }
                        }
                        if ($matching) {
                            $result = $this->remove($id) && $result;
                        }
                        break;
                    default:
                        Zend_Cache::throw_exception('Invalid mode for clean() method');
                        break;
                }
            }
            if (is_dir($file) and $this->_options['hashed_directory_level'] > 0) {
                // Recursive call
                $result = $this->_clean($file . DIRECTORY_SEPARATOR, $mode, $tags) && $result;
                if ($mode == Zend_Cache::CLEANING_MODE_ALL) {
                    // we try to drop the structure too
                    @rmdir($file);
                }
            }
        }
        // cycle through metadataFiles and delete orphaned ones
        foreach ($metadata_files as $file) {
            if (file_exists($file)) {
                $result = $this->_remove($file) && $result;
            }
        }
        return $result;
    }
    protected function _get(string $dir, $mode, $tags = [])
    {
        if (!is_dir($dir)) {
            return false;
        }
        $result = [];
        $prefix = $this->_options['file_name_prefix'];
        $glob = @glob($dir . $prefix . '--*');
        if ($glob === false) {
            // On some systems it is impossible to distinguish between empty match and an error.
            return [];
        }
        foreach ($glob as $file) {
            if (is_file($file)) {
                $file_name = basename($file);
                $id = $this->_file_name_to_id($file_name);
                $metadatas = $this->_get_metadatas($id);
                if ($metadatas === false) {
                    continue;
                }
                if (time() > $metadatas['expire']) {
                    continue;
                }
                switch ($mode) {
                    case 'ids':
                        $result[] = $id;
                        break;
                    case 'tags':
                        $result = array_unique(array_merge($result, $metadatas['tags']));
                        break;
                    case 'matching':
                        $matching = true;
                        foreach ($tags as $tag) {
                            if (!in_array($tag, $metadatas['tags'])) {
                                $matching = false;
                                break;
                            }
                        }
                        if ($matching) {
                            $result[] = $id;
                        }
                        break;
                    case 'notMatching':
                        $matching = false;
                        foreach ($tags as $tag) {
                            if (in_array($tag, $metadatas['tags'])) {
                                $matching = true;
                                break;
                            }
                        }
                        if (!$matching) {
                            $result[] = $id;
                        }
                        break;
                    case 'matchingAny':
                        $matching = false;
                        foreach ($tags as $tag) {
                            if (in_array($tag, $metadatas['tags'])) {
                                $matching = true;
                                break;
                            }
                        }
                        if ($matching) {
                            $result[] = $id;
                        }
                        break;
                    default:
                        Zend_Cache::throw_exception('Invalid mode for _get() method');
                        break;
                }
            }
            if (is_dir($file) and $this->_options['hashed_directory_level'] > 0) {
                // Recursive call
                $recursive_rs = $this->_get($file . DIRECTORY_SEPARATOR, $mode, $tags);
                if ($recursive_rs === false) {
                    $this->_log('Zend_Cache_Backend_File::_get() / recursive call : can\'t list entries of "' . $file . '"');
                } else {
                    $result = array_unique(array_merge($result, $recursive_rs));
                }
            }
        }
        return array_unique($result);
    }
    /**
     * Compute & return the expire time
     *
     * @param  int $lifetime
     * @return int expire time (unix timestamp)
     */
    protected function _expire_time($lifetime)
    {
        if ($lifetime === null) {
            return 9999999999;
        }
        return time() + $lifetime;
    }
    /**
     * Make a control key with the string containing datas
     *
     * @param  string $data        Data
     * @param  string $controlType Type of control 'md5', 'crc32' or 'strlen'
     * @throws Zend_Cache_Exception
     * @return string Control key
     */
    protected function _hash($data, $control_type)
    {
        switch ($control_type) {
            case 'md5':
                return md5($data);
            case 'crc32':
                return crc32($data);
            case 'strlen':
                return strlen($data);
            case 'adler32':
                return hash('adler32', $data);
            default:
                Zend_Cache::throw_exception("Incorrect hash function : {$control_type}");
        }
    }
    /**
     * Transform a cache id into a file name and return it
     *
     * @param  string $id Cache id
     * @return string File name
     */
    protected function _id_to_file_name(string $id): string
    {
        $prefix = $this->_options['file_name_prefix'];
        return $prefix . '---' . $id;
    }
    /**
     * Make and return a file name (with path)
     *
     * @param  string $id Cache id
     * @return string File name (with path)
     */
    protected function _file($id): string
    {
        $path = $this->_path($id);
        $file_name = $this->_id_to_file_name($id);
        return $path . $file_name;
    }
    /**
     * Return the complete directory path of a filename (including hashedDirectoryStructure)
     *
     * @param  string $id Cache id
     * @param  boolean $parts if true, returns array of directory parts instead of single string
     * @return string Complete directory path
     */
    protected function _path($id, $parts = false)
    {
        $parts_array = [];
        $root = $this->_options['cache_dir'];
        $prefix = $this->_options['file_name_prefix'];
        if ($this->_options['hashed_directory_level'] > 0) {
            $hash = hash('adler32', $id);
            for ($i = 0; $i < $this->_options['hashed_directory_level']; $i++) {
                $root = $root . $prefix . '--' . substr($hash, 0, $i + 1) . DIRECTORY_SEPARATOR;
                $parts_array[] = $root;
            }
        }
        if ($parts) {
            return $parts_array;
        }
        return $root;
    }
    /**
     * Make the directory strucuture for the given id
     *
     * @param string $id cache id
     * @return boolean true
     */
    protected function _recursive_mkdir_and_chmod($id): bool
    {
        if ($this->_options['hashed_directory_level'] <= 0) {
            return true;
        }
        $parts_array = $this->_path($id, true);
        foreach ($parts_array as $part) {
            if (!is_dir($part)) {
                @mkdir($part, $this->_options['hashed_directory_perm']);
                @chmod($part, $this->_options['hashed_directory_perm']);
                // see #ZF-320 (this line is required in some configurations)
            }
        }
        return true;
    }
    /**
     * Test if the given cache id is available (and still valid as a cache record)
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return boolean|mixed false (a cache is not available) or "last modified" timestamp (int) of the available cache record
     */
    protected function _test($id, $do_not_test_cache_validity)
    {
        $metadatas = $this->_get_metadatas($id);
        if (!$metadatas) {
            return false;
        }
        if ($do_not_test_cache_validity || time() <= $metadatas['expire']) {
            return $metadatas['mtime'];
        }
        return false;
    }
    /**
     * Return the file content of the given file
     *
     * @param  string $file File complete path
     * @return string File content (or false if problem)
     */
    protected function _file_get_contents($file)
    {
        $result = false;
        if (!is_file($file)) {
            return false;
        }
        $f = @fopen($file, 'rb');
        if ($f) {
            if ($this->_options['file_locking']) {
                @flock($f, LOCK_SH);
            }
            $result = stream_get_contents($f);
            if ($this->_options['file_locking']) {
                @flock($f, LOCK_UN);
            }
            @fclose($f);
        }
        return $result;
    }
    /**
     * Put the given string into the given file
     *
     * @param  string $file   File complete path
     * @param  string $string String to put in file
     * @return boolean true if no problem
     */
    protected function _file_put_contents($file, $string)
    {
        $result = false;
        $f = @fopen($file, 'ab+');
        if ($f) {
            if ($this->_options['file_locking']) {
                @flock($f, LOCK_EX);
            }
            fseek($f, 0);
            ftruncate($f, 0);
            $tmp = @fwrite($f, $string);
            if (!($tmp === false)) {
                $result = true;
            }
            @fclose($f);
        }
        @chmod($file, $this->_options['cache_file_perm']);
        return $result;
    }
    /**
     * Transform a file name into cache id and return it
     *
     * @param  string $fileName File name
     * @return string Cache id
     */
    protected function _file_name_to_id($file_name)
    {
        $prefix = $this->_options['file_name_prefix'];
        return preg_replace('~^' . $prefix . '---(.*)$~', '$1', $file_name);
    }
}