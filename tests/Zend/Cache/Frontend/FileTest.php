<?php

class Zend_Cache_Frontend_FileTest extends Zend_Cache_TestCase
{
    private function fileFrontend($master, $opts = array())
    {
        $dir = $this->tempDir('ff');
        $fe = Zend_Cache::factory(
            'File',
            'File',
            array_merge(array(
                'master_files' => array($master),
                'automatic_cleaning_factor' => 0,
            ), $opts),
            $this->fileBackendOptions($dir)
        );
        return array($fe, $dir);
    }

    public function testRequiresMasterFiles()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_File();
    }

    public function testMissingMasterThrows()
    {
        $missing = sys_get_temp_dir() . '/zc-missing-' . uniqid();
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_File(array('master_files' => array($missing)));
    }

    public function testIgnoreMissingMasterAllowsSaveLoad()
    {
        $missing = sys_get_temp_dir() . '/zc-missing-' . uniqid();
        $cacheDir = $this->tempDir('ff-b');
        try {
            $fe = new Zend_Cache_Frontend_File(array(
                'ignore_missing_master_files' => true,
                'master_files' => array($missing),
            ));
            $fe->setBackend(new Zend_Cache_Backend_File($this->fileBackendOptions($cacheDir)));
            $this->assertTrue($fe->save('body', 'cid'));
            $this->assertSame('body', $fe->load('cid'));
        } finally {
            $this->removeDir($cacheDir);
        }
    }

    public function testOrModeInvalidatesWhenAnyMasterIsNewer()
    {
        $dir = $this->tempDir('ff-or');
        $master = $dir . '/m1.txt';
        file_put_contents($master, 'x');
        touch($master, time() - 100);
        list($fe, $cacheDir) = $this->fileFrontend($master, array('master_files_mode' => Zend_Cache_Frontend_File::MODE_OR));
        try {
            $fe->save('cached', 'cid');
            $this->assertInternalType('integer', $fe->test('cid'));
            touch($master, time() + 100);
            $fe->setMasterFile($master);
            clearstatcache();
            $this->assertFalse($fe->test('cid'));
            $this->assertFalse($fe->load('cid'));
        } finally {
            $this->removeDir($dir);
            $this->removeDir($cacheDir);
        }
    }

    public function testAndModeKeepsCacheIfAnyMasterIsOlder()
    {
        $dir = $this->tempDir('ff-and');
        $m1 = $dir . '/a.txt';
        $m2 = $dir . '/b.txt';
        file_put_contents($m1, 'a');
        file_put_contents($m2, 'b');
        touch($m1, time() + 100);
        touch($m2, time() - 100);
        $cacheDir = $this->tempDir('ff-b');
        $fe = Zend_Cache::factory(
            'File',
            'File',
            array(
                'master_files' => array($m1, $m2),
                'master_files_mode' => Zend_Cache_Frontend_File::MODE_AND,
                'automatic_cleaning_factor' => 0,
            ),
            $this->fileBackendOptions($cacheDir)
        );
        try {
            $fe->save('cached', 'cid2');
            $this->assertInternalType('integer', $fe->test('cid2'));
            touch($m2, time() + 200);
            $fe->setMasterFiles(array($m1, $m2));
            clearstatcache();
            $this->assertFalse($fe->test('cid2'));
        } finally {
            $this->removeDir($dir);
            $this->removeDir($cacheDir);
        }
    }
}
