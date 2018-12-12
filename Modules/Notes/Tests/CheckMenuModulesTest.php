<?php

namespace Modules\Notes\Tests;

use App\Models\Client;
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
        $this->assertNotNull($module->get('sidebar'));
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
        $this->assertTrue(in_array('client', $array)); 
    }

    public function testViewsVariableExistsAndDoesNotContainRandomObject()
    {
        $module = Module::find('Notes');
        $array = $module->get('views');
        $this->assertFalse(in_array('foo', $array)); 
    }

    public function testResolvingTabMenuCorrectly()
    {
        $entity = Client::class;
        $tabs = [];

    	foreach (Module::getCached() as $module)
		{
			if(!$module['sidebar']
                && $module['active'] == 1
                && in_array( strtolower( class_basename($entity) ), $module['views']))
			{
                $tabs[] = $module;
			}
		}
        $this->assertFalse($module['sidebar']);
        $this->assertEquals(1,$module['active']);
        $this->assertEquals('client', strtolower(class_basename(Client::class)));
        $this->assertTrue( in_array(strtolower(class_basename(Client::class)), $module['views']) );
        $this->assertEquals(1, count($tabs));
    }
  
}
