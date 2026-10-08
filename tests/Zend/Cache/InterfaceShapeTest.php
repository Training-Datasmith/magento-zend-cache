<?php

class Zend_Cache_InterfaceShapeTest extends Zend_Cache_TestCase
{
    public function testExtendedBackendsImplementInterface()
    {
        foreach (array(
            'Zend_Cache_Backend_File',
            'Zend_Cache_Backend_BlackHole',
            'Zend_Cache_Backend_Test',
            'Zend_Cache_Backend_TwoLevels',
        ) as $class) {
            $ref = new ReflectionClass($class);
            foreach ($this->extendedMethods() as $method) {
                $this->assertTrue($ref->hasMethod($method), $class . ' missing ' . $method);
            }
        }
    }

    public function testBasicBackendsImplementInterface()
    {
        foreach (array(
            'Zend_Cache_Backend_Static',
            'Zend_Cache_Backend_ZendServer_Disk',
            'Zend_Cache_Backend_ZendServer_ShMem',
        ) as $class) {
            $ref = new ReflectionClass($class);
            foreach ($this->basicMethods() as $method) {
                $this->assertTrue($ref->hasMethod($method), $class . ' missing ' . $method);
            }
        }
    }

    private function basicMethods()
    {
        return array('setDirectives', 'load', 'test', 'save', 'remove', 'clean');
    }

    private function extendedMethods()
    {
        return array_merge($this->basicMethods(), array(
            'getIds', 'getTags', 'getIdsMatchingTags', 'getIdsNotMatchingTags',
            'getIdsMatchingAnyTags', 'getFillingPercentage', 'getMetadatas',
            'touch', 'getCapabilities',
        ));
    }
}
