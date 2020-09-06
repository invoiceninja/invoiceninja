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

class Hipster extends AbstractDesign
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

.table_header_thead_class { text-align: left }
.table_header_td_class { text-transform: uppercase; padding: .5rem 1rem; font-weight: 600; border-color: black;  }
.table_body_td_class { border-left-width: 2px; border-color: black; padding: 1rem; }
$custom_css
</style>';
    }

    public function header()
    {
        return '<div class="px-12 py-16">
<div class="flex">
    <div class="w-1/2 border-l pl-4 border-black mr-4">
        <p class="font-semibold uppercase text-yellow-600">From:</p>
        <div class="flex">
            <div class="flex flex-col mr-5 flex-wrap">
                $company_details
            </div>
            <div class="flex flex-col flex-wrap">
                $company_address
            </div>
        </div>
    </div>
    <div class="w-1/3 border-l pl-4 border-black flex flex-col flex-wrap">
        <p class="font-semibold uppercase text-yellow-600">To:</p>
        $client_details
    </div>
    <div class="w-1/3 mt-5 h-16">
        $company_logo
    </div>
</div>';
    }

    public function body()
    {
        return '<div class="flex flex-col mx-6 mt-10">
<h1 class="font-semibold uppercase text-6xl">$entity_label</h1>
<div class="flex mt-1">
    <span class="font-semibold uppercase text-yellow-600">$entity_number</span>
    <div class="ml-4">
        <span class="uppercase">$date_label</span>
        <span>$date</span>
    </div>
    <div class="ml-10">
        <span class="uppercase">$due_date_label</span>
        <span>$due_date</span>
    </div>
    <div class="ml-4">
        <span class="uppercase">$balance_due_label</span>
        <span class="text-yellow-600">$balance_due</span>
    </div>
</div>
</div>
<table class="w-full table-auto mt-24">
<thead class="text-left">
    $product_table_header
</thead>
<tbody class="whitespace-pre-line">
    $product_table_body
</tbody>
</table>
<table class="w-full table-auto mt-24">
<thead class="text-left">
    $task_table_header
</thead>
<tbody class="whitespace-pre-line">
    $task_table_body
</tbody>
</table>
<div class="flex justify-between mt-8">
<div class="w-1/2">
    <div class="flex flex-col">
        <p>$entity.public_notes</p>
        <div class="pt-4">
            <p class="font-bold">$terms_label</p>
            <p>$terms</p>
        </div>
    </div>
</div>
<div class="w-1/3 flex flex-col">
    <div class="flex px-3 mt-6">
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
    <section class="flex bg-black text-white px-3 mt-1">
        <p class="w-1/2 text-right">$balance_due_label</p>
        <p class="text-right w-1/2">$balance_due</p>
    </section>
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
