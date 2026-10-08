<?php

class Zend_Cache_Frontend_OutputTest extends Zend_Cache_TestCase
{
    private function outputFrontend($dir)
    {
        return Zend_Cache::factory(
            'Output',
            'File',
            array('automatic_cleaning_factor' => 0),
            $this->fileBackendOptions($dir)
        );
    }

    public function testMissThenHit()
    {
        $dir = $this->tempDir('out');
        try {
            $fe = $this->outputFrontend($dir);
            $this->assertFalse($fe->start('out_id'));
            ob_start();
            echo 'BODY';
            $fe->end();
            $this->assertSame('BODY', ob_get_clean());
            ob_start();
            $this->assertTrue($fe->start('out_id'));
            $this->assertSame('BODY', ob_get_clean());
            $this->assertSame('BODY', $fe->load('out_id'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testEchoDataFalseAndForcedDatas()
    {
        $dir = $this->tempDir('out-echo');
        try {
            $fe = $this->outputFrontend($dir);
            $fe->start('out_id2');
            echo 'BODY';
            $fe->end();
            $data = $fe->start('out_id2', false, false);
            $this->assertSame('BODY', $data);
            $fe->start('out_id3');
            $fe->end(array(), false, 'FORCED');
            $this->assertSame('FORCED', $fe->load('out_id3'));
        } finally {
            $this->removeDir($dir);
        }
    }

    public function testNestedLifo()
    {
        $dir = $this->tempDir('out-nest');
        try {
            $fe = $this->outputFrontend($dir);
            $fe->start('outer');
            echo 'O';
            $fe->start('inner');
            echo 'I';
            $fe->end();
            $fe->end();
            $this->assertSame('I', $fe->load('inner'));
            $this->assertSame('OI', $fe->load('outer'));
        } finally {
            $this->removeDir($dir);
        }
    }
}
