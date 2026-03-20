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
/** @see Zend_Cache_Exception */
#require_once 'Zend/Cache/Exception.php';
/** @see Zend_Cache */
#require_once 'Zend/Cache.php';
/**
 * @category   Zend
 * @package    Zend_Cache
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Cache_Manager
{
    /**
     * Constant holding reserved name for default Page Cache
     */
    public const PAGECACHE = 'page';
    /**
     * Constant holding reserved name for default Page Tag Cache
     */
    public const PAGETAGCACHE = 'pagetag';
    /**
     * Array of caches stored by the Cache Manager instance
     *
     * @var array
     */
    protected $_caches = [];
    /**
     * Array of ready made configuration templates for lazy
     * loading caches.
     *
     * @var array
     */
    protected $_option_templates = [
        // Simple Common Default
        'default' => ['frontend' => ['name' => 'Core', 'options' => ['automatic_serialization' => true]], 'backend' => ['name' => 'File', 'options' => []]],
        // Static Page HTML Cache
        'page' => ['frontend' => ['name' => 'Capture', 'options' => ['ignore_user_abort' => true]], 'backend' => ['name' => 'Static', 'options' => ['public_dir' => '../public']]],
        // Tag Cache
        'pagetag' => ['frontend' => ['name' => 'Core', 'options' => ['automatic_serialization' => true, 'lifetime' => null]], 'backend' => ['name' => 'File', 'options' => []]],
    ];
    /**
     * Set a new cache for the Cache Manager to contain
     *
     * @param  string $name
     */
    public function set_cache($name, Zend_Cache_Core $cache): self
    {
        $this->_caches[$name] = $cache;
        return $this;
    }
    /**
     * Check if the Cache Manager contains the named cache object, or a named
     * configuration template to lazy load the cache object
     *
     * @param string $name
     */
    public function has_cache($name): bool
    {
        if (isset($this->_caches[$name]) || $this->has_cache_template($name)) {
            return true;
        }
        return false;
    }
    /**
     * Fetch the named cache object, or instantiate and return a cache object
     * using a named configuration template
     *
     * @param  string $name
     * @return Zend_Cache_Core
     */
    public function get_cache($name)
    {
        if (isset($this->_caches[$name])) {
            return $this->_caches[$name];
        }
        if (isset($this->_option_templates[$name])) {
            if ($name == self::PAGECACHE && (!isset($this->_option_templates[$name]['backend']['options']['tag_cache']) || !$this->_option_templates[$name]['backend']['options']['tag_cache'] instanceof Zend_Cache_Core)) {
                $this->_option_templates[$name]['backend']['options']['tag_cache'] = $this->get_cache(self::PAGETAGCACHE);
            }
            $this->_caches[$name] = Zend_Cache::factory($this->_option_templates[$name]['frontend']['name'], $this->_option_templates[$name]['backend']['name'], $this->_option_templates[$name]['frontend']['options'] ?? [], $this->_option_templates[$name]['backend']['options'] ?? [], $this->_option_templates[$name]['frontend']['customFrontendNaming'] ?? false, $this->_option_templates[$name]['backend']['customBackendNaming'] ?? false, $this->_option_templates[$name]['frontendBackendAutoload'] ?? false);
            return $this->_caches[$name];
        }
    }
    /**
     * Fetch all available caches
     *
     * @return array An array of all available caches with it's names as key
     */
    public function get_caches()
    {
        $caches = $this->_caches;
        foreach ($this->_option_templates as $name => $tmp) {
            if (!isset($caches[$name])) {
                $caches[$name] = $this->get_cache($name);
            }
        }
        return $caches;
    }
    /**
     * Set a named configuration template from which a cache object can later
     * be lazy loaded
     *
     * @param  string $name
     * @param  array  $options
     * @throws Zend_Cache_Exception
     */
    public function set_cache_template($name, $options): self
    {
        if ($options instanceof Zend_Config) {
            $options = $options->to_array();
        } elseif (!is_array($options)) {
            #require_once 'Zend/Cache/Exception.php';
            throw new Zend_Cache_Exception('Options passed must be in' . ' an associative array or instance of Zend_Config');
        }
        $this->_option_templates[$name] = $options;
        return $this;
    }
    /**
     * Check if the named configuration template
     *
     * @param  string $name
     */
    public function has_cache_template($name): bool
    {
        if (isset($this->_option_templates[$name])) {
            return true;
        }
        return false;
    }
    /**
     * Get the named configuration template
     *
     * @param  string $name
     * @return array
     */
    public function get_cache_template($name)
    {
        if (isset($this->_option_templates[$name])) {
            return $this->_option_templates[$name];
        }
    }
    /**
     * Pass an array containing changes to be applied to a named
     * configuration
     * template
     *
     * @param  array $options
     * @throws Zend_Cache_Exception for invalid options format or if option templates do not have $name
     */
    public function set_template_options(string $name, $options): self
    {
        if ($options instanceof Zend_Config) {
            $options = $options->to_array();
        } elseif (!is_array($options)) {
            #require_once 'Zend/Cache/Exception.php';
            throw new Zend_Cache_Exception('Options passed must be in' . ' an associative array or instance of Zend_Config');
        }
        if (!isset($this->_option_templates[$name])) {
            throw new Zend_Cache_Exception('A cache configuration template' . 'does not exist with the name "' . $name . '"');
        }
        $this->_option_templates[$name] = $this->_merge_options($this->_option_templates[$name], $options);
        return $this;
    }
    /**
     * Simple method to merge two configuration arrays
     *
     * @return array
     */
    protected function _merge_options(array $current, array $options)
    {
        if (isset($options['frontend']['name'])) {
            $current['frontend']['name'] = $options['frontend']['name'];
        }
        if (isset($options['backend']['name'])) {
            $current['backend']['name'] = $options['backend']['name'];
        }
        if (isset($options['frontend']['options'])) {
            foreach ($options['frontend']['options'] as $key => $value) {
                $current['frontend']['options'][$key] = $value;
            }
        }
        if (isset($options['backend']['options'])) {
            foreach ($options['backend']['options'] as $key => $value) {
                $current['backend']['options'][$key] = $value;
            }
        }
        if (isset($options['frontend']['customFrontendNaming'])) {
            $current['frontend']['customFrontendNaming'] = $options['frontend']['customFrontendNaming'];
        }
        if (isset($options['backend']['customBackendNaming'])) {
            $current['backend']['customBackendNaming'] = $options['backend']['customBackendNaming'];
        }
        if (isset($options['frontendBackendAutoload'])) {
            $current['frontendBackendAutoload'] = $options['frontendBackendAutoload'];
        }
        return $current;
    }
}