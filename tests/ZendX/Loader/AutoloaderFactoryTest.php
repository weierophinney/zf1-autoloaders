<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    ZendX_Loader
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/*
 * Preload a number of classes to ensure they're available once we've disabled
 * other autoloaders.
 */
require_once 'PHPUnit/Framework/Constraint/IsEqual.php';
require_once 'PHPUnit/Framework/Constraint/IsInstanceOf.php';
require_once 'PHPUnit/Framework/Constraint/IsNull.php';
require_once 'PHPUnit/Framework/Constraint/IsTrue.php';
require_once 'ZendX/Loader/AutoloaderFactory.php';
require_once 'ZendX/Loader/ClassMapAutoloader.php';
require_once 'ZendX/Loader/StandardAutoloader.php';

/**
 * @package    ZendX_Loader
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @group      Loader
 */
class ZendX_Loader_AutoloaderFactoryTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        // Store original autoloaders
        $this->loaders = spl_autoload_functions();
        if (!is_array($this->loaders)) {
            // spl_autoload_functions does not return empty array when no
            // autoloaders registered...
            $this->loaders = array();
        }

        // Clear out other autoloaders to ensure those being tested are at the 
        // top of the stack
        foreach ($this->loaders as $loader) {
            spl_autoload_unregister($loader);
        }

        // Store original include_path
        $this->includePath = get_include_path();
    }

    public function tearDown()
    {
        ZendX_Loader_AutoloaderFactory::unregisterAutoloaders();
        // Restore original autoloaders
        $loaders = spl_autoload_functions();
        if (is_array($loaders)) {
            foreach ($loaders as $loader) {
                spl_autoload_unregister($loader);
            }
        }

        foreach ($this->loaders as $loader) {
            spl_autoload_register($loader);
        }

        // Restore original include_path
        set_include_path($this->includePath);
    }

    public function testRegisteringValidMapFilePopulatesAutoloader()
    {
        ZendX_Loader_AutoloaderFactory::factory(array(
            'ZendX_Loader_ClassMapAutoloader' => array(
                dirname(__FILE__) . '/_files/goodmap.php',
            ),
        ));
        $loader = ZendX_Loader_AutoloaderFactory::getRegisteredAutoloader('ZendX_Loader_ClassMapAutoloader');
        $map = $loader->getAutoloadMap();
        $this->assertTrue(is_array($map));
        $this->assertEquals(2, count($map));
    }

    public function testFactoryDoesNotRegisterDuplicateAutoloaders()
    {
        ZendX_Loader_AutoloaderFactory::factory(array(
            'ZendX_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'TestPrefix' => dirname(__FILE__) . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        $this->assertEquals(1, count(ZendX_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
        ZendX_Loader_AutoloaderFactory::factory(array(
            'ZendX_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'ZendTest_Loader_TestAsset_TestPlugins' => dirname(__FILE__) . '/TestAsset/TestPlugins',
                ),
            ),
        ));
        $this->assertEquals(1, count(ZendX_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
        $this->assertTrue(class_exists('TestPrefix_NoDuplicateAutoloadersCase'));
        $this->assertTrue(class_exists('ZendTest_Loader_TestAsset_TestPlugins_Foo'));
    }

    public function testCanUnregisterAutoloaders()
    {
        ZendX_Loader_AutoloaderFactory::factory(array(
            'ZendX_Loader_StandardAutoloader' => array(
                'prefixes' => array(
                    'TestPrefix' => dirname(__FILE__) . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        ZendX_Loader_AutoloaderFactory::unregisterAutoloaders();
        $this->assertEquals(0, count(ZendX_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
    }

    public function testCanUnregisterAutoloadersByClassName()
    {
        ZendX_Loader_AutoloaderFactory::factory(array(
            'ZendX_Loader_StandardAutoloader' => array(
                'namespaces' => array(
                    'TestPrefix' => dirname(__FILE__) . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        ZendX_Loader_AutoloaderFactory::unregisterAutoloader('ZendX_Loader_StandardAutoloader');
        $this->assertEquals(0, count(ZendX_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
    }

    public function testCanGetValidRegisteredAutoloader()
    {
        ZendX_Loader_AutoloaderFactory::factory(array(
            'ZendX_Loader_StandardAutoloader' => array(
                'namespaces' => array(
                    'TestPrefix' => dirname(__FILE__) . '/TestAsset/TestPrefix',
                ),
            ),
        ));
        $autoloader = ZendX_Loader_AutoloaderFactory::getRegisteredAutoloader('ZendX_Loader_StandardAutoloader');
        $this->assertInstanceOf('ZendX_Loader_StandardAutoloader', $autoloader);
    }

    public function testDefaultAutoloader()
    {
        ZendX_Loader_AutoloaderFactory::factory();
        $autoloader = ZendX_Loader_AutoloaderFactory::getRegisteredAutoloader('ZendX_Loader_StandardAutoloader');
        $this->assertInstanceOf('ZendX_Loader_StandardAutoloader', $autoloader);
        $this->assertEquals(1, count(ZendX_Loader_AutoloaderFactory::getRegisteredAutoloaders()));
    }

    public function testGetInvalidAutoloaderThrowsException()
    {
        $this->setExpectedException('ZendX_Loader_Exception_InvalidArgumentException');
        $loader = ZendX_Loader_AutoloaderFactory::getRegisteredAutoloader('InvalidAutoloader');
    }

    public function testFactoryWithInvalidArgumentThrowsException()
    {
        $this->setExpectedException('ZendX_Loader_Exception_InvalidArgumentException');
        ZendX_Loader_AutoloaderFactory::factory('InvalidArgument');
    }

    public function testFactoryWithInvalidAutoloaderClassThrowsException()
    {
        $this->setExpectedException('ZendX_Loader_Exception_InvalidArgumentException');
        ZendX_Loader_AutoloaderFactory::factory(array('InvalidAutoloader' => array()));
    }

    public function testCannotBeInstantiatedViaConstructor()
    {
        $reflection = new ReflectionClass('ZendX_Loader_AutoloaderFactory');
        $constructor = $reflection->getConstructor();
        $this->assertNull($constructor);
    }
}
