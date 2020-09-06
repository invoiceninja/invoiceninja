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

class Bold extends AbstractDesign
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
    @page: not(:first-of-type) { size: auto; margin-top: 5mm; }
    .table_header_thead_class {text-align:left;}
    .table_header_td_class {padding-left:3rem; padding-right:3rem; font-size:1rem; padding-left:1rem;padding-right:1rem; padding-top:.5rem;padding-bottom:.5rem}
    .table_body_td_class {background-color:#edf2f7; adding-top:1.25rem;padding-bottom:1.25rem; padding-left:3rem;}
    $custom_css
</style>';
    }

    public function header()
    {
        return '<div class="bg-gray-800 p-12">
    <div class="grid grid-cols-6 gap-1">
        <div class="col-span-2 p-3">
            <div class="p-1 rounded-lg">
                $company_logo
            </div>
        </div>
        <div class="col-span-2 p-3 text-white flex flex-col flex-wrap">
            $company_details
        </div>
        <div class="col-span-2 p-3 text-white flex flex-col flex-wrap">
            $company_address
        </div>
    </div>
</div>';
    }

    public function body()
    {
        return '<div class="bg-white mt-16 pl-10">
<div class="grid grid-cols-12 gap-2">
        <div class="col-span-7">
            <h2 class="text-2xl uppercase font-semibold text-teal-600 tracking-tight">$entity_label</h2>
            <div class="flex flex-col flex-wrap">$client_details</div>
        </div>
        <div class="col-span-5">
            <div class="bg-teal-600 px-5 py-3 text-white">
                <div class="w-80 flex flex-col text-white flex-wrap">
                    $entity_details
                </div>
            </div>
        </div>
    </div>
</div>

<div class="mx-10 mt-8">
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
</div>
<div class="flex px-4 mt-6 w-full px-12">
    <div class="w-1/2">
        $entity.public_notes
    </div>
    <div class="w-1/2 flex">
        <div class="w-1/2 text-right flex flex-col">
            $subtotal_label $discount_label $total_tax_labels $line_tax_labels 
        </div>
        <div class="w-1/2 text-right flex flex-col">
            $subtotal $discount $total_tax_values $line_tax_values 
        </div>
    </div>
</div>

<div class="flex px-4 mt-4 w-full items-end px-12">
    <div class="w-1/2 flex flex-col flex-wrap">
        <p class="font-semibold">$terms_label</p>
        $terms
    </div>
    <div class="w-1/2 flex">
        <div class="w-1/2 text-right flex flex-col flex-wrap">
            <span class="text-xl font-semibold">$balance_due_label</span>
        </div>
        <div class="w-1/2 text-right flex flex-col flex-wrap">
            <span class="text-xl text-teal-600 font-semibold">$balance_due</span>
        </div>
    </div>
</div>
';
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
