<?php

namespace App\Utils;

use Illuminate\Support\Collection;

class MenuPermissions
{

	protected $permission;

	protected $is_admin;

	protected $map;

 	public function __construct(array $permission, bool $is_admin)
    {
        $this->permission = $permission;
        $this->is_admin = $is_admin;
        $this->map = $this->action_items();
    }

	private function action_items() :Collection
	{
    
    return collect([
		['permission' => 'view_client', 'route' => 'clients.show', 'key' => 'client_id', 'name' => trans('texts.view')],
        ['permission' => 'edit_client', 'route' => 'clients.edit', 'key' => 'client_id', 'name' => trans('texts.edit')],
        ['permission' => 'create_task', 'route' => 'task.create', 'key' => 'client_id', 'name' => trans('texts.new_task')],
        ['permission' => 'create_invoice', 'route' => 'invoice.create', 'key' => 'client_id', 'name' => trans('texts.new_invoice')],
        ['permission' => 'create_payment', 'route' => 'payment.create', 'key' => 'client_id', 'name' => trans('texts.enter_payment')], 
        ['permission' => 'create_credit', 'route' => 'credit.create', 'key' => 'client_id', 'name' => trans('texts.enter_credit')],
        ['permission' => 'create_expense', 'route' => 'expense.create', 'key' => 'client_id', 'name' => trans('texts.enter_expense')]
    ]);

	}

	public function checkPermissions() :Collection
    {

        if($is_admin === TRUE)
            return $this->map;

        return $this->map->whereIn('permission', $permission);
        
    }
}
