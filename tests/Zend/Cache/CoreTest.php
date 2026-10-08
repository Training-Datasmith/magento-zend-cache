<?php

class Zend_Cache_CoreTest extends Zend_Cache_TestCase
{
    /** @var Zend_Cache_Core */
    private $core;

    /** @var Zend_Cache_Backend_Test */
    private $backend;

    protected function setUp()
    {
        parent::setUp();
        $this->backend = new Zend_Cache_Backend_Test();
        $this->core = new Zend_Cache_Core(array(
            'automatic_cleaning_factor' => 0,
            'write_control' => false,
        ));
        $this->core->setBackend($this->backend);
    }

    public function testLoadAndTestDelegate()
    {
        $this->assertSame('foo', $this->core->load('any'));
        $this->assertSame(123456, $this->core->test('any'));
        $this->assertFalse($this->core->load('false'));
        $this->assertFalse($this->core->test('false'));
        $methods = array_column($this->backend->getAllLogs(), 'methodName');
        $this->assertContains('get', $methods);
        $this->assertContains('test', $methods);
    }

    public function testCachingDisabledDoesNotTouchBackend()
    {
        $this->core->setOption('caching', false);
        $before = $this->backend->getLogIndex();
        $this->assertFalse($this->core->load('any'));
        $this->assertFalse($this->core->test('any'));
        $this->assertTrue($this->core->save('x', 'id'));
        $this->assertTrue($this->core->remove('id'));
        $this->assertTrue($this->core->clean());
        $this->assertSame($before, $this->backend->getLogIndex());
    }

    public function testSaveRejectsNonStringWithoutSerialization()
    {
        $this->expectException('Zend_Cache_Exception');
        $this->core->save(array(1), 'id');
    }

    public function testAutomaticSerializationRoundTrip()
    {
        $dir = $this->tempDir('core-ser');
        try {
            $core = $this->coreWithFileBackend($dir, array(
                'automatic_serialization' => true,
                'automatic_cleaning_factor' => 0,
            ));
            $data = array('a' => 1);
            $this->assertTrue($core->save($data, 'ser'));
            $this->assertSame($data, $core->load('ser'));
            $raw = $core->load('ser', false, true);
            $this->assertInternalType('string', $raw);
            $this->assertSame($data, unserialize($raw));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testWriteControlMismatchRemoves()
    {
        $this->core->setOption('write_control', true);
        $this->assertFalse($this->core->save('bar', 'wid'));
        $logs = $this->backend->getAllLogs();
        $methods = array_column($logs, 'methodName');
        $this->assertContains('remove', $methods);
        $this->assertTrue($this->core->save('foo', 'wid2'));
    }

    public function testFailedSaveRemoves()
    {
        $this->assertFalse($this->core->save('x', 'abcfalse'));
        $last = $this->backend->getLastLog();
        $this->assertSame('remove', $last['methodName']);
    }

    public function testNullIdReusesLastLoadId()
    {
        $this->core->load('any');
        $this->core->save('foo');
        $saveLog = null;
        foreach ($this->backend->getAllLogs() as $log) {
            if ($log['methodName'] === 'save') {
                $saveLog = $log;
            }
        }
        $this->assertNotNull($saveLog);
        $this->assertSame('any', $saveLog['args'][1]);
    }

    public function testPrefixAppliedAndStripped()
    {
        $this->core->setOption('cache_id_prefix', 'ns_');
        $this->core->save('foo', 'item');
        $saveLog = $this->backend->getLastLog();
        $this->assertSame('ns_item', $saveLog['args'][1]);

        $this->core->setOption('cache_id_prefix', 'prefix_');
        $ids = $this->core->getIds();
        $this->assertContains('id1', $ids);
        $this->assertContains('id2', $ids);

        $this->core->setOption('cache_id_prefix', 'other_');
        $ids2 = $this->core->getIds();
        $this->assertContains('prefix_id1', $ids2);
    }

    public function testIdValidationRejectsSpaces()
    {
        $this->expectException('Zend_Cache_Exception');
        $this->core->load('bad id');
    }

    public function testIdValidationRejectsInternalPrefix()
    {
        $this->expectException('Zend_Cache_Exception');
        $this->core->load('internal-x');
    }

    public function testTagValidation()
    {
        $this->core->save('foo', 'ok', array('ok'));
        $this->expectException('Zend_Cache_Exception');
        $this->core->save('foo', 'ok2', array('bad tag'));
    }

    public function testCleanRejectsUnknownMode()
    {
        $this->expectException('Zend_Cache_Exception');
        $this->core->clean('nope');
    }

    public function testAutomaticCleaningFactor()
    {
        $this->core->setOption('automatic_cleaning_factor', 0);
        $this->core->save('foo', 'id');
        $hasClean = false;
        foreach ($this->backend->getAllLogs() as $log) {
            if ($log['methodName'] === 'clean') {
                $hasClean = true;
            }
        }
        $this->assertFalse($hasClean);

        $this->backend = new Zend_Cache_Backend_Test();
        $this->core->setBackend($this->backend);
        $this->core->setOption('automatic_cleaning_factor', 1);
        $this->core->save('foo', 'id2');
        $cleaned = false;
        foreach ($this->backend->getAllLogs() as $log) {
            if ($log['methodName'] === 'clean' && $log['args'][0] === Zend_Cache::CLEANING_MODE_OLD) {
                $cleaned = true;
            }
        }
        $this->assertTrue($cleaned);
    }

    public function testExtendedApiRequiresExtendedBackend()
    {
        $dir = $this->tempDir('core-static');
        $public = $this->tempDir('core-pub');
        try {
            $static = new Zend_Cache_Backend_Static(array('public_dir' => $public));
            $innerDir = $this->tempDir('core-inner');
            $inner = $this->coreWithFileBackend($innerDir);
            $static->setInnerCache($inner);
            $core = new Zend_Cache_Core();
            $core->setBackend($static);
            $this->expectException('Zend_Cache_Exception');
            $core->getIds();
        } finally {
            $this->removeDir($dir);
            $this->removeDir($public);
        }
    }

    public function testExtendedApiStripsPrefixAndHonorsCapabilities()
    {
        $this->core->setOption('cache_id_prefix', 'prefix_');
        $this->assertSame(array('id1', 'id2'), $this->core->getIdsMatchingTags(array('tag1', 'tag2')));
        $this->assertSame(50, $this->core->getFillingPercentage());
        $this->assertTrue($this->core->touch('id', 5));
    }

    public function testSetLifetimeReachesBackend()
    {
        $dir = $this->tempDir('core-life');
        try {
            $core = $this->coreWithFileBackend($dir);
            $core->setLifetime(42);
            $this->assertSame(42, $core->getBackend()->getOption('lifetime'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testOptionsRoundTripAndLogger()
    {
        $this->core->setOption('LIFETIME', 7);
        $this->assertSame(7, $this->core->getOption('lifetime'));

        $core = new Zend_Cache_Core(new Zend_Config(array('lifetime' => 99)));
        $this->assertSame(99, $core->getOption('lifetime'));

        $logFile = $this->tempDir('log') . '/log.txt';
        file_put_contents($logFile, '');
        $logger = new Zend_Log(new Zend_Log_Writer_Stream($logFile));
        $this->core->setOption('logging', true);
        $this->core->setOption('logger', $logger);
        $this->core->save('x', 'abcfalse');
        $contents = file_get_contents($logFile);
        $this->assertNotSame('', $contents);
        $this->assertContains('failed to save', $contents);
        $this->removeDir(dirname($logFile));
    }
}
