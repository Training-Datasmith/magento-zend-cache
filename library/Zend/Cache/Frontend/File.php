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
 * @subpackage Zend_Cache_Frontend
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */
/**
 * @see Zend_Cache_Core
 */
#require_once 'Zend/Cache/Core.php';
/**
 * @package    Zend_Cache
 * @subpackage Zend_Cache_Frontend
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Cache_Frontend_File extends Zend_Cache_Core
{
    /**
     * Consts for master_files_mode
     */
    public const MODE_AND = 'AND';
    public const MODE_OR = 'OR';
    /**
     * Available options
     *
     * ====> (string) master_file :
     * - a complete path of the master file
     * - deprecated (see master_files)
     *
     * ====> (array) master_files :
     * - an array of complete path of master files
     * - this option has to be set !
     *
     * ====> (string) master_files_mode :
     * - Zend_Cache_Frontend_File::MODE_AND or Zend_Cache_Frontend_File::MODE_OR
     * - if MODE_AND, then all master files have to be touched to get a cache invalidation
     * - if MODE_OR (default), then a single touched master file is enough to get a cache invalidation
     *
     * ====> (boolean) ignore_missing_master_files
     * - if set to true, missing master files are ignored silently
     * - if set to false (default), an exception is thrown if there is a missing master file
     * @var array available options
     */
    protected $_specific_options = ['master_file' => null, 'master_files' => null, 'master_files_mode' => 'OR', 'ignore_missing_master_files' => false];
    /**
     * Master file mtimes
     *
     * Array of int
     *
     * @var array
     */
    private $_master_file_mtimes;
    /**
     * Constructor
     *
     * @param  array $options Associative array of options
     * @throws Zend_Cache_Exception
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $name => $value) {
            $this->set_option($name, $value);
        }
        if (!isset($this->_specific_options['master_files'])) {
            Zend_Cache::throw_exception('master_files option must be set');
        }
    }
    /**
     * Change the master_files option
     *
     * @param array $masterFiles the complete paths and name of the master files
     */
    public function set_master_files(array $master_files)
    {
        $this->_specific_options['master_file'] = null;
        // to keep a compatibility
        $this->_specific_options['master_files'] = null;
        $this->_master_file_mtimes = [];
        clearstatcache();
        $i = 0;
        foreach ($master_files as $master_file) {
            if (file_exists($master_file)) {
                $mtime = filemtime($master_file);
            } else {
                $mtime = false;
            }
            if (!$this->_specific_options['ignore_missing_master_files'] && !$mtime) {
                Zend_Cache::throw_exception('Unable to read master_file : ' . $master_file);
            }
            $this->_master_file_mtimes[$i] = $mtime;
            $this->_specific_options['master_files'][$i] = $master_file;
            if ($i === 0) {
                // to keep a compatibility
                $this->_specific_options['master_file'] = $master_file;
            }
            $i++;
        }
    }
    /**
     * Change the master_file option
     *
     * To keep the compatibility
     *
     * @deprecated
     * @param string $masterFile the complete path and name of the master file
     */
    public function set_master_file($master_file)
    {
        $this->set_master_files([$master_file]);
    }
    /**
     * Public frontend to set an option
     *
     * Just a wrapper to get a specific behaviour for master_file
     *
     * @param  string $name  Name of the option
     * @param  mixed  $value Value of the option
     * @throws Zend_Cache_Exception
     * @return void
     */
    public function set_option($name, $value)
    {
        if ($name == 'master_file') {
            $this->set_master_file($value);
        } elseif ($name == 'master_files') {
            $this->set_master_files($value);
        } else {
            parent::set_option($name, $value);
        }
    }
    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @param  boolean $doNotUnserialize       Do not serialize (even if automatic_serialization is true) => for internal use
     * @return mixed|false Cached datas
     */
    public function load($id, $do_not_test_cache_validity = false, $do_not_unserialize = false)
    {
        if (!$do_not_test_cache_validity) {
            if ($this->test($id)) {
                return parent::load($id, true, $do_not_unserialize);
            }
            return false;
        }
        return parent::load($id, true, $do_not_unserialize);
    }
    /**
     * Test if a cache is available for the given id
     *
     * @param  string $id Cache id
     * @return int|false Last modified time of cache entry if it is available, false otherwise
     */
    public function test($id)
    {
        $last_modified = parent::test($id);
        if ($last_modified) {
            if ($this->_specific_options['master_files_mode'] == self::MODE_AND) {
                // MODE_AND
                foreach ($this->_master_file_mtimes as $master_file_m_time) {
                    if ($master_file_m_time) {
                        if ($last_modified > $master_file_m_time) {
                            return $last_modified;
                        }
                    }
                }
            } else {
                // MODE_OR
                $res = true;
                foreach ($this->_master_file_mtimes as $master_file_m_time) {
                    if ($master_file_m_time) {
                        if ($last_modified <= $master_file_m_time) {
                            return false;
                        }
                    }
                }
                return $last_modified;
            }
        }
        return false;
    }
}