<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\Traits\MakesHash;
use Tests\TestCase;

/**
 * @test
 * @covers  App\Utils\Number
 */
class CompareCollectionTest extends TestCase
{
    use MakesHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->map = collect([
            ['action' => 'view_client_client_id', 'permission' => 'view_client', 'route' => 'clients.show', 'key' => 'client_id', 'name' => trans('texts.view')],
            ['action' => 'edit_client_client_id', 'permission' => 'edit_client', 'route' => 'clients.edit', 'key' => 'client_id', 'name' => trans('texts.edit')],
            ['action' => 'create_task_client_id', 'permission' => 'create_task', 'route' => 'task.create', 'key' => 'client_id', 'name' => trans('texts.new_task')],
            ['action' => 'create_invoice_client_id', 'permission' => 'create_invoice', 'route' => 'invoice.create', 'key' => 'client_id', 'name' => trans('texts.new_invoice')],
            ['action' => 'enter_payment_client_id', 'permission' => 'create_payment', 'route' => 'payment.create', 'key' => 'client_id', 'name' => trans('texts.enter_payment')],
            ['action' => 'enter_credit_client_id', 'permission' => 'create_credit', 'route' => 'credit.create', 'key' => 'client_id', 'name' => trans('texts.enter_credit')],
            ['action' => 'enter_expense_client_id', 'permission' => 'create_expense', 'route' => 'expense.create', 'key' => 'client_id', 'name' => trans('texts.enter_expense')],
        ]);

        $this->view_permission = ['view_client'];

        $this->edit_permission = ['view_client', 'edit_client'];

        $this->is_admin = true;

        $this->is_not_admin = false;
    }

    public function testCollectionCreation()
    {
        $collection = collect();

        $invoice_ids = '';

        $invoices = explode(",", $invoice_ids);

        if (count($invoices) >= 1) {
            foreach ($invoices as $invoice) {
                if (is_string($invoice) && strlen($invoice) > 1) {
                    $collection->push($this->decodePrimaryKey($invoice));
                }
            }
        }

        $this->assertEquals(0, $collection->count());
    }

    public function testCompareResultOfComparison()
    {
        $this->assertEquals(7, $this->map->count());
    }

    public function testViewPermission()
    {
        $this->assertEquals(1, $this->checkPermissions($this->view_permission, $this->is_not_admin)->count());
    }

    public function testViewAndEditPermission()
    {
        $this->assertEquals(2, $this->checkPermissions($this->edit_permission, $this->is_not_admin)->count());
    }

    public function testAdminPermissions()
    {
        $this->assertEquals(7, $this->checkPermissions($this->view_permission, $this->is_admin)->count());
    }

    public function testActionViewClientFilter()
    {
        $actions = [
            'view_client_client_id',
        ];

        $this->assertEquals(1, $this->map->whereIn('action', $actions)->count());
    }

    public function testNoActionClientFilter()
    {
        $actions = [
            '',
        ];

        $this->assertEquals(0, $this->map->whereIn('action', $actions)->count());
    }

    public function testActionsAndPermissionsFilter()
    {
        $actions = [
            'view_client_client_id',

        ];

        $this->filterActions($actions);

        $this->assertEquals(1, $this->checkPermissions($this->view_permission, $this->is_not_admin)->count());
    }

    public function testActionAndPermissionsFilterFailure()
    {
        $actions = [
            'edit_client_client_id',

        ];

        $data = $this->filterActions($actions);

        $this->assertEquals(0, $data->whereIn('permission', $this->view_permission)->count());
    }

    public function testEditActionAndPermissionsFilter()
    {
        $actions = [
            'edit_client_client_id',

        ];

        $data = $this->filterActions($actions);
        $this->assertEquals(1, $data->whereIn('permission', $this->edit_permission)->count());
    }

    public function checkPermissions($permission, $is_admin)
    {
        if ($is_admin === true) {
            return $this->map;
        }

        return $this->map->whereIn('permission', $permission);
    }

    public function filterActions($actions)
    {
        return $this->map->whereIn('action', $actions);
    }
}
