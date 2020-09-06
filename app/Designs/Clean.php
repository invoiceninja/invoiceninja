<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Designs;

class Clean extends AbstractDesign
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
    @page
    {
        size: auto;
        margin-top: 5mm;
    }
    .table_header_thead_class { text-align: left; }
    .table_header_td_class { padding: .5rem 1rem;}
    .table_body_td_class { border-bottom-width: 1px; border-top-width: 1px; border-color: #cbd5e0; padding: 1rem;}
    $custom_css
</style>';
    }

    public function header()
    {
        return '<div class="px-12 my-10">
<div class="flex items-center">
    <div class="w-1/3">
        <div class="h-14 w-14">$company_logo</div>
    </div>
    <div class="w-auto flex">
        <div class="mr-10 text-gray-600 flex flex-col flex-wrap">
            $company_details
        </div>
        <div class="ml-5 text-gray-600 flex flex-col flex-wrap">
            $company_address
        </div>
    </div>
</div>';
    }

    public function body()
    {
        return '<h1 class="mt-12 uppercase text-2xl text-blue-500 ml-4">
    $entity_label
</h1>

<div class="border-b border-gray-400"></div>

<div class="ml-4 py-4">
    <div class="flex">
        <div class="w-40 flex flex-col flex-wrap">
            $entity_labels
        </div>
        <div class="w-48 flex flex-col flex-wrap">
            $entity_details
        </div>
        <div class="w-56 flex flex-col flex-wrap">
            $client_details
        </div>
    </div>
</div>
<div class="border-b border-gray-400"></div>
<table class="w-full table-auto mt-8">
    <thead class="text-left">
        $product_table_header
    </thead>
    <tbody class="whitespace-pre-line">
        $product_table_body
    </tbody>
</table>
<table class="w-full table-auto mt-8">
    <thead class="text-left">
        $task_table_header
    </thead>
    <tbody class="whitespace-pre-line">
        $task_table_body
    </tbody>
</table>
<div class="flex px-4 mt-6 w-full">
    <div class="w-1/2">
        $entity.public_notes
    </div>
    <div class="w-1/2 flex">
        <div class="w-1/2 text-right flex flex-col">
            $discount_label
            $total_tax_labels
            $line_tax_labels
        </div>
        <div class="w-1/2 text-right flex flex-col">
            $discount
            $total_tax_values
            $line_tax_values
        </div>
    </div>
</div>

    <div class="flex px-4 mt-4 w-full items-end">
        <div class="w-1/2">
            <p class="font-semibold">$terms_label</p>
            $terms
        </div>
        <div class="w-1/2 flex">
            <div class="w-1/2 text-right flex flex-col">
                <span>$balance_due_label</span>
            </div>
            <div class="w-1/2 text-right flex flex-col">
                <span class="text-blue-600">$balance_due</span>
            </div>
        </div>
    </div>
</div>';
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
        return '
<footer>
    <div class="div_footer flex justify-between py-8 px-12" style="page-break-inside: avoid;">
    </div>
</footer>';
    }
}
