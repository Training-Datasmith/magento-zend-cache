<?php

class Zend_Cache_Backend_FileTest extends Zend_Cache_TestCase
{
    private function backend($dir, $extra = array())
    {
        return new Zend_Cache_Backend_File(array_merge($this->fileBackendOptions($dir), $extra));
    }

    public function testSaveLoadRemove()
    {
        $dir = $this->tempDir('file');
        try {
            $b = $this->backend($dir);
            $before = time();
            $this->assertTrue($b->save('alpha', 'item_a'));
            $after = time();
            $this->assertSame('alpha', $b->load('item_a'));
            $mtime = $b->test('item_a');
            $this->assertInternalType('integer', $mtime);
            $this->assertGreaterThanOrEqual($before, $mtime);
            $this->assertLessThanOrEqual($after, $mtime);
            $this->assertTrue($b->remove('item_a'));
            $this->assertFalse($b->load('item_a'));
            $this->assertFalse($b->remove('item_a'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testMissIsFalse()
    {
        $dir = $this->tempDir('file-miss');
        try {
            $b = $this->backend($dir);
            $b->save('x', 'other');
            $this->assertFalse($b->load('missing_id'));
            $this->assertFalse($b->test('missing_id'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testSpecificAndInfiniteLifetime()
    {
        $dir = $this->tempDir('file-life');
        try {
            $b = $this->backend($dir);
            $before = time();
            $b->save('v', 'item_a', array(), 10);
            $meta = $b->getMetadatas('item_a');
            $this->assertGreaterThanOrEqual($before + 10, $meta['expire']);
            $this->assertLessThanOrEqual(time() + 10 + 2, $meta['expire']);
            $b->save('v', 'item_b', array(), null);
            $meta2 = $b->getMetadatas('item_b');
            $this->assertSame(9999999999, $meta2['expire']);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testExpireHookAndDoNotTestValidity()
    {
        $dir = $this->tempDir('file-exp');
        try {
            $b = $this->backend($dir);
            $b->save('alpha', 'item_a');
            $b->save('beta', 'item_b');
            $b->___expire('item_a');
            $this->assertFalse($b->load('item_a'));
            $this->assertFalse($b->test('item_a'));
            $this->assertSame('alpha', $b->load('item_a', true));
            $ids = $b->getIds();
            $this->assertNotContains('item_a', $ids);
            $this->assertContains('item_b', $ids);
            foreach ($ids as $id) {
                $this->assertNotContains('internal-metadatas', $id);
            }
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testTouch()
    {
        $dir = $this->tempDir('file-touch');
        try {
            $b = $this->backend($dir);
            $b->save('x', 'item_a');
            $before = $b->getMetadatas('item_a');
            $this->assertTrue($b->touch('item_a', 100));
            $after = $b->getMetadatas('item_a');
            $this->assertSame($before['expire'] + 100, $after['expire']);
            $this->assertFalse($b->touch('missing_id', 5));
            $b->___expire('item_a');
            $this->assertFalse($b->touch('item_a', 5));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCleanModes()
    {
        $dir = $this->tempDir('file-clean');
        try {
            $b = $this->backend($dir);
            $b->save('a', 'r', array('red'));
            $b->save('b', 'rb', array('red', 'blue'));
            $b->save('c', 'g', array('green'));
            $b->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('red', 'blue'));
            $this->assertFalse($b->load('rb'));
            $this->assertSame('a', $b->load('r'));
            $this->assertSame('c', $b->load('g'));

            $b->save('d', 'bonly', array('blue'));
            $b->clean(Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, array('blue'));
            $this->assertFalse($b->load('bonly'));

            $b->save('e', 'nored', array('green'));
            $b->clean(Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG, array('red'));
            $this->assertFalse($b->load('nored'));

            $b->save('f', 'old', array());
            $b->___expire('old');
            $b->clean(Zend_Cache::CLEANING_MODE_OLD);
            $this->assertFalse($b->load('old'));

            $b->clean(Zend_Cache::CLEANING_MODE_ALL);
            $this->assertSame(array(), $b->getIds());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testReadControlDetectsCorruption()
    {
        $dir = $this->tempDir('file-rc');
        try {
            $b = $this->backend($dir);
            $b->save('good', 'item_a');
            $glob = array();
            foreach (new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
            ) as $fileInfo) {
                if ($fileInfo->getFilename() === 'zc_test---item_a') {
                    $glob[] = $fileInfo->getPathname();
                }
            }
            $this->assertNotEmpty($glob);
            file_put_contents($glob[0], 'tampered');
            $this->assertFalse($b->load('item_a'));

            foreach (array('md5', 'crc32', 'strlen', 'adler32') as $type) {
                $sub = $this->tempDir('file-h');
                $b2 = $this->backend($sub, array('read_control_type' => $type));
                $b2->save('payload', 'id');
                $this->assertSame('payload', $b2->load('id'));
                $this->removeDir($sub);
            }
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testInvalidReadControlTypeThrowsOnSave()
    {
        $dir = $this->tempDir('file-badhash');
        try {
            $b = $this->backend($dir, array('read_control_type' => 'nope'));
            $this->expectException('Zend_Cache_Exception');
            $b->save('x', 'id');
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testHashedDirectoryLevel()
    {
        $dir = $this->tempDir('file-hash');
        try {
            $b = $this->backend($dir, array('hashed_directory_level' => 2));
            $b->save('payload', 'hid');
            $this->assertSame('payload', $b->load('hid'));
            $this->assertContains('hid', $b->getIds());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testStringPermAndDeprecatedUmask()
    {
        $dir = $this->tempDir('file-perm');
        try {
            $b = $this->backend($dir, array('cache_file_perm' => '0640'));
            $this->assertSame(0640, $b->getOption('cache_file_perm'));

            $dir2 = $this->tempDir('file-umask');
            $noticed = false;
            set_error_handler(function ($errno, $errstr) use (&$noticed) {
                if ($errno === E_USER_NOTICE && strpos($errstr, 'cache_file_perm') !== false) {
                    $noticed = true;
                }
                return true;
            });
            try {
                $b2 = $this->backend($dir2, array('cache_file_umask' => '0666'));
            } finally {
                restore_error_handler();
            }
            $this->assertTrue($noticed);
            $this->assertSame(0666, $b2->getOption('cache_file_perm'));

            $dir3 = $this->tempDir('file-humask');
            $noticed2 = false;
            set_error_handler(function ($errno, $errstr) use (&$noticed2) {
                if ($errno === E_USER_NOTICE && strpos($errstr, 'hashed_directory_perm') !== false) {
                    $noticed2 = true;
                }
                return true;
            });
            try {
                $b3 = $this->backend($dir3, array('hashed_directory_umask' => '0770'));
            } finally {
                restore_error_handler();
            }
            $this->assertTrue($noticed2);
            $this->assertSame(0770, $b3->getOption('hashed_directory_perm'));
            $this->removeDir($dir2);
            $this->removeDir($dir3);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testRejectsMissingCacheDir()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Backend_File(array('cache_dir' => '/no/such/dir'));
    }

    public function testRejectsBadFileNamePrefix()
    {
        $dir = $this->tempDir('file-bad');
        try {
            $this->expectException('Zend_Cache_Exception');
            new Zend_Cache_Backend_File(array_merge(
                $this->fileBackendOptions($dir),
                array('file_name_prefix' => 'a/b')
            ));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testRejectsSmallMetadatasArrayMaxSize()
    {
        $dir2 = $this->tempDir('file-meta');
        try {
            $this->expectException('Zend_Cache_Exception');
            new Zend_Cache_Backend_File(array_merge(
                $this->fileBackendOptions($dir2),
                array('metadatas_array_max_size' => 9)
            ));
        } finally {
            $this->removeDir($dir2);
        }
    }

    public function testGetFillingPercentage()
    {
        $dir = $this->tempDir('file-fill');
        try {
            $pct = $this->backend($dir)->getFillingPercentage();
            $this->assertInternalType('integer', $pct);
            $this->assertGreaterThanOrEqual(0, $pct);
            $this->assertLessThanOrEqual(100, $pct);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCapabilities()
    {
        $dir = $this->tempDir('file-cap');
        try {
            $cap = $this->backend($dir)->getCapabilities();
            $this->assertTrue($cap['automatic_cleaning']);
            $this->assertTrue($cap['tags']);
            $this->assertTrue($cap['expired_read']);
            $this->assertFalse($cap['priority']);
            $this->assertTrue($cap['infinite_lifetime']);
            $this->assertTrue($cap['get_list']);
        } finally {
            $this->removeDir($dir);
        }
    }
}
