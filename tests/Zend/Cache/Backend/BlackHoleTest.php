<?php

class Zend_Cache_Backend_BlackHoleTest extends Zend_Cache_TestCase
{
    public function testContract()
    {
        $b = new Zend_Cache_Backend_BlackHole();
        $this->assertTrue($b->save('x', 'id'));
        $this->assertFalse($b->load('id'));
        $this->assertFalse($b->test('id'));
        $this->assertTrue($b->remove('id'));
        $this->assertTrue($b->clean());
        $this->assertSame(array(), $b->getIds());
        $this->assertSame(array(), $b->getTags());
        $this->assertFalse($b->getMetadatas('id'));
        $this->assertFalse($b->touch('id', 1));
        $this->assertSame(0, $b->getFillingPercentage());
        $cap = $b->getCapabilities();
        $this->assertTrue($cap['tags']);
    }

    public function testCoreOverBlackHole()
    {
        $core = new Zend_Cache_Core(array('write_control' => false));
        $core->setBackend(new Zend_Cache_Backend_BlackHole());
        $this->assertTrue($core->save('x', 'id'));
        $this->assertFalse($core->load('id'));
    }
}
