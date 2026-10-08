<?php

class Zend_Cache_ManagerTest extends Zend_Cache_TestCase
{
    public function testTemplates()
    {
        $m = new Zend_Cache_Manager();
        $this->assertTrue($m->hasCacheTemplate('default'));
        $this->assertTrue($m->hasCacheTemplate('page'));
        $this->assertTrue($m->hasCacheTemplate('pagetag'));
        $this->assertFalse($m->hasCacheTemplate('nope'));
        $this->assertTrue($m->hasCache('default'));
        $tpl = $m->getCacheTemplate('default');
        $this->assertSame('Core', $tpl['frontend']['name']);
        $this->assertSame('File', $tpl['backend']['name']);
    }

    public function testSetCacheTemplateRejectsBadOptions()
    {
        $m = new Zend_Cache_Manager();
        $this->expectException('Zend_Cache_Exception');
        $m->setCacheTemplate('x', 'nope');
    }

    public function testSetTemplateOptionsRejectsMissingTemplate()
    {
        $m = new Zend_Cache_Manager();
        $this->expectException('Zend_Cache_Exception');
        $m->setTemplateOptions('missing', array());
    }

    public function testMergeKeepsUnspecifiedNames()
    {
        $m = new Zend_Cache_Manager();
        $m->setTemplateOptions('default', array(
            'frontend' => array('options' => array('lifetime' => 12)),
        ));
        $tpl = $m->getCacheTemplate('default');
        $this->assertSame('File', $tpl['backend']['name']);
        $this->assertSame(12, $tpl['frontend']['options']['lifetime']);
    }

    public function testGetCacheDefaultStoresData()
    {
        $dir = $this->tempDir('mgr-def');
        $m = new Zend_Cache_Manager();
        $m->setTemplateOptions('default', array(
            'backend' => array('options' => array('cache_dir' => $dir)),
        ));
        $c1 = $m->getCache('default');
        $this->assertInstanceOf('Zend_Cache_Core', $c1);
        $this->assertTrue($c1->save(array('k' => 'v'), 'id'));
        $this->assertSame(array('k' => 'v'), $c1->load('id'));
        $c2 = $m->getCache('default');
        $this->assertSame($c1, $c2);
        $other = $this->coreWithFileBackend($this->tempDir('mgr-o'));
        $m->setCache('other', $other);
        $this->assertSame($other, $m->getCache('other'));
        $this->assertNull($m->getCache('unknown_name_zc'));
        $this->removeDir($dir);
    }

    public function testPageTemplateWiresStaticAndTagCache()
    {
        $public = $this->tempDir('mgr-pub');
        $tagDir = $this->tempDir('mgr-tag');
        $m = new Zend_Cache_Manager();
        $m->setTemplateOptions('page', array(
            'backend' => array('options' => array('public_dir' => $public)),
            'frontendBackendAutoload' => true,
        ));
        $m->setTemplateOptions('pagetag', array(
            'backend' => array('options' => array('cache_dir' => $tagDir)),
            'frontendBackendAutoload' => true,
        ));
        $page = $m->getCache('page');
        $this->assertInstanceOf('Zend_Cache_Frontend_Capture', $page);
        $backend = $page->getBackend();
        $this->assertInstanceOf('Zend_Cache_Backend_Static', $backend);
        $this->assertInstanceOf('Zend_Cache_Core', $backend->getInnerCache());
        $id = bin2hex('/mgr');
        $page->start($id, array('t'));
        echo 'P';
        ob_end_flush();
        $this->assertSame('P', $backend->load($id));
        $this->removeDir($public);
        $this->removeDir($tagDir);
    }
}
