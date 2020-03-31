<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Designs;

class Plain extends AbstractDesign
{
    public function __construct()
    {
    }


    public function includes()
    {
        return '<title>$number</title>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<link href="$app_url/css/tailwind-1.2.0.css" rel="stylesheet">
<style>
body {font-size:90%}
@page {
    size: auto;
    margin-top: 5mm;
}
.table_header_thead_class { text-align: left; background-color: #e2e8f0 }
.table_header_td_class { padding: 1rem .5rem; }
.table_body_td_class { padding: 1rem; border-bottom-width: 1px; border-top-width: 2px; border-color: #e2e8f0 }
</style>';
    }

    public function header()
    {
        return '<div class="px-12 py-8">
<div class="grid grid-cols-6 gap-1">
    <div class="col-span-2 p-3 bg-red-100">
        $company_logo
    </div>
    <div class="col-span-2 p-3 flex flex-col bg-green-100">
        $company_details
    </div>
    <div class="col-span-2 p-3 flex flex-col bg-blue-100">
        $entity_details
    </div>
</div>';
    }

    public function body()
    {
        return '<div class="flex flex-col mt-8">
    $client_details
</div>
<table class="w-full table-auto mt-8">
    <thead class="text-left bg-gray-300">
        $product_table_header
    </thead>
    <tbody>
        $product_table_body
    </tbody>
</table>
<table class="w-full table-auto mt-8">
    <thead class="text-left bg-gray-300">
        $task_table_header
    </thead>
    <tbody>
        $task_table_body
    </tbody>
</table>

<div class="grid grid-cols-12 gap-1">
    <div class="col-span-6 p-3 bg-red-100">
        <div class="flex flex-col">
            <p>$entity.public_notes</p>
            <div class="pt-4">
                <p class="font-bold">$terms_label</p>
                <p>$terms</p>
            </div>
        </div>
    </div>
    <div class="col-span-6 p-3 bg-green-100">
        <div class="grid grid-cols-2 gap-1">
            <div class="col-span-1 bg-red-200 text-right flex flex-col">
                $discount_label
                $total_tax_labels
                $line_tax_labels
            </div>
            <div class="col-span-1 bg-red-300 text-right flex flex-col">
                $discount
                $total_tax_values
                $line_tax_values
            </div>
        </div>
        <div class="grid grid-cols-2 gap-1 bg-gray-300">
            <div class="col-span-1 text-right flex flex-col">
                $balance_due_label
            </div>
            <div class="col-span-1 text-right flex flex-col">
                $balance_due
            </div>
        </div>
    </div>
</div';
    }

    public function task()
    {
        return '';
    }

    public function product()
    {
        return '';
    }

    public function footer()
    {
        return '';
    }
}
