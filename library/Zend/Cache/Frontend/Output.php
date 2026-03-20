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
class Zend_Cache_Frontend_Output extends Zend_Cache_Core
{
    private $_id_stack = [];
    /**
     * Constructor
     *
     * @param  array $options Associative array of options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->_id_stack = [];
    }
    /**
     * Start the cache
     *
     * @param  string  $id                     Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @param  boolean $echoData               If set to true, datas are sent to the browser if the cache is hit (simply returned else)
     * @return mixed True if the cache is hit (false else) with $echoData=true (default) ; string else (datas)
     */
    public function start($id, $do_not_test_cache_validity = false, $echo_data = true)
    {
        $data = $this->load($id, $do_not_test_cache_validity);
        if ($data !== false) {
            if ($echo_data) {
                echo $data;
                return true;
            }
            return $data;
        }
        ob_start();
        ob_implicit_flush(false);
        $this->_id_stack[] = $id;
        return false;
    }
    /**
     * Stop the cache
     *
     * @param  array   $tags             Tags array
     * @param  int     $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @param  string  $forcedDatas      If not null, force written datas with this
     * @param  boolean $echoData         If set to true, datas are sent to the browser
     * @param  int     $priority         integer between 0 (very low priority) and 10 (maximum priority) used by some particular backends
     * @return void
     */
    public function end($tags = [], $specific_lifetime = false, $forced_datas = null, $echo_data = true, $priority = 8)
    {
        if ($forced_datas === null) {
            $data = ob_get_clean();
        } else {
            $data =& $forced_datas;
        }
        $id = array_pop($this->_id_stack);
        if ($id === null) {
            Zend_Cache::throw_exception('use of end() without a start()');
        }
        $this->save($data, $id, $tags, $specific_lifetime, $priority);
        if ($echo_data) {
            echo $data;
        }
    }
}