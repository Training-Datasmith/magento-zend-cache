<?php

function tests_zc_add($a, $b)
{
    if (!isset($GLOBALS['tests_zc_add_counter'])) {
        $GLOBALS['tests_zc_add_counter'] = 0;
    }
    $GLOBALS['tests_zc_add_counter']++;
    echo 'out';
    return $a + $b;
}

function tests_zc_reset_add_counter()
{
    $GLOBALS['tests_zc_add_counter'] = 0;
}

function tests_zc_get_add_counter()
{
    return isset($GLOBALS['tests_zc_add_counter']) ? $GLOBALS['tests_zc_add_counter'] : 0;
}
