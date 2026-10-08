<?php

class Zend_Cache_Backend_TwoLevelsTest extends Zend_Cache_TestCase
{
    private function twoLevels($slowDir, $fastDir, $opts = array())
    {
        $slow = new Zend_Cache_Backend_File($this->fileBackendOptions($slowDir));
        $fast = new Zend_Cache_Backend_File($this->fileBackendOptions($fastDir));
        return new Zend_Cache_Backend_TwoLevels(array_merge(array(
            'slow_backend' => $slow,
            'fast_backend' => $fast,
            'stats_update_factor' => 1,
        ), $opts));
    }

    public function testPriorityTenWritesBothAndLoadReturnsData()
    {
        $slow = $this->tempDir('tl-slow');
        $fast = $this->tempDir('tl-fast');
        try {
            $tl = $this->twoLevels($slow, $fast);
            $this->assertTrue($tl->save('payload', 'item_a', array('t'), 30, 10));
            $slowB = new Zend_Cache_Backend_File($this->fileBackendOptions($slow));
            $fastB = new Zend_Cache_Backend_File($this->fileBackendOptions($fast));
            $this->assertNotFalse($slowB->load('item_a'));
            $this->assertNotFalse($fastB->load('item_a'));
            $wrapped = unserialize($fastB->load('item_a'));
            $this->assertSame('payload', $wrapped['data']);
            $this->assertSame(10, $wrapped['priority']);
            $this->assertSame(30, $wrapped['lifetime']);
            $this->assertSame('payload', $tl->load('item_a'));
        } finally {
            $this->removeDir($slow);
            $this->removeDir($fast);
        }
    }

    public function testPriorityZeroWritesSlowOnly()
    {
        $slow = $this->tempDir('tl-slow2');
        $fast = $this->tempDir('tl-fast2');
        try {
            $tl = $this->twoLevels($slow, $fast);
            $tl->save('payload', 'item_a', array(), 30, 0);
            $slowB = new Zend_Cache_Backend_File($this->fileBackendOptions($slow));
            $fastB = new Zend_Cache_Backend_File($this->fileBackendOptions($fast));
            $this->assertNotFalse($slowB->load('item_a'));
            $this->assertFalse($fastB->load('item_a'));
            $this->assertSame('payload', $tl->load('item_a'));
        } finally {
            $this->removeDir($slow);
            $this->removeDir($fast);
        }
    }

    public function testAutoFillCopiesSlowHitIntoFast()
    {
        $slow = $this->tempDir('tl-slow3');
        $fast = $this->tempDir('tl-fast3');
        try {
            $tl = $this->twoLevels($slow, $fast, array('auto_fill_fast_cache' => true));
            $tl->save('payload', 'item_a', array(), 30, 0);
            $tl->load('item_a');
            $fastB = new Zend_Cache_Backend_File($this->fileBackendOptions($fast));
            $this->assertNotFalse($fastB->load('item_a'));

            $slow4 = $this->tempDir('tl-slow4');
            $fast4 = $this->tempDir('tl-fast4');
            $tl2 = $this->twoLevels($slow4, $fast4, array('auto_fill_fast_cache' => false));
            $tl2->save('payload', 'item_b', array(), 30, 0);
            $tl2->load('item_b');
            $fastB2 = new Zend_Cache_Backend_File($this->fileBackendOptions($fast4));
            $this->assertFalse($fastB2->load('item_b'));
            $this->removeDir($slow4);
            $this->removeDir($fast4);
        } finally {
            $this->removeDir($slow);
            $this->removeDir($fast);
        }
    }

    public function testCleanMatchingTagRemovesFromBoth()
    {
        $slow = $this->tempDir('tl-slow5');
        $fast = $this->tempDir('tl-fast5');
        try {
            $tl = $this->twoLevels($slow, $fast);
            $tl->save('a', 'tagged', array('red'), 30, 10);
            $tl->save('b', 'plain', array(), 30, 10);
            $tl->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('red'));
            $slowB = new Zend_Cache_Backend_File($this->fileBackendOptions($slow));
            $fastB = new Zend_Cache_Backend_File($this->fileBackendOptions($fast));
            $this->assertFalse($slowB->load('tagged'));
            $this->assertFalse($fastB->load('tagged'));
            $this->assertNotFalse($slowB->load('plain'));
        } finally {
            $this->removeDir($slow);
            $this->removeDir($fast);
        }
    }

    public function testRejectsNonExtendedSlowBackend()
    {
        $public = $this->tempDir('tl-pub');
        try {
            $this->expectException('Zend_Cache_Exception');
            new Zend_Cache_Backend_TwoLevels(array(
                'slow_backend' => 'Static',
                'fast_backend' => 'File',
                'slow_backend_options' => array('public_dir' => $public),
                'fast_backend_options' => $this->fileBackendOptions($this->tempDir('tl-f')),
            ));
        } finally {
            $this->removeDir($public);
        }
    }

    public function testGetIdsFromSlow()
    {
        $slow = $this->tempDir('tl-slow6');
        $fast = $this->tempDir('tl-fast6');
        try {
            $tl = $this->twoLevels($slow, $fast);
            $tl->save('a', 'one', array(), 30, 10);
            $tl->save('b', 'two', array(), 30, 10);
            $ids = $tl->getIds();
            $this->assertContains('one', $ids);
            $this->assertContains('two', $ids);
        } finally {
            $this->removeDir($slow);
            $this->removeDir($fast);
        }
    }
}
