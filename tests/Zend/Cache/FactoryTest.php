<?php

class Zend_Cache_FactoryTest extends Zend_Cache_TestCase
{
    public function testThrowExceptionMessageAndPrevious()
    {
        $inner = new Exception('inner');
        try {
            Zend_Cache::throwException('boom', $inner);
            $this->fail('Expected exception');
        } catch (Zend_Cache_Exception $e) {
            $this->assertSame('boom', $e->getMessage());
            $this->assertSame($inner, $e->getPrevious());
        }
    }

    public function testFactoryCoreFileRoundTrip()
    {
        $dir = $this->tempDir('factory');
        try {
            $cache = Zend_Cache::factory(
                'Core',
                'file',
                array('automatic_cleaning_factor' => 0),
                $this->fileBackendOptions($dir)
            );
            $this->assertInstanceOf('Zend_Cache_Core', $cache);
            $this->assertInstanceOf('Zend_Cache_Backend_File', $cache->getBackend());
            $this->assertTrue($cache->save('payload', 'item_a'));
            $this->assertSame('payload', $cache->load('item_a'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testFactoryLowercaseOutput()
    {
        $dir = $this->tempDir('factory-out');
        try {
            $cache = Zend_Cache::factory(
                'output',
                'File',
                array('automatic_cleaning_factor' => 0),
                $this->fileBackendOptions($dir)
            );
            $this->assertInstanceOf('Zend_Cache_Frontend_Output', $cache);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testFactoryTwoLevelsCanonicalNames()
    {
        $slow = $this->tempDir('slow');
        $fast = $this->tempDir('fast');
        try {
            $slowBackend = new Zend_Cache_Backend_File($this->fileBackendOptions($slow));
            $fastBackend = new Zend_Cache_Backend_File($this->fileBackendOptions($fast));
            $opts = array(
                'slow_backend' => $slowBackend,
                'fast_backend' => $fastBackend,
            );
            $cache = Zend_Cache::factory('Core', 'TwoLevels', array(), $opts);
            $this->assertInstanceOf('Zend_Cache_Backend_TwoLevels', $cache->getBackend());

            $cache2 = Zend_Cache::factory('Core', 'two_levels', array(), $opts);
            $this->assertInstanceOf('Zend_Cache_Backend_TwoLevels', $cache2->getBackend());
        } finally {
            $this->removeDir($slow);
            $this->removeDir($fast);
        }
    }

    public function testFactoryWinCacheResolvesClass()
    {
        if (extension_loaded('wincache')) {
            $this->markTestSkipped('WinCache extension loaded');
        }
        try {
            Zend_Cache::factory('Core', 'WinCache');
            $this->fail('Expected exception');
        } catch (Zend_Cache_Exception $e) {
            $this->assertContains('wincache', strtolower($e->getMessage()));
        }
    }

    public function testFactoryRejectsNonInterfaceBackend()
    {
        $this->expectException('Zend_Cache_Exception');
        Zend_Cache::factory('Core', new stdClass());
    }

    public function testFactoryRejectsInvalidNames()
    {
        $dir = $this->tempDir('factory-inv');
        try {
            try {
                Zend_Cache::factory('Core', '../File', array(), $this->fileBackendOptions($dir));
                $this->fail('Expected exception');
            } catch (Zend_Cache_Exception $e) {
                $this->assertContains('Invalid backend name', $e->getMessage());
            }
            try {
                Zend_Cache::factory('bad/name', 'File', array(), $this->fileBackendOptions($dir));
                $this->fail('Expected exception');
            } catch (Zend_Cache_Exception $e) {
                $this->assertContains('Invalid frontend name', $e->getMessage());
            }
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCustomBackendNaming()
    {
        $cache = Zend_Cache::factory(
            'Core',
            'Zend_Cache_Backend_BlackHole',
            array('write_control' => false),
            array(),
            false,
            true,
            true
        );
        $this->assertInstanceOf('Zend_Cache_Backend_BlackHole', $cache->getBackend());
        $this->assertTrue($cache->save('x', 'id'));
        $this->assertFalse($cache->load('id'));
    }

    public function testMissingCustomFile()
    {
        $this->expectException('Zend_Cache_Exception');
        Zend_Cache::factory('Core', 'NoSuchBackend');
    }

    public function testFrontendObjectIsReused()
    {
        $dir = $this->tempDir('factory-fe');
        try {
            $frontend = new Zend_Cache_Core(array('automatic_cleaning_factor' => 0));
            $result = Zend_Cache::factory(
                $frontend,
                'File',
                array(),
                $this->fileBackendOptions($dir)
            );
            $this->assertSame($frontend, $result);
        } finally {
            $this->removeDir($dir);
        }
    }
}
