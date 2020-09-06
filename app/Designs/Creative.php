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

/**
 * @wip: Table margins act weird.
 */
class Creative extends AbstractDesign
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
margin-top: 6mm;
}
.table_header_thead_class { text-align: left; border-radius: .5rem; }
.table_header_td_class { text-transform: uppercase; font-size: 1.25rem; color: #b83280; font-weight: 500 }
.table_body_td_class { padding: 1rem;}
$custom_css
</style>';
    }

    public function header()
    {
        return '<div class="m-12">
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-3 flex flex-col flex-wrap break-all">$client_details</div>
    <div class="col-span-3 flex flex-col flex-wrap break-all">$company_details</div>
    <div class="col-span-3 flex flex-col flex-wrap break-all">$company_address</div>
    <div class="col-span-3 flex flex-wrap">$company_logo</div>
</div>';
    }

    public function body()
    {
        return '<div class="grid grid-cols-12 mt-8">
    <div class="col-span-7">
        <p class="text-4xl text-pink-700">#$entity_number</p>
    </div>
    <div class="col-span-5 flex flex-col flex-wrap">$entity_details</div>
</div>

<table class="w-full table-auto border-t-4 border-pink-700 bg-white mt-8">
    <thead class="text-left rounded-lg">
        $product_table_header
    </thead>
    <tbody class="whitespace-pre-line">
        $product_table_body
    </tbody>
</table>
<table class="w-full table-auto border-t-4 border-pink-700 bg-white">
    <thead class="text-left rounded-lg">
        $task_table_header
    </thead>
    <tbody class="whitespace-pre-line">
        $task_table_body
    </tbody>
</table>
<div class="border-b-4 border-pink-700 mt-8">
<div class="grid grid-cols-12 mt-2 px-4 pb-4">
    <div class="col-span-7 flex flex-col">
        <p>$entity.public_notes</p>
    </div>
    <div class="col-span-5 flex px-3 mt-2">
        <div class="w-1/2 text-right flex flex-col">
            $subtotal_label $discount_label $total_tax_labels $line_tax_labels 
        </div>
        <div class="w-1/2 text-right flex flex-col">
            $subtotal $discount $total_tax_values $line_tax_values 
        </div>
    </div>
</div>
<div class="flex items-center justify-between mt-4 pb-4 px-4">
    <div class="w-1/2">
        <div class="flex flex-col">
            <p class="font-semibold">$terms_label</p>
            <p>N21</p>
        </div>
    </div>
</div>
</div>
<div class="w-full flex justify-end mt-4">
    <p>$balance_due_label</p>
    <p class="ml-8 text-pink-700 font-semibold">$balance</p>
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
