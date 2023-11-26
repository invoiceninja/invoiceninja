<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\Decorators;

use App\Models\Expense;

class ExpenseDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $expense = false;

        if($entity instanceof Expense) {
            $expense = $entity;
        } elseif($entity->expense) {
            $expense = $entity->expense;
        }

        if($expense && method_exists($this, $key)) {
            return $this->{$key}($expense);
        }

        return '';

    }

    public function amount(Expense $expense)
    {
        return $expense->amount ?? 0;
    }
    public function category_id(Expense $expense)
    {
        return $expense->category ? $expense->category->name : '';
    }
    public function client_id(Expense $expense)
    {
        return $expense->client ? $expense->client->present()->name() : '';
    }
    public function custom_value1(Expense $expense)
    {
        return $expense->custom_value1 ?? '';
    }
    public function custom_value2(Expense $expense)
    {
        return $expense->custom_value2 ?? '';
    }
    public function custom_value3(Expense $expense)
    {
        return $expense->custom_value3 ?? '';
    }
    public function custom_value4(Expense $expense)
    {
        return $expense->custom_value4 ?? '';
    }
    public function currency_id(Expense $expense)
    {
        return $expense->currency ? $expense->currency->code : $expense->company->currency()->code;
    }
    public function date(Expense $expense)
    {
        return $expense->date ?? '';
    }
    public function exchange_rate(Expense $expense)
    {
        return $expense->exchange_rate ?? 0;
    }
    public function foreign_amount(Expense $expense)
    {
        return $expense->foreign_amount ?? 0;
    }
    public function invoice_currency_id(Expense $expense)
    {
        return $expense->invoice_currency ? $expense->invoice_currency->code : $expense->company->currency()->code;
    }
    public function payment_date(Expense $expense)
    {
        return $expense->payment_date ?? '';
    }
    public function number(Expense $expense)
    {
        return $expense->number ?? '';
    }
    public function payment_type_id(Expense $expense)
    {
        return $expense->payment_type ? $expense->payment_type->name : '';
    }
    public function private_notes(Expense $expense)
    {
        return $expense->private_notes ?? '';
    }
    public function project_id(Expense $expense)
    {
        return $expense->project ? $expense->project->name : '';
    }
    public function public_notes(Expense $expense)
    {
        return $expense->public_notes ?? '';
    }
    public function tax_amount1(Expense $expense)
    {
        return $expense->tax_amount1 ?? 0;
    }
    public function tax_amount2(Expense $expense)
    {
        return $expense->tax_amount2 ?? 0;
    }
    public function tax_amount3(Expense $expense)
    {
        return $expense->tax_amount3 ?? 0;
    }
    public function tax_name1(Expense $expense)
    {
        return $expense->tax_name1 ?? '';
    }
    public function tax_name2(Expense $expense)
    {
        return $expense->tax_name2 ?? '';
    }
    public function tax_name3(Expense $expense)
    {
        return $expense->tax_name3 ?? '';
    }
    public function tax_rate1(Expense $expense)
    {
        return $expense->tax_rate1 ?? 0;
    }
    public function tax_rate2(Expense $expense)
    {
        return $expense->tax_rate2 ?? 0;
    }
    public function tax_rate3(Expense $expense)
    {
        return $expense->tax_rate3 ?? 0;
    }
    public function transaction_reference(Expense $expense)
    {
        return $expense->transaction_reference ?? '';
    }
    public function vendor_id(Expense $expense)
    {
        return $expense->vendor ? $expense->vendor->name : '';
    }
    public function invoice_id(Expense $expense)
    {
        return $expense->invoice ? $expense->invoice->number : '';
    }
    public function user(Expense $expense)
    {
        return $expense->user ? $expense->user->present()->name() : '';
    }
    public function assigned_user(Expense $expense)
    {
        return $expense->assigned_user ? $expense->assigned_user->present()->name() : '';
    }

}
