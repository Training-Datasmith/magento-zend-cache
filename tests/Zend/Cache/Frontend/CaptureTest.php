<?php

class Zend_Cache_Frontend_CaptureTest extends Zend_Cache_TestCase
{
    private function captureSetup()
    {
        $public = $this->tempDir('cap-pub');
        $innerDir = $this->tempDir('cap-inner');
        $inner = $this->coreWithFileBackend($innerDir, array('automatic_serialization' => true));
        $static = new Zend_Cache_Backend_Static(array('public_dir' => $public));
        $static->setInnerCache($inner);
        $fe = new Zend_Cache_Frontend_Capture(array('automatic_cleaning_factor' => 0));
        $fe->setBackend($static);
        return array($fe, $static, $public, $innerDir);
    }

    public function testFlushSavesBuffer()
    {
        list($fe, $static, $public, $innerDir) = $this->captureSetup();
        try {
            $id = bin2hex('/cap');
            $fe->start($id, array('tag_a'));
            echo 'CAP';
            ob_end_flush();
            $this->assertSame('CAP', $static->load($id));
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }

    public function testExtensionIsStored()
    {
        list($fe, $static, $public, $innerDir) = $this->captureSetup();
        try {
            $id = bin2hex('/cap2');
            $fe->start($id, array('t'), 'css');
            echo 'CAP';
            ob_end_flush();
            $this->assertFileExists($public . '/cap2.css');
            $this->assertSame('CAP', file_get_contents($public . '/cap2.css'));
            $this->assertTrue($static->test($id));
        } finally {
            $this->removeDir($public);
            $this->removeDir($innerDir);
        }
    }

    public function testFlushWithoutStartThrows()
    {
        list($fe) = $this->captureSetup();
        $this->expectException('Zend_Cache_Exception');
        $fe->_flush('x');
    }
}
