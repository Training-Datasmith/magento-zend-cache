<?php

class Tests_Zc_ClassFixture
{
    public static $instanceCounter = 0;

    public function __construct()
    {
        self::$instanceCounter++;
    }

    public function add($a, $b)
    {
        if (!isset($GLOBALS['tests_zc_class_counter'])) {
            $GLOBALS['tests_zc_class_counter'] = 0;
        }
        $GLOBALS['tests_zc_class_counter']++;
        echo 'class_out';
        return $a + $b;
    }

    public static function staticAdd($a, $b)
    {
        if (!isset($GLOBALS['tests_zc_static_counter'])) {
            $GLOBALS['tests_zc_static_counter'] = 0;
        }
        $GLOBALS['tests_zc_static_counter']++;
        return $a + $b;
    }

    public function throws()
    {
        throw new RuntimeException('boom');
    }
}

function tests_zc_reset_class_counters()
{
    $GLOBALS['tests_zc_class_counter'] = 0;
    $GLOBALS['tests_zc_static_counter'] = 0;
    Tests_Zc_ClassFixture::$instanceCounter = 0;
}
