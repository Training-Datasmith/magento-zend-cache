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
#require_once 'Zend/Cache/Backend/Interface.php';
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
class Zend_Cache_Backend_Static extends Zend_Cache_Backend implements Zend_Cache_Backend_Interface
{
    public const INNER_CACHE_NAME = 'zend_cache_backend_static_tagcache';
    /**
     * Static backend options
     * @var array
     */
    protected $_options = ['public_dir' => null, 'sub_dir' => 'html', 'file_extension' => '.html', 'index_filename' => 'index', 'file_locking' => true, 'cache_file_perm' => 0600, 'cache_directory_perm' => 0700, 'debug_header' => false, 'tag_cache' => null, 'disable_caching' => false];
    /**
     * Cache for handling tags
     * @var Zend_Cache_Core
     */
    protected $_tag_cache;
    /**
     * Tagged items
     * @var array
     */
    protected $_tagged;
    /**
     * Interceptor child method to handle the case where an Inner
     * Cache object is being set since it's not supported by the
     * standard backend interface
     *
     * @param  string $name
     * @param  mixed $value
     */
    public function set_option($name, $value): self
    {
        if ($name == 'tag_cache') {
            $this->set_inner_cache($value);
        } else {
            // See #ZF-12047 and #GH-91
            if ($name == 'cache_file_umask') {
                trigger_error("'cache_file_umask' is deprecated -> please use 'cache_file_perm' instead", E_USER_NOTICE);
                $name = 'cache_file_perm';
            }
            if ($name == 'cache_directory_umask') {
                trigger_error("'cache_directory_umask' is deprecated -> please use 'cache_directory_perm' instead", E_USER_NOTICE);
                $name = 'cache_directory_perm';
            }
            parent::set_option($name, $value);
        }
        return $this;
    }
    /**
     * Retrieve any option via interception of the parent's statically held
     * options including the local option for a tag cache.
     *
     * @param  string $name
     * @return mixed
     */
    public function get_option($name)
    {
        $name = strtolower($name);
        if ($name == 'tag_cache') {
            return $this->get_inner_cache();
        }
        return parent::get_option($name);
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
        if (($id = (string) $id) === '') {
            $id = $this->_detect_id();
        } else {
            $id = $this->_decode_id($id);
        }
        if (!$this->_verify_path($id)) {
            Zend_Cache::throw_exception('Invalid cache id: does not match expected public_dir path');
        }
        if ($do_not_test_cache_validity) {
            $this->_log('Zend_Cache_Backend_Static::load() : $doNotTestCacheValidity=true is unsupported by the Static backend');
        }
        $file_name = basename($id);
        if ($file_name === '') {
            $file_name = $this->_options['index_filename'];
        }
        $path_name = $this->_options['public_dir'] . dirname($id);
        $file = rtrim($path_name, '/') . '/' . $file_name . $this->_options['file_extension'];
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return false;
    }
    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param  string $id cache id
     */
    public function test($id): bool
    {
        $id = $this->_decode_id($id);
        if (!$this->_verify_path($id)) {
            Zend_Cache::throw_exception('Invalid cache id: does not match expected public_dir path');
        }
        $file_name = basename($id);
        if ($file_name === '') {
            $file_name = $this->_options['index_filename'];
        }
        if ($this->_tagged === null && $tagged = $this->get_inner_cache()->load(self::INNER_CACHE_NAME)) {
            $this->_tagged = $tagged;
        } elseif (!$this->_tagged) {
            return false;
        }
        $path_name = $this->_options['public_dir'] . dirname($id);
        // Switch extension if needed
        if (isset($this->_tagged[$id])) {
            $extension = $this->_tagged[$id]['extension'];
        } else {
            $extension = $this->_options['file_extension'];
        }
        $file = $path_name . '/' . $file_name . $extension;
        if (file_exists($file)) {
            return true;
        }
        return false;
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
     * @return boolean true if no problem
     */
    public function save($data, $id, $tags = [], $specific_lifetime = false)
    {
        if ($this->_options['disable_caching']) {
            return true;
        }
        $extension = null;
        if ($this->_is_serialized($data)) {
            $data = unserialize($data, ['allowed_classes' => false]);
            $extension = '.' . ltrim($data[1], '.');
            $data = $data[0];
        }
        clearstatcache();
        if (($id = (string) $id) === '') {
            $id = $this->_detect_id();
        } else {
            $id = $this->_decode_id($id);
        }
        $file_name = basename($id);
        if ($file_name === '') {
            $file_name = $this->_options['index_filename'];
        }
        $path_name = realpath($this->_options['public_dir']) . dirname($id);
        $this->_create_directories_for($path_name);
        if ($id === null || strlen($id) == 0) {
            $data_unserialized = unserialize($data, ['allowed_classes' => false]);
            $data = $data_unserialized['data'];
        }
        $ext = $this->_options['file_extension'];
        if ($extension) {
            $ext = $extension;
        }
        $file = rtrim($path_name, '/') . '/' . $file_name . $ext;
        if ($this->_options['file_locking']) {
            $result = file_put_contents($file, $data, LOCK_EX);
        } else {
            $result = file_put_contents($file, $data);
        }
        @chmod($file, $this->_octdec($this->_options['cache_file_perm']));
        if ($this->_tagged === null && $tagged = $this->get_inner_cache()->load(self::INNER_CACHE_NAME)) {
            $this->_tagged = $tagged;
        } elseif ($this->_tagged === null) {
            $this->_tagged = [];
        }
        if (!isset($this->_tagged[$id])) {
            $this->_tagged[$id] = [];
        }
        if (!isset($this->_tagged[$id]['tags'])) {
            $this->_tagged[$id]['tags'] = [];
        }
        $this->_tagged[$id]['tags'] = array_unique(array_merge($this->_tagged[$id]['tags'], $tags));
        $this->_tagged[$id]['extension'] = $ext;
        $this->get_inner_cache()->save($this->_tagged, self::INNER_CACHE_NAME);
        return (bool) $result;
    }
    /**
     * Recursively create the directories needed to write the static file
     */
    protected function _create_directories_for($path)
    {
        if (!is_dir($path)) {
            $old_umask = umask(0);
            if (!@mkdir($path, $this->_octdec($this->_options['cache_directory_perm']), true)) {
                $last_err = error_get_last();
                umask($old_umask);
                Zend_Cache::throw_exception("Can't create directory: {$last_err['message']}");
            }
            umask($old_umask);
        }
    }
    /**
     * Detect serialization of data (cannot predict since this is the only way
     * to obey the interface yet pass in another parameter).
     *
     * In future, ZF 2.0, check if we can just avoid the interface restraints.
     *
     * This format is the only valid one possible for the class, so it's simple
     * to just run a regular expression for the starting serialized format.
     */
    protected function _is_serialized($data)
    {
        return preg_match("/a:2:\\{i:0;s:\\d+:\"/", $data);
    }
    /**
     * Remove a cache record
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    public function remove($id)
    {
        if (!$this->_verify_path($id)) {
            Zend_Cache::throw_exception('Invalid cache id: does not match expected public_dir path');
        }
        $file_name = basename($id);
        if ($this->_tagged === null && $tagged = $this->get_inner_cache()->load(self::INNER_CACHE_NAME)) {
            $this->_tagged = $tagged;
        } elseif (!$this->_tagged) {
            return false;
        }
        if (isset($this->_tagged[$id])) {
            $extension = $this->_tagged[$id]['extension'];
        } else {
            $extension = $this->_options['file_extension'];
        }
        if ($file_name === '') {
            $file_name = $this->_options['index_filename'];
        }
        $path_name = $this->_options['public_dir'] . dirname($id);
        $file = realpath($path_name) . '/' . $file_name . $extension;
        if (!file_exists($file)) {
            return false;
        }
        return unlink($file);
    }
    /**
     * Remove a cache record recursively for the given directory matching a
     * REQUEST_URI based relative path (deletes the actual file matching this
     * in addition to the matching directory)
     *
     * @param  string $id Cache id
     * @return boolean True if no problem
     */
    public function remove_recursively($id)
    {
        if (!$this->_verify_path($id)) {
            Zend_Cache::throw_exception('Invalid cache id: does not match expected public_dir path');
        }
        $file_name = basename($id);
        if ($file_name === '') {
            $file_name = $this->_options['index_filename'];
        }
        $path_name = $this->_options['public_dir'] . dirname($id);
        $file = $path_name . '/' . $file_name . $this->_options['file_extension'];
        $directory = $path_name . '/' . $file_name;
        if (file_exists($directory)) {
            if (!is_writable($directory)) {
                return false;
            }
            if (is_dir($directory)) {
                foreach (new Directory_Iterator($directory) as $file) {
                    if (true === $file->is_file()) {
                        if (false === unlink($file->get_path_name())) {
                            return false;
                        }
                    }
                }
            }
            rmdir($directory);
        }
        if (file_exists($file)) {
            if (!is_writable($file)) {
                return false;
            }
            return unlink($file);
        }
        return true;
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
     * @return boolean true if no problem
     * @throws Zend_Exception
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = [])
    {
        $result = false;
        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
                if (empty($tags)) {
                    throw new Zend_Exception('Cannot use tag matching modes as no tags were defined');
                }
                if ($this->_tagged === null && $tagged = $this->get_inner_cache()->load(self::INNER_CACHE_NAME)) {
                    $this->_tagged = $tagged;
                } elseif (!$this->_tagged) {
                    return true;
                }
                foreach ($tags as $tag) {
                    $urls = array_keys($this->_tagged);
                    foreach ($urls as $url) {
                        if (isset($this->_tagged[$url]['tags']) && in_array($tag, $this->_tagged[$url]['tags'])) {
                            $this->remove($url);
                            unset($this->_tagged[$url]);
                        }
                    }
                }
                $this->get_inner_cache()->save($this->_tagged, self::INNER_CACHE_NAME);
                $result = true;
                break;
            case Zend_Cache::CLEANING_MODE_ALL:
                if ($this->_tagged === null) {
                    $tagged = $this->get_inner_cache()->load(self::INNER_CACHE_NAME);
                    $this->_tagged = $tagged;
                }
                if ($this->_tagged === null || empty($this->_tagged)) {
                    return true;
                }
                $urls = array_keys($this->_tagged);
                foreach ($urls as $url) {
                    $this->remove($url);
                    unset($this->_tagged[$url]);
                }
                $this->get_inner_cache()->save($this->_tagged, self::INNER_CACHE_NAME);
                $result = true;
                break;
            case Zend_Cache::CLEANING_MODE_OLD:
                $this->_log('Zend_Cache_Backend_Static : Selected Cleaning Mode Currently Unsupported By This Backend');
                break;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                if (empty($tags)) {
                    throw new Zend_Exception('Cannot use tag matching modes as no tags were defined');
                }
                if ($this->_tagged === null) {
                    $tagged = $this->get_inner_cache()->load(self::INNER_CACHE_NAME);
                    $this->_tagged = $tagged;
                }
                if ($this->_tagged === null || empty($this->_tagged)) {
                    return true;
                }
                $urls = array_keys($this->_tagged);
                foreach ($urls as $url) {
                    $difference = array_diff($tags, $this->_tagged[$url]['tags']);
                    if (count($tags) == count($difference)) {
                        $this->remove($url);
                        unset($this->_tagged[$url]);
                    }
                }
                $this->get_inner_cache()->save($this->_tagged, self::INNER_CACHE_NAME);
                $result = true;
                break;
            default:
                Zend_Cache::throw_exception('Invalid mode for clean() method');
                break;
        }
        return $result;
    }
    /**
     * Set an Inner Cache, used here primarily to store Tags associated
     * with caches created by this backend. Note: If Tags are lost, the cache
     * should be completely cleaned as the mapping of tags to caches will
     * have been irrevocably lost.
     *
     * @param  Zend_Cache_Core
     * @return void
     */
    public function set_inner_cache(Zend_Cache_Core $cache)
    {
        $this->_tag_cache = $cache;
        $this->_options['tag_cache'] = $cache;
    }
    /**
     * Get the Inner Cache if set
     *
     * @return Zend_Cache_Core
     */
    public function get_inner_cache()
    {
        if ($this->_tag_cache === null) {
            Zend_Cache::throw_exception('An Inner Cache has not been set; use setInnerCache()');
        }
        return $this->_tag_cache;
    }
    /**
     * Verify path exists and is non-empty
     *
     * @param  string $path
     */
    protected function _verify_path($path): bool
    {
        $path = realpath($path);
        $base = realpath($this->_options['public_dir']);
        return strncmp($path, $base, strlen($base)) !== 0;
    }
    /**
     * Determine the page to save from the request
     *
     * @return string
     */
    protected function _detect_id()
    {
        return $_SERVER['REQUEST_URI'];
    }
    /**
     * Validate a cache id or a tag (security, reliable filenames, reserved prefixes...)
     *
     * Throw an exception if a problem is found
     *
     * @param  string $string Cache id or tag
     * @throws Zend_Cache_Exception
     * @return void
     * @deprecated Not usable until perhaps ZF 2.0
     */
    protected static function _validate_id_or_tag($string)
    {
        if (!is_string($string)) {
            Zend_Cache::throw_exception('Invalid id or tag : must be a string');
        }
        // Internal only checked in Frontend - not here!
        if (substr($string, 0, 9) == 'internal-') {
            return;
        }
        // Validation assumes no query string, fragments or scheme included - only the path
        if (!preg_match('/^(?:\/(?:(?:%[[:xdigit:]]{2}|[A-Za-z0-9-_.!~*\'()\[\]:@&=+$,;])*)?)+$/', $string)) {
            Zend_Cache::throw_exception("Invalid id or tag '{$string}' : must be a valid URL path");
        }
    }
    /**
     * Detect an octal string and return its octal value for file permission ops
     * otherwise return the non-string (assumed octal or decimal int already)
     *
     * @param string $val The potential octal in need of conversion
     * @return int
     */
    protected function _octdec($val)
    {
        if (is_string($val) && decoct(octdec($val)) == $val) {
            return octdec($val);
        }
        return $val;
    }
    /**
     * Decode a request URI from the provided ID
     *
     * @param string $id
     */
    protected function _decode_id($id): string
    {
        return pack('H*', $id);
    }
}