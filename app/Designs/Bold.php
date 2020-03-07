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

class Bold extends AbstractDesign
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
                    $css_url
                    <style>
                    @page: not(:first-of-type)
                    { 
                        size: auto;
                        margin-top: 5mm;
                    }

                     .table_header_thead_class {text-align:left;}
                     .table_header_td_class {padding-left:3rem; padding-right:3rem; font-size:1.5rem; padding-left:1rem;padding-right:1rem; padding-top:.5rem;padding-bottom:.5rem}
                     .table_body_td_class {background-color:#edf2f7; adding-top:1.25rem;padding-bottom:1.25rem; padding-left:3rem;}

                    </style>
                </head>
                
        ';
    }

	public function header() {

		return '
                <div class="flex static bg-gray-800 p-12">
                    <div class="w-1/2">
                        <div class="absolute bg-white pt-10 px-10 pb-4 inline-block align-middle">
                            $company_logo
                        </div>
                    </div>
                    <div class="w-1/2 flex">
                        <div class="w-64 flex flex-col text-white">
                            $company_details
                        </div>
                        <div class="flex flex-col text-white">
                            $company_address
                        </div>
                    </div>
                </div>
			';

	}

	public function body() {

        return '
            <div class="flex mt-32 pl-12">
                <div class="w-1/2 mr-40 flex flex-col">
                    <h2 class="text-2xl uppercase font-semibold text-teal-600 tracking-tight">$entity_label</h2> $client_details
                </div>
                <div class="w-1/2">
                    <div class="w-full bg-teal-600 px-5 py-3 text-white flex">
                        <div class="w-48 flex flex-col text-white">
                            $entity_labels
                        </div>
                        <div class="w-32 flex flex-col text-white">
                            $entity_details
                        </div>
                    </div>
                </div>
            </div>
        ';

	}

    public function task() {
        return '
            <table class="w-full table-auto mt-8">
                <thead class="text-left">
                    <tr>
                        $task_table_header
                    </tr>
                </thead>
                <tbody>
                    $task_table_body
                </tbody>
            </table>
        ';
    }

	public function product() {

        return '
            <table class="w-full table-auto mt-8">
                <thead class="text-left">
                    <tr>
                        $product_table_header
                    </tr>
                </thead>
                <tbody>
                    $product_table_body
                </tbody>
            </table>
        ';
	}

	public function footer() {

        return '
            <div class="flex px-4 mt-6 w-full px-12">
                <div class="w-1/2">
                    $entity.public_notes
                </div>
                <div class="w-1/2 flex">
                    <div class="w-1/2 text-right flex flex-col">
                        $total_tax_labels $line_tax_labels
                    </div>
                    <div class="w-1/2 text-right flex flex-col">
                        $total_tax_values $line_tax_values
                    </div>
                </div>
            </div>
            
            <div class="flex px-4 mt-4 w-full items-end px-12">
                <div class="w-1/2">
                    <p class="font-semibold">$terms_label</p>
                    $terms
                </div>
                <div class="w-1/2 flex">
                    <div class="w-1/2 text-right flex flex-col">
                        <span class="text-2xl font-semibold">$balance_due_label</span>
                    </div>
                    <div class="w-1/2 text-right flex flex-col">
                        <span class="text-2xl text-teal-600 font-semibold">$balance_due</span>
                    </div>
                </div>
            </div>
            ';

	}

}