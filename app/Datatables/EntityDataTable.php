<?php

namespace App\Datatables;


class EntityDatatable
{

	private function client_action_items() :Collection
	{

    return collect([
		['action' => 'view_client', 'permission' => 'view_client', 'route' => 'clients.show', 'key' => 'client_id', 'name' => trans('texts.view')],
        ['action' => 'edit_client', 'permission' => 'edit_client', 'route' => 'clients.edit', 'key' => 'client_id', 'name' => trans('texts.edit')],
        ['action' => 'create_task_client_id', 'permission' => 'create_task', 'route' => 'task.create', 'key' => 'client_id', 'name' => trans('texts.new_task')],
        ['action' => 'create_invoice_client_id', 'permission' => 'create_invoice', 'route' => 'invoice.create', 'key' => 'client_id', 'name' => trans('texts.new_invoice')],
        ['action' => 'enter_payment_client_id', 'permission' => 'create_payment', 'route' => 'payment.create', 'key' => 'client_id', 'name' => trans('texts.enter_payment')], 
        ['action' => 'enter_credit_client_id', 'permission' => 'create_credit', 'route' => 'credit.create', 'key' => 'client_id', 'name' => trans('texts.enter_credit')],
        ['action' => 'enter_expense_client_id', 'permission' => 'create_expense', 'route' => 'expense.create', 'key' => 'client_id', 'name' => trans('texts.enter_expense')]
    ]);

	}

	private function checkPermissions() :Collection
    {

        if($is_admin === TRUE)
            return $this->map;

        return $this->map->whereIn('permission', $permission);
        
    }

}