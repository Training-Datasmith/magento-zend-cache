<?php

error_reporting(-1);
ini_set('display_errors', '1');

require dirname(__DIR__) . '/vendor/autoload.php';

require __DIR__ . '/Zend/Cache/TestCase.php';
require __DIR__ . '/fixtures/functions.php';
require __DIR__ . '/fixtures/ClassFixture.php';
require __DIR__ . '/fixtures/InMemoryZendServer.php';

if (!class_exists('Zend_Config', false)) {
    class Zend_Config
    {
        private $data;

        public function __construct(array $data)
        {
            $this->data = $data;
        }

        public function toArray()
        {
            return $this->data;
        }
    }
}
