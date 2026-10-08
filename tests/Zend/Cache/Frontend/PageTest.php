<?php

class Zend_Cache_Frontend_PageTest extends Zend_Cache_TestCase
{
    private function pageFrontend($dir, $opts = array())
    {
        return Zend_Cache::factory(
            'Page',
            'File',
            array_merge(array('automatic_cleaning_factor' => 0), $opts),
            $this->fileBackendOptions($dir)
        );
    }

    public function testHttpConditionalThrows()
    {
        $dir = $this->tempDir('page-hc');
        try {
            $this->expectException('Zend_Cache_Exception');
            new Zend_Cache_Frontend_Page(array('http_conditional' => true));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testDefaultOptionsMustBeArray()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_Page(array('default_options' => 'nope'));
    }

    public function testRegexpsMustBeArray()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_Page(array('regexps' => 'nope'));
    }

    public function testRegexpsValuesMustBeArrays()
    {
        $this->expectException('Zend_Cache_Exception');
        new Zend_Cache_Frontend_Page(array('regexps' => array('^/x' => 'not-array')));
    }

    public function testCacheDisabledByDefaultWhenGetPresent()
    {
        $dir = $this->tempDir('page-get');
        try {
            $_SERVER['REQUEST_URI'] = '/item';
            $_GET = array('a' => '1');
            $fe = $this->pageFrontend($dir);
            $level = ob_get_level();
            $this->assertFalse($fe->start(false, true));
            $this->assertSame($level, ob_get_level());
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testRegexpLowercaseOverrides()
    {
        $dir = $this->tempDir('page-re');
        try {
            $_SERVER['REQUEST_URI'] = '/item';
            $_GET = array('a' => '1');
            $fe = $this->pageFrontend($dir, array(
                'regexps' => array(
                    '^/item' => array(
                        'cache_with_get_variables' => true,
                        'make_id_with_get_variables' => false,
                    ),
                ),
            ));
            $this->assertFalse($fe->start(false, true));
            echo 'BODY';
            ob_end_flush();
            $id = md5('/item');
            $loaded = $fe->load($id);
            $this->assertInternalType('array', $loaded);
            $this->assertSame('BODY', $loaded['data']);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testContentTypeMemorizationFalseKeepsHeaders()
    {
        $dir = $this->tempDir('page-ct');
        try {
            $fe = $this->pageFrontend($dir, array(
                'memorize_headers' => array('Accept'),
                'content_type_memorization' => false,
            ));
            $headers = $fe->getOption('memorize_headers');
            $this->assertSame(array('Accept'), $headers);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testContentTypeMemorizationAddsHeaderName()
    {
        $dir = $this->tempDir('page-ct2');
        try {
            $fe = $this->pageFrontend($dir, array('content_type_memorization' => true));
            $headers = $fe->getOption('memorize_headers');
            $this->assertContains('Content-Type', $headers);
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testCancelSkipsSave()
    {
        $dir = $this->tempDir('page-cancel');
        try {
            $_SERVER['REQUEST_URI'] = '/cancel';
            $fe = $this->pageFrontend($dir, array(
                'default_options' => array('cache_with_get_variables' => true),
            ));
            $fe->start(false, true);
            echo 'BODY';
            $fe->cancel();
            ob_end_flush();
            $this->assertFalse($fe->load(md5('/cancel')));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testDebugHeader()
    {
        $dir = $this->tempDir('page-debug');
        try {
            $_SERVER['REQUEST_URI'] = '/dbg';
            $fe = $this->pageFrontend($dir, array(
                'debug_header' => true,
                'default_options' => array('cache_with_get_variables' => true),
            ));
            $fe->start(false, true);
            echo 'BODY';
            ob_end_flush();
            ob_start();
            $fe->start(false, true);
            $out = ob_get_clean();
            $this->assertContains('DEBUG HEADER', $out);
            $this->assertContains('BODY', $out);
        } finally {
            $this->removeDir($dir);
        }
    }
}
