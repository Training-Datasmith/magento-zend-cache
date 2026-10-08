<?php

class Zend_Cache_Backend_StaticTest extends Zend_Cache_TestCase
{
    private function staticSetup()
    {
        $public = $this->tempDir('static-pub');
        $innerDir = $this->tempDir('static-inner');
        $inner = $this->coreWithFileBackend($innerDir, array('automatic_serialization' => true));
        $static = new Zend_Cache_Backend_Static(array('public_dir' => $public));
        $static->setInnerCache($inner);
        return array($static, $public, $innerDir);
    }

    public function testInnerCacheRequired()
    {
        $public = $this->tempDir('static-no-inner');
        try {
            $static = new Zend_Cache_Backend_Static(array('public_dir' => $public));
            $this->expectException('Zend_Cache_Exception');
            $static->getInnerCache();
        } finally {
            $this->removeDir($public);
        }
    }

    public function testSaveLoadRoundTrip()
    {
        list($static, $public, $innerDir) = $this->staticSetup();
        try {
            $id = bin2hex('/page');
            $this->assertTrue($static->save('<p>hi</p>', $id, array('tag_a')));
            $this->assertFileExists($public . '/page.html');
            $this->assertSame('<p>hi</p>', file_get_contents($public . '/page.html'));
            $this->assertSame('<p>hi</p>', $static->load($id));
            $this->assertFalse($static->load(bin2hex('/other')));
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }

    public function testDisableCachingWritesNothing()
    {
        list($static, $public, $innerDir) = $this->staticSetup();
        try {
            $static->setOption('disable_caching', true);
            $this->assertTrue(is_dir($public));
            $this->assertTrue($static->save('<p>x</p>', bin2hex('/page')));
            $this->assertFileNotExists($public . '/page.html');
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }

    public function testCleanMatchingTagDeletesOnlyThatFile()
    {
        list($static, $public, $innerDir) = $this->staticSetup();
        try {
            $static->save('<p>a</p>', bin2hex('/page'), array('tag_a'));
            $static->save('<p>b</p>', bin2hex('/other'), array('tag_b'));
            $static->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('tag_a'));
            $this->assertFileNotExists($public . '/page.html');
            $this->assertFileExists($public . '/other.html');
            $static->clean(Zend_Cache::CLEANING_MODE_ALL);
            $this->assertFileNotExists($public . '/other.html');
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }

    public function testFileModeOptionValue()
    {
        list($static, $public, $innerDir) = $this->staticSetup();
        try {
            $this->assertSame(0600, $static->getOption('cache_file_perm'));
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }

    public function testEmptyIdUsesRequestUri()
    {
        list($static, $public, $innerDir) = $this->staticSetup();
        try {
            $_SERVER['REQUEST_URI'] = '/from-request';
            $static->save('<p>x</p>', '');
            $this->assertFileExists($public . '/from-request.html');
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }
}
