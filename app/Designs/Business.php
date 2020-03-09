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

class Business extends AbstractDesign
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
                    <link rel="stylesheet" href="/css/design/business.css"> 

                <style>
                    @page  
                    { 
                        size: auto;
                        margin-top: 5mm;
                    } 
                    thead th:first-child {
                        border-top-left-radius: 0.5rem;
                    }
                    thead th:last-child {
                        border-top-right-radius: 0.5rem;
                    }

                .table_header_thead_class text-left bg-blue-900
                .table_header_td_class font-semibold text-white px-4 bg-blue-900 py-5
                .table_body_td_class border-4 border-white text-orange-700 px-4 py-4
            
                </style>
                </head>

        ';
    }

	public function header() {

        return '
                <div class="my-16 mx-10">
                <div class="flex justify-between">
                    <div class="w-1/2">
                        $company_logo
                    </div>
                    <div class="w-1/2 flex justify-end">
                        <div class="flex flex-col text-gray-600">
                            $company_details
                        </div>
                        <div class="flex flex-col text-gray-600 ml-8">
                            $company_address
                        </div>
                    </div>
                </div>
			';

	}

	public function body() {

        return '
            <div class="flex items-center justify-between mt-20">
                <div class="w-1/2 flex flex-col">
                    <span>$entity_label</span>
                    <section class="flex flex-col text-orange-600 mt-2">
                        $client_details
                    </section>
                </div>
                <div class="w-1/2 ml-40 bg-orange-600 px-4 py-4 h-auto rounded-lg">
                    <div class="flex text-white">
                        <section class="w-1/2 flex flex-col">
                            $entity_labels
                        </section>
                        <section class="flex flex-col">
                            $entity_details
                        </section>
                    </div>
                </div>
            </div>
        ';

	}

    public function task() {
        return '
            <table class="w-full table-auto mt-20">
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
            <table class="w-full table-auto mt-20">
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
                <div class="flex items-center justify-between px-4 pb-4 bg-gray-200 rounded py-2">
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
                    <section class="flex py-2 bg-blue-900 px-4 py-3 rounded text-white">
                        <p class="w-1/2">$balance_due_label</p>
                        <p class="text-right w-1/2">$balance_due</p>
                    </section>
                </div>
            </div>
        </div>
            </body>
        </html>
        ';

	}

}