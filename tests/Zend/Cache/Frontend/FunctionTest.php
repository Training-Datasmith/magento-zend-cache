<?php

class Zend_Cache_Frontend_FunctionTest extends Zend_Cache_TestCase
{
    private function functionFrontend($dir, $opts = array())
    {
        return Zend_Cache::factory(
            'Function',
            'File',
            array_merge(array('automatic_cleaning_factor' => 0), $opts),
            $this->fileBackendOptions($dir)
        );
    }

    public function testSecondCallHitsCache()
    {
        $dir = $this->tempDir('fn');
        tests_zc_reset_add_counter();
        try {
            $fe = $this->functionFrontend($dir);
            ob_start();
            $this->assertSame(3, $fe->call('tests_zc_add', array(1, 2)));
            $this->assertSame('out', ob_get_clean());
            $this->assertSame(1, tests_zc_get_add_counter());
            ob_start();
            $this->assertSame(3, $fe->call('tests_zc_add', array(1, 2)));
            $this->assertSame('out', ob_get_clean());
            $this->assertSame(1, tests_zc_get_add_counter());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testNonCachedListBypasses()
    {
        $dir = $this->tempDir('fn-nc');
        tests_zc_reset_add_counter();
        try {
            $fe = $this->functionFrontend($dir, array(
                'non_cached_functions' => array('tests_zc_add'),
            ));
            $fe->call('tests_zc_add', array(1, 1));
            $fe->call('tests_zc_add', array(1, 1));
            $this->assertSame(2, tests_zc_get_add_counter());

            $dir2 = $this->tempDir('fn-c');
            $fe2 = Zend_Cache::factory(
                'Function',
                'File',
                array(
                    'cache_by_default' => false,
                    'cached_functions' => array('tests_zc_add'),
                    'automatic_cleaning_factor' => 0,
                ),
                $this->fileBackendOptions($dir2)
            );
            tests_zc_reset_add_counter();
            $fe2->call('tests_zc_add', array(1, 1));
            $fe2->call('tests_zc_add', array(1, 1));
            $this->assertSame(1, tests_zc_get_add_counter());
            $this->removeDir($dir2);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testInvalidCallbackThrows()
    {
        $dir = $this->tempDir('fn-bad');
        try {
            $fe = $this->functionFrontend($dir);
            try {
                $fe->call(array(123, 'noMethod'));
                $this->fail('Expected exception');
            } catch (Zend_Cache_Exception $e) {
                $this->assertContains('Invalid callback', $e->getMessage());
            }
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testMakeIdStable()
    {
        $dir = $this->tempDir('fn-id');
        try {
            $fe = $this->functionFrontend($dir);
            $a = $fe->makeId('tests_zc_add', array(1));
            $b = $fe->makeId('tests_zc_add', array(1));
            $c = $fe->makeId('tests_zc_add', array(2));
            $this->assertRegExp('/^[a-f0-9]{32}$/', $a);
            $this->assertSame($a, $b);
            $this->assertNotSame($a, $c);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testClosureCallThrows()
    {
        $dir = $this->tempDir('fn-cl');
        try {
            $fe = $this->functionFrontend($dir);
            $this->expectException('Zend_Cache_Exception');
            $fe->call(function () {
                return 1;
            });
        } finally {
            $this->removeDir($dir);
        }
    }
}
