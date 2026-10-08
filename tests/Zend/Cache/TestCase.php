<?php

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class Zend_Cache_TestCase extends PhpUnitTestCase
{
    /** @var array */
    protected $savedServer;

    /** @var int */
    protected $obLevel;

    protected function setUp()
    {
        parent::setUp();
        $this->savedServer = array(
            'REQUEST_URI' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
            'GET' => $_GET,
            'POST' => $_POST,
            'COOKIE' => $_COOKIE,
            'FILES' => $_FILES,
            'SESSION' => isset($_SESSION) ? $_SESSION : null,
        );
        $this->obLevel = ob_get_level();
    }

    protected function tearDown()
    {
        while (ob_get_level() > $this->obLevel) {
            ob_end_clean();
        }
        $_GET = $this->savedServer['GET'];
        $_POST = $this->savedServer['POST'];
        $_COOKIE = $this->savedServer['COOKIE'];
        $_FILES = $this->savedServer['FILES'];
        if ($this->savedServer['SESSION'] === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->savedServer['SESSION'];
        }
        if ($this->savedServer['REQUEST_URI'] === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $this->savedServer['REQUEST_URI'];
        }
        parent::tearDown();
    }

    protected function tempDir($prefix = 'zc')
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . '-' . bin2hex(random_bytes(8));
        $this->assertTrue(mkdir($dir, 0700, true));
        return $dir;
    }

    protected function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }
        rmdir($dir);
    }

    protected function fileBackendOptions($cacheDir)
    {
        return array(
            'cache_dir' => $cacheDir,
            'file_name_prefix' => 'zc_test',
            'automatic_cleaning_factor' => 0,
        );
    }

    protected function coreWithFileBackend($cacheDir, $coreOptions = array())
    {
        $backend = new Zend_Cache_Backend_File($this->fileBackendOptions($cacheDir));
        $core = new Zend_Cache_Core($coreOptions);
        $core->setBackend($backend);
        return $core;
    }
}
