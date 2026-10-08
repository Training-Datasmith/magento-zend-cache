<?php

class Zend_Cache_Backend_MissingExtensionTest extends Zend_Cache_TestCase
{
    public function testApcConstructor()
    {
        if (extension_loaded('apc')) {
            $this->markTestSkipped('apc loaded');
        }
        $this->expectExtensionGuard('Zend_Cache_Backend_Apc', 'apc');
    }

    public function testMemcachedConstructor()
    {
        if (extension_loaded('memcache')) {
            $this->markTestSkipped('memcache loaded');
        }
        $this->expectExtensionGuard('Zend_Cache_Backend_Memcached', 'memcache');
    }

    public function testLibmemcachedConstructor()
    {
        if (extension_loaded('memcached')) {
            $this->markTestSkipped('memcached loaded');
        }
        $this->expectExtensionGuard('Zend_Cache_Backend_Libmemcached', 'memcached');
    }

    public function testXcacheConstructor()
    {
        if (extension_loaded('xcache')) {
            $this->markTestSkipped('xcache loaded');
        }
        $this->expectExtensionGuard('Zend_Cache_Backend_Xcache', 'xcache');
    }

    public function testWinCacheConstructor()
    {
        if (extension_loaded('wincache')) {
            $this->markTestSkipped('wincache loaded');
        }
        $this->expectExtensionGuard('Zend_Cache_Backend_WinCache', 'wincache');
    }

    public function testZendPlatformConstructor()
    {
        if (function_exists('accelerator_license_info')) {
            $this->markTestSkipped('Zend Platform present');
        }
        $this->expectExtensionGuard('Zend_Cache_Backend_ZendPlatform', 'Zend Platform');
    }

    private function expectExtensionGuard($class, $needle)
    {
        try {
            new $class();
            $this->fail('Expected exception');
        } catch (Zend_Cache_Exception $e) {
            $this->assertContains($needle, $e->getMessage());
        }
    }
}
