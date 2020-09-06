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

class Modern extends AbstractDesign
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
 .table_header_thead_class {text-align:left; text-align:left; color:#fff; background-color:#1a202c;}
 .table_header_td_class {padding-left:1rem;padding-right:1rem; padding-top:.5rem;padding-bottom:.5rem}
 .table_body_td_class {border-top-width:1px; border-bottom-width:1px; border-color:#1a202c; padding-left:1rem;padding-right:1rem; padding-top:1rem;padding-bottom:1rem;}
$custom_css
</style>
</head>
<body>';
    }

    public function header()
    {
        return '
<div class="header bg-orange-600 flex justify-between py-12 px-12" style="page-break-inside: avoid;">
<div class="grid grid-cols-6 gap-1">
    <div class="col-span-2 p-3">
		<h1 class="text-white font-bold text-3xl">$company.name</h1>
    </div>
    <div class="col-span-2 p-3 flex flex-col text-white flex-wrap">
        $company_details
    </div>
    <div class="col-span-2 p-3 flex flex-col text-white flex-wrap">
        $entity_details
    </div>
</div>
</div>';
    }

    public function body()
    {
        return '
<table class="container"><thead><tr><td><div class="header-space"></div></td></tr></thead>
<tbody><tr><td>
<div class="grid grid-cols-5 gap-1 px-12 pt-12">
    <div class="col-span-2 p-3">
		$company_logo
	</div>
    <div class="col-span-3 p-3 flex flex-col flex-wrap">
		$client_details
    </div>
</div>

<div class="px-12 pt-5 pb-20">
<table class="w-full table-auto mt-8">
    <thead class="text-left text-white bg-gray-900 display: table-header-group;">
       $product_table_header
    </thead>
    <tbody class="whitespace-pre-line">
            $product_table_body
    </tbody>
</table>
<table class="w-full table-auto mt-8">
    <thead class="text-left text-white bg-gray-900 display: table-header-group;">
        $task_table_header
    </thead>
    <tbody class="whitespace-pre-line">
            $task_table_body
    </tbody>
</table>
<div class="flex px-4 mt-6 w-full" style="page-break-inside: avoid;">
    <div class="w-1/2">
        $entity.public_notes
    </div>
    <div class="w-1/2 flex" style="page-break-inside: avoid;">
        <div class="w-1/2 text-right flex flex-col"  style="page-break-inside: avoid;">
            $discount_label
            $total_tax_labels
            $line_tax_labels
        </div>
        <div class="w-1/2 text-right flex flex-col"  style="page-break-inside: avoid;">
            $discount
            $total_tax_values
            $line_tax_values
        </div>
    </div>
</div>
<div style="page-break-inside: avoid;">
    <div class="flex px-4 mt-4 w-full items-end mt-5" >
        <div class="w-1/2" style="page-break-inside: avoid;">
            <p class="font-semibold">$terms_label</p>
            $terms
        </div>
    </div>

<div class="mt-8 px-4 py-2 bg-gray-900 text-white" style="">
    <div class="w-1/2"></div>
    <div class="w-auto flex justify-end" style="page-break-inside: avoid;">
        <div class="w-56" style="page-break-inside: avoid;">
            <p class="font-bold">$balance_due_label</p>
        </div>
        <p>$balance_due</p>
    </div>
</div>
</div>
</div>
</td></tr></tbody><tfoot><tr><td><div class="footer-space"></div></td></tr></tfoot></table>
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
<div class="footer bg-orange-600 flex justify-between py-8 px-12" style="page-break-inside: avoid;">
	<div class="grid grid-cols-12 gap-4">
		<div class="col-start-4 col-span-4 p-3 flex flex-col text-white text-right flex-wrap">
			$company_details
		</div>
		<div class="col-span-4 p-3 flex flex-col text-white text-right flex-wrap">
			$company_address
		</div>
	</div>
</div>';
    }
}
