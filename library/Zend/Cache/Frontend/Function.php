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
class Zend_Cache_Frontend_Function extends Zend_Cache_Core
{
    /**
     * This frontend specific options
     *
     * ====> (boolean) cache_by_default :
     * - if true, function calls will be cached by default
     *
     * ====> (array) cached_functions :
     * - an array of function names which will be cached (even if cache_by_default = false)
     *
     * ====> (array) non_cached_functions :
     * - an array of function names which won't be cached (even if cache_by_default = true)
     *
     * @var array options
     */
    protected $_specific_options = ['cache_by_default' => true, 'cached_functions' => [], 'non_cached_functions' => []];
    /**
     * Constructor
     *
     * @param  array $options Associative array of options
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $name => $value) {
            $this->set_option($name, $value);
        }
        $this->set_option('automatic_serialization', true);
    }
    /**
     * Main method : call the specified function or get the result from cache
     *
     * @param  callback $callback         A valid callback
     * @param  array    $parameters       Function parameters
     * @param  array    $tags             Cache tags
     * @param  int      $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @param  int      $priority         integer between 0 (very low priority) and 10 (maximum priority) used by some particular backends
     * @return mixed Result
     */
    public function call($callback, array $parameters = [], $tags = [], $specific_lifetime = false, $priority = 8)
    {
        if (!is_callable($callback, true, $name)) {
            Zend_Cache::throw_exception('Invalid callback');
        }
        $cache_bool1 = $this->_specific_options['cache_by_default'];
        $cache_bool2 = in_array($name, $this->_specific_options['cached_functions']);
        $cache_bool3 = in_array($name, $this->_specific_options['non_cached_functions']);
        $cache = ($cache_bool1 || $cache_bool2) && !$cache_bool3;
        if (!$cache) {
            // Caching of this callback is disabled
            return call_user_func_array($callback, $parameters);
        }
        $id = $this->_make_id($callback, $parameters);
        if (($rs = $this->load($id)) && isset($rs[0], $rs[1])) {
            // A cache is available
            $output = $rs[0];
            $return = $rs[1];
        } else {
            // A cache is not available (or not valid for this frontend)
            ob_start();
            ob_implicit_flush(false);
            $return = call_user_func_array($callback, $parameters);
            $output = ob_get_clean();
            $data = [$output, $return];
            $this->save($data, $id, $tags, $specific_lifetime, $priority);
        }
        echo $output;
        return $return;
    }
    /**
     * ZF-9970
     *
     * @deprecated
     */
    private function _make_id($callback, array $args)
    {
        return $this->make_id($callback, $args);
    }
    /**
     * Make a cache id from the function name and parameters
     *
     * @param  callback $callback A valid callback
     * @param  array    $args     Function parameters
     * @throws Zend_Cache_Exception
     * @return string Cache id
     */
    public function make_id(array $callback, array $args = []): string
    {
        if (!is_callable($callback, true, $name)) {
            Zend_Cache::throw_exception('Invalid callback');
        }
        // functions, methods and classnames are case-insensitive
        $name = strtolower($name);
        // generate a unique id for object callbacks
        if (is_object($callback)) {
            // Closures & __invoke
            $object = $callback;
        } elseif (isset($callback[0])) {
            // array($object, 'method')
            $object = $callback[0];
        }
        if (isset($object)) {
            try {
                $tmp = @serialize($callback);
            } catch (Exception $e) {
                Zend_Cache::throw_exception($e->get_message());
            }
            if (!$tmp) {
                $last_err = error_get_last();
                Zend_Cache::throw_exception("Can't serialize callback object to generate id: {$last_err['message']}");
            }
            $name .= '__' . $tmp;
        }
        // generate a unique id for arguments
        $args_str = '';
        if ($args) {
            try {
                $args_str = @serialize(array_values($args));
            } catch (Exception $e) {
                Zend_Cache::throw_exception($e->get_message());
            }
            if (!$args_str) {
                $last_err = error_get_last();
                throw Zend_Cache::throw_exception("Can't serialize arguments to generate id: {$last_err['message']}");
            }
        }
        return md5($name . $args_str);
    }
}