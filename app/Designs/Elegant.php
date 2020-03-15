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

class Elegant extends AbstractDesign
{

	public function __construct() {
	}


    public function includes()
    {
        return '
            <head>
                <title>$number</title>
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
                    .table_header_thead_class { text-align: left; border-bottom-width: 1px; border-style: dashed; border-color: black; }
                    .table_header_td_class { font-weight: normal; color: #2f855a; padding: .5rem 1rem; }
                    .table_body_td_class { padding: 1rem; }
                </style>
            </head>
        ';
    }


	public function header() {

		return '
                <div class="py-16 px-8">
                <div class="flex flex justify-between border-b-4 border-black">
                    <div style="margin-bottom: 15px">
                        $company_logo
                    </div>
                    <div class="flex">
                        <div class="flex flex-col mr-5">
                            $entity_labels
                        </div>
                        <div class="flex flex-col text-right">
                            $entity_details
                        </div>
                    </div>
                </div>
                <div class="p-px border-b border-black mt-1"></div>
			';

	}

	public function body() {

        return '
        <div class="flex mt-8">
            <div class="w-1/3 mr-6 flex flex-col pr-2 border-r border-dashed border-black">
                $client_details
            </div>
            <div class="flex flex-col mr-6">
                $company_details
            </div>
            <div class="flex flex-col">
                $company_address
            </div>
        </div>
        <table class="w-full table-auto mb-6 mt-16">
            <thead class="text-left border-dashed border-b border-black">
                $product_table_header
            </thead>
            <tbody>
                $product_table_body
            </tbody>
        </table>
        <table class="w-full table-auto mb-6 mt-16">
            <thead class="text-left border-dashed border-b border-black">
                $product_table_header
            </thead>
            <tbody>
                $product_table_body
            </tbody>
        </table>
        ';

	}

    public function task() {
	    return '';
    }

    public function product()
    {
        return '';
    }

	public function footer() {

        return '
            <div class="flex items-center justify-between mt-2 px-4 pb-4">
                <div class="w-1/2">
                    <div class="flex flex-col">
                        <p>$entity.public_notes</p>
                    </div>
                </div>
                <div class="w-1/3 flex flex-col">
                    <div class="flex px-3 mt-2">
                        <section class="w-1/2 text-right flex flex-col">
                            $total_tax_labels
                            $line_tax_labels
                        </section>
                        <section class="w-1/2 text-right flex flex-col">
                            $total_tax_values
                            $line_tax_values
                        </section>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between mt-4 pb-4 px-4">
                <div class="w-1/2">
                    <div class="flex flex-col">
                        <p class="font-semibold">$terms_label</p>
                        <p>$terms</p>
                    </div>
                </div>
                <div class="flex w-2/5 flex-col">
                    <section class="flex py-2 text-green-700 border-t border-b border-dashed border-black px-2 mt-1">
                        <p class="w-1/2">$balance_due_label</p>
                        <p class="text-right w-1/2">$balance</p>
                    </section>
                </div>
            </div>
                    <div class="flex justify-center border-b-4 border-black mt-6">
                    <h4 class="text-2xl font-semibold mb-4">Thanks</h4>
                </div>
                <div class="p-px border-b border-black mt-1"></div>
            </div>';

	}

}
