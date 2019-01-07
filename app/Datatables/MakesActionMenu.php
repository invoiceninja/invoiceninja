<?php

namespace App\Datatables;

use Illuminate\Support\Collection;

trait MakesActionMenu
{
    /**
     * Returns all possible datatable actions
     * 
     * @return Collection collection instance of action items
     */
	public function actions() :Collection
	{

    return collect([
		['action' => 'view_client_client_id', 'permission' => 'view_client', 'route' => 'clients.show', 'key' => 'client_id', 'name' => trans('texts.view')],
        ['action' => 'edit_client_client_id', 'permission' => 'edit_client', 'route' => 'clients.edit', 'key' => 'client_id', 'name' => trans('texts.edit')],
        ['action' => 'create_task_client_id', 'permission' => 'create_task', 'route' => 'tasks.create', 'key' => 'client_id', 'name' => trans('texts.new_task')],
        ['action' => 'create_invoice_client_id', 'permission' => 'create_invoice', 'route' => 'invoices.create', 'key' => 'client_id', 'name' => trans('texts.new_invoice')],
        ['action' => 'enter_payment_client_id', 'permission' => 'create_payment', 'route' => 'payments.create', 'key' => 'client_id', 'name' => trans('texts.enter_payment')], 
        ['action' => 'enter_credit_client_id', 'permission' => 'create_credit', 'route' => 'credits.create', 'key' => 'client_id', 'name' => trans('texts.enter_credit')],
        ['action' => 'enter_expense_client_id', 'permission' => 'create_expense', 'route' => 'expenses.create', 'key' => 'client_id', 'name' => trans('texts.enter_expense')]
    ]);

	}

	/**
     * Checks the user permissions against the collection and returns
     * a Collection of available actions\.
     * 
     * @param  Collection $actions  collection of possible actions
     * @param  bool       $is_admin boolean defining if user is an administrator
     * @return Collection collection of filtered actions
     */
	private function checkPermissions(Collection $actions, array $permissions, bool $is_admin) :Collection
    {

        if($is_admin === TRUE)
            return $actions;

        return $actions->whereIn('permission', $permissions);
        
    }

    /**
     * Filters the main actions collection down to the requested
     * actions for this menu
     * 
     * @param  array  $actions     Array of actions requested
     * @param  array  $permissions Array of user permissions
     * @param  bool   $is_admin    Boolean is_admin
     * @return Collection collection of filtered actions available to the user
     */
    public function filterActions(array $actions, array $permissions, bool $is_admin) :Collection
    {

    	return $this->checkPermissions($this->actions()->whereIn('action', $actions), $permissions, $is_admin);
    }
}