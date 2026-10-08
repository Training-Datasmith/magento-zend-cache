<?php

class Zend_Cache_Backend_ZendServerTest extends Zend_Cache_TestCase
{
    protected function setUp()
    {
        parent::setUp();
        Tests_Zc_InMemoryZendServer::resetStore();
    }

    public function testSaveLoadTestRemove()
    {
        $b = new Tests_Zc_InMemoryZendServer();
        $before = time();
        $this->assertTrue($b->save('body', 'item_a'));
        $this->assertSame('body', $b->load('item_a'));
        $mtime = $b->test('item_a');
        $this->assertInternalType('integer', $mtime);
        $this->assertGreaterThanOrEqual($before, $mtime);
        $this->assertTrue($b->remove('item_a'));
        $this->assertFalse($b->load('item_a'));
    }

    public function testCleanAll()
    {
        $b = new Tests_Zc_InMemoryZendServer();
        $b->save('a', 'one');
        $b->save('b', 'two');
        $this->assertTrue($b->clean(Zend_Cache::CLEANING_MODE_ALL));
        $this->assertFalse($b->load('one'));
        $this->assertFalse($b->load('two'));
    }

    public function testInvalidModeThrows()
    {
        $b = new Tests_Zc_InMemoryZendServer();
        $this->expectException('Zend_Cache_Exception');
        $b->clean('nope');
    }

    public function testDiskAndShMemConstructors()
    {
        try {
            new Zend_Cache_Backend_ZendServer_Disk();
            $this->fail('Expected Disk exception');
        } catch (Zend_Cache_Exception $e) {
            $this->assertContains('Zend Server', $e->getMessage());
        }
        try {
            new Zend_Cache_Backend_ZendServer_ShMem();
            $this->fail('Expected ShMem exception');
        } catch (Zend_Cache_Exception $e) {
            $this->assertContains('Zend Server', $e->getMessage());
        }
    }
}
