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
 * @license    http://framework.zend.com/license/new-bsd New BSD License
 */
class Zend_Cache_Frontend_Class extends Zend_Cache_Core
{
    /**
     * Available options
     *
     * ====> (mixed) cached_entity :
     * - if set to a class name, we will cache an abstract class and will use only static calls
     * - if set to an object, we will cache this object methods
     *
     * ====> (boolean) cache_by_default :
     * - if true, method calls will be cached by default
     *
     * ====> (array) cached_methods :
     * - an array of method names which will be cached (even if cache_by_default = false)
     *
     * ====> (array) non_cached_methods :
     * - an array of method names which won't be cached (even if cache_by_default = true)
     *
     * @var array available options
     */
    protected $_specific_options = ['cached_entity' => null, 'cache_by_default' => true, 'cached_methods' => [], 'non_cached_methods' => []];
    /**
     * Tags array
     *
     * @var array
     */
    protected $_tags = [];
    /**
     * SpecificLifetime value
     *
     * false => no specific life time
     *
     * @var bool|int
     */
    protected $_specific_lifetime = false;
    /**
     * The cached object or the name of the cached abstract class
     *
     * @var mixed
     */
    protected $_cached_entity;
    /**
     * The class name of the cached object or cached abstract class
     *
     * Used to differentiate between different classes with the same method calls.
     *
     * @var string
     */
    protected $_cached_entity_label = '';
    /**
     * Priority (used by some particular backends)
     *
     * @var int
     */
    protected $_priority = 8;
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
        if ($this->_specific_options['cached_entity'] === null) {
            Zend_Cache::throw_exception('cached_entity must be set !');
        }
        $this->set_cached_entity($this->_specific_options['cached_entity']);
        $this->set_option('automatic_serialization', true);
    }
    /**
     * Set a specific life time
     *
     * @param  bool|int $specificLifetime
     * @return void
     */
    public function set_specific_lifetime($specific_lifetime = false)
    {
        $this->_specific_lifetime = $specific_lifetime;
    }
    /**
     * Set the priority (used by some particular backends)
     *
     * @param int $priority integer between 0 (very low priority) and 10 (maximum priority)
     */
    public function set_priority($priority)
    {
        $this->_priority = $priority;
    }
    /**
     * Public frontend to set an option
     *
     * Just a wrapper to get a specific behaviour for cached_entity
     *
     * @param  string $name  Name of the option
     * @param  mixed  $value Value of the option
     * @throws Zend_Cache_Exception
     * @return void
     */
    public function set_option($name, $value)
    {
        if ($name == 'cached_entity') {
            $this->set_cached_entity($value);
        } else {
            parent::set_option($name, $value);
        }
    }
    /**
     * Specific method to set the cachedEntity
     *
     * if set to a class name, we will cache an abstract class and will use only static calls
     * if set to an object, we will cache this object methods
     *
     * @param mixed $cachedEntity
     */
    public function set_cached_entity($cached_entity)
    {
        if (!is_string($cached_entity) && !is_object($cached_entity)) {
            Zend_Cache::throw_exception('cached_entity must be an object or a class name');
        }
        $this->_cached_entity = $cached_entity;
        $this->_specific_options['cached_entity'] = $cached_entity;
        if (is_string($this->_cached_entity)) {
            $this->_cached_entity_label = $this->_cached_entity;
        } else {
            $ro = new Reflection_Object($this->_cached_entity);
            $this->_cached_entity_label = $ro->get_name();
        }
    }
    /**
     * Set the cache array
     *
     * @param  array $tags
     * @return void
     */
    public function set_tags_array($tags = [])
    {
        $this->_tags = $tags;
    }
    /**
     * Main method : call the specified method or get the result from cache
     *
     * @param  string $name       Method name
     * @param  array  $parameters Method parameters
     * @return mixed Result
     * @throws Exception
     */
    public function __call(string $name, array $parameters)
    {
        $callback = [$this->_cached_entity, $name];
        if (!is_callable($callback, false)) {
            Zend_Cache::throw_exception('Invalid callback');
        }
        $cache_bool1 = $this->_specific_options['cache_by_default'];
        $cache_bool2 = in_array($name, $this->_specific_options['cached_methods']);
        $cache_bool3 = in_array($name, $this->_specific_options['non_cached_methods']);
        $cache = ($cache_bool1 || $cache_bool2) && !$cache_bool3;
        if (!$cache) {
            // We do not have not cache
            return call_user_func_array($callback, $parameters);
        }
        $id = $this->make_id($name, $parameters);
        if (($rs = $this->load($id)) && array_key_exists(0, $rs) && array_key_exists(1, $rs)) {
            // A cache is available
            $output = $rs[0];
            $return = $rs[1];
        } else {
            // A cache is not available (or not valid for this frontend)
            ob_start();
            ob_implicit_flush(false);
            try {
                $return = call_user_func_array($callback, $parameters);
                $output = ob_get_clean();
                $data = [$output, $return];
                $this->save($data, $id, $this->_tags, $this->_specific_lifetime, $this->_priority);
            } catch (Exception $e) {
                ob_end_clean();
                throw $e;
            }
        }
        echo $output;
        return $return;
    }
    /**
     * ZF-9970
     *
     * @deprecated
     */
    private function _make_id($name, array $args)
    {
        return $this->make_id($name, $args);
    }
    /**
     * Make a cache id from the method name and parameters
     *
     * @param  string $name Method name
     * @param  array  $args Method parameters
     * @return string Cache id
     */
    public function make_id(string $name, array $args = []): string
    {
        return md5($this->_cached_entity_label . '__' . $name . '__' . serialize($args));
    }
}