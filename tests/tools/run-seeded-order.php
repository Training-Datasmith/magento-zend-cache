<?php

/**
 * Single-process PHPUnit run with Fisher-Yates test order (fixed seed).
 * Exit 1 on test failure; exit 2 if fewer tests executed than in the suite.
 */

error_reporting(-1);
ini_set('display_errors', '1');

const SEED = 20261008;

$root = dirname(dirname(__DIR__));
chdir($root);

require $root . '/tests/bootstrap.php';

$configFile = $root . '/phpunit.xml.dist';
if (!is_file($configFile)) {
    fwrite(STDERR, "Missing phpunit.xml.dist\n");
    exit(2);
}

$configuration = PHPUnit\Util\Configuration::getInstance($configFile);
$suite = $configuration->getTestSuiteConfiguration();

$tests = array();
self_collect($suite, $tests);
$expected = count($tests);

mt_srand(SEED);
for ($i = count($tests) - 1; $i > 0; $i--) {
    $j = mt_rand(0, $i);
    $tmp = $tests[$i];
    $tests[$i] = $tests[$j];
    $tests[$j] = $tmp;
}

$ordered = new PHPUnit\Framework\TestSuite('SeededOrder');
foreach ($tests as $test) {
    $ordered->addTest($test);
}

$runner = new PHPUnit\TextUI\TestRunner();
$result = $runner->doRun($ordered, array(), false);

$run = $result->count();
if ($run < $expected) {
    fwrite(STDERR, "Expected {$expected} tests, ran {$run}\n");
    exit(2);
}

exit($result->wasSuccessful() ? 0 : 1);

function self_collect(PHPUnit\Framework\Test $test, array &$out)
{
    if ($test instanceof PHPUnit\Framework\TestSuite) {
        foreach ($test as $child) {
            self_collect($child, $out);
        }
        return;
    }
    $out[] = $test;
}
