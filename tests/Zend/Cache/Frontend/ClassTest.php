<?php

class Zend_Cache_Frontend_ClassTest extends Zend_Cache_TestCase
{
    public function testRequiresEntity()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_Class();
    }

    public function testRejectsInvalidEntity()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_Class(array('cached_entity' => 1));
    }

    public function testCachesInstanceCalls()
    {
        $dir = $this->tempDir('class-fe');
        tests_zc_reset_class_counters();
        try {
            $obj = new Tests_Zc_ClassFixture();
            $fe = Zend_Cache::factory(
                'Class',
                'File',
                array(
                    'cached_entity' => $obj,
                    'automatic_cleaning_factor' => 0,
                ),
                $this->fileBackendOptions($dir)
            );
            ob_start();
            $this->assertSame(5, $fe->add(2, 3));
            $this->assertSame('class_out', ob_get_clean());
            ob_start();
            $this->assertSame(5, $fe->add(2, 3));
            $this->assertSame('class_out', ob_get_clean());
            $this->assertSame(1, $GLOBALS['tests_zc_class_counter']);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCachesStaticCalls()
    {
        $dir = $this->tempDir('class-st');
        tests_zc_reset_class_counters();
        try {
            $fe = Zend_Cache::factory(
                'Class',
                'File',
                array(
                    'cached_entity' => 'Tests_Zc_ClassFixture',
                    'automatic_cleaning_factor' => 0,
                ),
                $this->fileBackendOptions($dir)
            );
            $this->assertSame(4, $fe->staticAdd(1, 3));
            $this->assertSame(4, $fe->staticAdd(1, 3));
            $this->assertSame(1, $GLOBALS['tests_zc_static_counter']);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testNonCachedMethod()
    {
        $dir = $this->tempDir('class-nc');
        tests_zc_reset_class_counters();
        try {
            $obj = new Tests_Zc_ClassFixture();
            $fe = Zend_Cache::factory(
                'Class',
                'File',
                array(
                    'cached_entity' => $obj,
                    'non_cached_methods' => array('add'),
                    'automatic_cleaning_factor' => 0,
                ),
                $this->fileBackendOptions($dir)
            );
            $fe->add(1, 1);
            $fe->add(1, 1);
            $this->assertSame(2, $GLOBALS['tests_zc_class_counter']);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testExceptionCleansBuffer()
    {
        $dir = $this->tempDir('class-ex');
        tests_zc_reset_class_counters();
        try {
            $obj = new Tests_Zc_ClassFixture();
            $fe = Zend_Cache::factory(
                'Class',
                'File',
                array(
                    'cached_entity' => $obj,
                    'automatic_cleaning_factor' => 0,
                ),
                $this->fileBackendOptions($dir)
            );
            $level = ob_get_level();
            try {
                $fe->throws();
            } catch (RuntimeException $e) {
                $this->assertSame('boom', $e->getMessage());
            }
            $this->assertSame($level, ob_get_level());
            $this->assertSame(3, $fe->add(1, 2));
        } finally {
            $this->removeDir($dir);
        }
    }
}
