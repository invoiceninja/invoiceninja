<?php

namespace Modules\Notes\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

class CheckMenuModulesTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();
    }
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testModulesAreDetected()
    {
    	$this->assertGreaterThan(0, Module::count());
    }

    public function testNotesModuleExists()
    {
        $module = Module::find('Notes');
        $this->assertNotNull($module);
        
    }

    public function testNoSideBarVariableExists()
    {
        $module = Module::find('Notes');
        $this->assertNotNull($module->get('no-sidebar'));
    }

    public function testViewsVariableExistsAndIsArray()
    {
        $module = Module::find('Notes');
        $this->assertTrue(is_array($module->get('views')));
    }

    public function testViewsVariableExistsAndContainsClients()
    {
        $module = Module::find('Notes');
        $array = $module->get('views');
        $this->assertTrue(in_array('clients', $array)); 
    }

    public function testViewsVariableExistsAndDoesNotContainRandomObject()
    {
        $module = Module::find('Notes');
        $array = $module->get('views');
        $this->assertFalse(in_array('foo', $array)); 
    }

    public function testResolvingTabMenuCorrectly()
    {
        $entity = 'clients';
        $tabs = [];

    	foreach (Module::getCached() as $module)
		{
			if($module['no-sidebar'] == 1 
                && $module['active']
                && in_array($entity, $module['views']))
			{
                $tabs[] = $module;
			}
		}

        $this->assertEquals(1, count($tabs));
    }
  
}
