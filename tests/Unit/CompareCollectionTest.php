<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\NumberHelper
 */
class CompareCollectionTest extends TestCase
{

public function setUp()
    {
    
    parent::setUp();

    $this->map = collect([
        ['permission' => 'view_client', 'route' => 'clients.show', 'key' => 'client_id', 'name' => trans('texts.view')],
        ['permission' => 'edit_client', 'route' => 'clients.edit', 'key' => 'client_id', 'name' => trans('texts.edit')],
        ['permission' => 'create_task', 'route' => 'task.create', 'key' => 'client_id', 'name' => trans('texts.new_task')],
        ['permission' => 'create_invoice', 'route' => 'invoice.create', 'key' => 'client_id', 'name' => trans('texts.new_invoice')],
        ['permission' => 'create_payment', 'route' => 'payment.create', 'key' => 'client_id', 'name' => trans('texts.enter_payment')], 
        ['permission' => 'create_credit', 'route' => 'credit.create', 'key' => 'client_id', 'name' => trans('texts.enter_credit')],
        ['permission' => 'create_expense', 'route' => 'expense.create', 'key' => 'client_id', 'name' => trans('texts.enter_expense')]
    ]);

        $this->view_permission = ['view_client'];

        $this->edit_permission = ['view_client', 'edit_client'];
    }

    public function testCompareResultOfComparison()
    {
        $this->assertEquals(7, $this->map->count()); 
    }

    public function testViewPermission()
    {
        $this->assertEquals(1, $this->checkPermissions($this->view_permission)->count());
    }

    public function testViewAndEditPermission()
    {
        $this->assertEquals(2, $this->checkPermissions($this->edit_permission)->count());
    }

    public function checkPermissions($permission)
    {
        return $this->map->whereIn('permission', $permission);
    }

}
