<?php

class Tests_Zc_InMemoryZendServer extends Zend_Cache_Backend_ZendServer
{
    private static $store = array();

    protected function _store($data, $id, $timeToLive)
    {
        self::$store[$id] = $data;
        return true;
    }

    protected function _fetch($id)
    {
        return array_key_exists($id, self::$store) ? self::$store[$id] : null;
    }

    protected function _unset($id)
    {
        unset(self::$store[$id]);
        return true;
    }

    protected function _clear()
    {
        self::$store = array();
    }

    public static function resetStore()
    {
        self::$store = array();
    }
}
