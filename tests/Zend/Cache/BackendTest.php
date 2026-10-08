<?php

class Zend_Cache_BackendTest extends Zend_Cache_TestCase
{
    public function testGetLifetimeDistinguishesFalse()
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->assertSame(3600, $backend->getLifetime(false));
        $this->assertSame(0, $backend->getLifetime(0));
        $this->assertNull($backend->getLifetime(null));
        $this->assertSame(15, $backend->getLifetime(15));
    }

    public function testDirectivesAndOptions()
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $backend->setDirectives(array('lifetime' => 9));
        $this->assertSame(9, $backend->getOption('lifetime'));

        $this->expectException('Zend_Cache_Exception');
        $backend->setDirectives('no');

        $this->expectException('Zend_Cache_Exception');
        $backend->getOption('missing');
    }

    public function testLoggerMustBeZendLog()
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $this->expectException('Zend_Cache_Exception');
        $backend->setDirectives(array('logging' => true, 'logger' => new stdClass()));
    }

    public function testGetTmpDir()
    {
        $backend = new Zend_Cache_Backend_BlackHole();
        $dir = $backend->getTmpDir();
        $this->assertInternalType('string', $dir);
        $this->assertTrue(is_dir($dir));
        $this->assertTrue(is_writable($dir));
    }
}
