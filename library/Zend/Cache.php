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
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @package    Zend_Cache
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
abstract class Zend_Cache
{
    /**
     * Standard frontends
     *
     * @var array
     */
    public static $standard_frontends = ['Core', 'Output', 'Class', 'File', 'Function', 'Page'];
    /**
     * Standard backends
     *
     * @var array
     */
    public static $standard_backends = ['File', 'Sqlite', 'Memcached', 'Libmemcached', 'Apc', 'ZendPlatform', 'Xcache', 'TwoLevels', 'WinCache', 'ZendServer_Disk', 'ZendServer_ShMem'];
    /**
     * Standard backends which implement the ExtendedInterface
     *
     * @var array
     */
    public static $standard_extended_backends = ['File', 'Apc', 'TwoLevels', 'Memcached', 'Libmemcached', 'Sqlite', 'WinCache'];
    /**
     * Only for backward compatibility (may be removed in next major release)
     *
     * @var array
     * @deprecated
     */
    public static $available_frontends = ['Core', 'Output', 'Class', 'File', 'Function', 'Page'];
    /**
     * Only for backward compatibility (may be removed in next major release)
     *
     * @var array
     * @deprecated
     */
    public static $available_backends = ['File', 'Sqlite', 'Memcached', 'Libmemcached', 'Apc', 'ZendPlatform', 'Xcache', 'WinCache', 'TwoLevels'];
    /**
     * Consts for clean() method
     */
    public const CLEANING_MODE_ALL = 'all';
    public const CLEANING_MODE_OLD = 'old';
    public const CLEANING_MODE_MATCHING_TAG = 'matchingTag';
    public const CLEANING_MODE_NOT_MATCHING_TAG = 'notMatchingTag';
    public const CLEANING_MODE_MATCHING_ANY_TAG = 'matchingAnyTag';
    /**
     * Factory
     *
     * @param mixed  $frontend        frontend name (string) or Zend_Cache_Frontend_ object
     * @param mixed  $backend         backend name (string) or Zend_Cache_Backend_ object
     * @param array  $frontendOptions associative array of options for the corresponding frontend constructor
     * @param array  $backendOptions  associative array of options for the corresponding backend constructor
     * @param boolean $customFrontendNaming if true, the frontend argument is used as a complete class name ; if false, the frontend argument is used as the end of "Zend_Cache_Frontend_[...]" class name
     * @param boolean $customBackendNaming if true, the backend argument is used as a complete class name ; if false, the backend argument is used as the end of "Zend_Cache_Backend_[...]" class name
     * @param boolean $autoload if true, there will no require_once for backend and frontend (useful only for custom backends/frontends)
     * @throws Zend_Cache_Exception
     * @return Zend_Cache_Core|Zend_Cache_Frontend
     */
    public static function factory($frontend, $backend, $frontend_options = [], $backend_options = [], $custom_frontend_naming = false, $custom_backend_naming = false, $autoload = false)
    {
        if (is_string($backend)) {
            $backend_object = self::_make_backend($backend, $backend_options, $custom_backend_naming, $autoload);
        } else if (is_object($backend) && in_array('Zend_Cache_Backend_Interface', class_implements($backend))) {
            $backend_object = $backend;
        } else {
            self::throw_exception('backend must be a backend name (string) or an object which implements Zend_Cache_Backend_Interface');
        }
        if (is_string($frontend)) {
            $frontend_object = self::_make_frontend($frontend, $frontend_options, $custom_frontend_naming, $autoload);
        } else if (is_object($frontend)) {
            $frontend_object = $frontend;
        } else {
            self::throw_exception('frontend must be a frontend name (string) or an object');
        }
        $frontend_object->set_backend($backend_object);
        return $frontend_object;
    }
    /**
     * Backend Constructor
     *
     * @param string  $backend
     * @param array   $backendOptions
     * @param boolean $customBackendNaming
     * @param boolean $autoload
     * @return Zend_Cache_Backend
     */
    public static function _make_backend($backend, $backend_options, $custom_backend_naming = false, $autoload = false)
    {
        if (!$custom_backend_naming) {
            $backend = self::_normalize_name($backend);
        }
        if (in_array($backend, Zend_Cache::$standard_backends)) {
            // we use a standard backend
            $backend_class = 'Zend_Cache_Backend_' . $backend;
            // security controls are explicit
            #require_once str_replace('_', DIRECTORY_SEPARATOR, $backendClass) . '.php';
        } else {
            // we use a custom backend
            if (!preg_match('~^[\w\\\\]+$~D', $backend)) {
                Zend_Cache::throw_exception("Invalid backend name [{$backend}]");
            }
            if (!$custom_backend_naming) {
                // we use this boolean to avoid an API break
                $backend_class = 'Zend_Cache_Backend_' . $backend;
            } else {
                $backend_class = $backend;
            }
            if (!$autoload) {
                $file = str_replace('_', DIRECTORY_SEPARATOR, $backend_class) . '.php';
                if (!self::_is_readable($file)) {
                    self::throw_exception("file {$file} not found in include_path");
                }
                #require_once $file;
            }
        }
        return new $backend_class($backend_options);
    }
    /**
     * Frontend Constructor
     *
     * @param string  $frontend
     * @param array   $frontendOptions
     * @param boolean $customFrontendNaming
     * @param boolean $autoload
     * @return Zend_Cache_Core|Zend_Cache_Frontend
     */
    public static function _make_frontend($frontend, $frontend_options = [], $custom_frontend_naming = false, $autoload = false)
    {
        if (!$custom_frontend_naming) {
            $frontend = self::_normalize_name($frontend);
        }
        if (in_array($frontend, self::$standard_frontends)) {
            // we use a standard frontend
            // For perfs reasons, with frontend == 'Core', we can interact with the Core itself
            $frontend_class = 'Zend_Cache_' . ($frontend != 'Core' ? 'Frontend_' : '') . $frontend;
            // security controls are explicit
            #require_once str_replace('_', DIRECTORY_SEPARATOR, $frontendClass) . '.php';
        } else {
            // we use a custom frontend
            if (!preg_match('~^[\w\\\\]+$~D', $frontend)) {
                Zend_Cache::throw_exception("Invalid frontend name [{$frontend}]");
            }
            if (!$custom_frontend_naming) {
                // we use this boolean to avoid an API break
                $frontend_class = 'Zend_Cache_Frontend_' . $frontend;
            } else {
                $frontend_class = $frontend;
            }
            if (!$autoload) {
                $file = str_replace('_', DIRECTORY_SEPARATOR, $frontend_class) . '.php';
                if (!self::_is_readable($file)) {
                    self::throw_exception("file {$file} not found in include_path");
                }
                #require_once $file;
            }
        }
        return new $frontend_class($frontend_options);
    }
    /**
     * Throw an exception
     *
     * Note : for perf reasons, the "load" of Zend/Cache/Exception is dynamic
     * @param  string $msg  Message for the exception
     * @throws Zend_Cache_Exception
     */
    public static function throw_exception($msg, ?Exception $e = null)
    {
        // For perfs reasons, we use this dynamic inclusion
        #require_once 'Zend/Cache/Exception.php';
        throw new Zend_Cache_Exception($msg, 0, $e);
    }
    /**
     * Normalize frontend and backend names to allow multiple words TitleCased
     *
     * @param  string $name  Name to normalize
     * @return string
     */
    protected static function _normalize_name($name)
    {
        $name = ucfirst(strtolower($name));
        $name = str_replace(['-', '_', '.'], ' ', $name);
        $name = ucwords($name);
        $name = str_replace(' ', '', $name);
        if (stripos($name, 'ZendServer') === 0) {
            return 'ZendServer_' . substr($name, strlen('ZendServer'));
        }
        return $name;
    }
    /**
     * Returns TRUE if the $filename is readable, or FALSE otherwise.
     * This function uses the PHP include_path, where PHP's is_readable()
     * does not.
     *
     * Note : this method comes from Zend_Loader (see #ZF-2891 for details)
     *
     * @param string   $filename
     */
    private static function _is_readable($filename): bool
    {
        if (!$fh = @fopen($filename, 'r', true)) {
            return false;
        }
        @fclose($fh);
        return true;
    }
}