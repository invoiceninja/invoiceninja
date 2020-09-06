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

class Playful extends AbstractDesign
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
.table_header_thead_class { text-align: left; background-color: #319795; border-radius: .5rem; }
.table_header_td_class { padding: .75rem 1rem; font-weight: 600; color: white; }
.table_body_td_class { padding: 1rem; border-bottom-width: 4px; border-style: dashed; border-color: #319795; color: black }
$custom_css
</style>';
    }

    public function header()
    {
        return '<div class="my-12 mx-16">
<div class="grid grid-cols-12 items-center justify-between">
    <div class="col-span-7">
        $company_logo
    </div>
    <div class="col-span-5 bg-teal-600 p-5 text-white">
        <div class="flex flex-col flex-wrap">
            $entity_details
        </div>
    </div>
</div>';
    }

    public function body()
    {
        return '<div class="flex mt-16">
<div class="w-1/2">
    <div class="flex flex-col">
        <p class="font-semibold text-teal-600 pl-4">$to_label:</p>
        <div class="flex border-dashed border-t-4 border-b-4 border-teal-600 py-4 mt-4 pl-4">
            <section class="flex flex-col flex-wrap">
                $client_details
            </section>
        </div>
    </div>
</div>
<div class="w-1/2 ml-24">
    <div class="flex flex-col">
        <p class="font-semibold text-teal-600 pl-4">$from_label:</p>
        <div class="flex border-dashed border-t-4 border-b-4 border-teal-600 py-4 mt-4 pl-4">
            <section class="flex flex-col flex-wrap">
                $company_details
            </section>
        </div>
    </div>
</div>
</div>
<table class="w-full table-auto mt-20 mb-8">
<thead class="text-left bg-teal-600 rounded-lg">
    $product_table_header
</thead>
<tbody class="whitespace-pre-line">
    $product_table_body
</tbody>
</table>
<table class="w-full table-auto mt-20 mb-8">
<thead class="text-left bg-teal-600 rounded-lg">
    $task_table_header
</thead>
<tbody class="whitespace-pre-line">
    $task_table_body
</tbody>
</table>
<div class="grid grid-cols-12 gap-4">
    <div class="col-span-7 flex flex-col">
        $entity.public_notes
    </div>   
    <div class="col-span-5 flex">
        <section class="w-1/2 text-right flex flex-col">
            $discount_label
            $total_tax_labels
            $line_tax_labels
        </section>
        <section class="w-1/2 text-right flex flex-col">
            $discount
            $total_tax_values
            $line_tax_values
        </section>
    </div>
    <div class="col-span-7">
        <p class="font-semibold">$terms_label</p>
        <p>$terms</p>
    </div>   
    <div class="col-span-5">
        <div class="flex bg-teal-600 py-3 px-4 text-white">
            <p class="w-1/2">$balance_due_label</p>
            <p class="text-right w-1/2">$balance_due</p>
        </div>
    </div>
<div>';
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
