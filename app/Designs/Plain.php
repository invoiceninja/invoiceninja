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
                @page {
                    size: auto;
                    margin-top: 5mm;
                }
                .table_header_thead_class { text-align: left; background-color: #e2e8f0 }
                .table_header_td_class { padding: 1rem .5rem; }
                .table_body_td_class { padding: 1rem; border-bottom-width: 1px; border-top-width: 2px; border-color: #e2e8f0 }
                </style>
            </head>
        ';
    }


	public function header() {

		return '
                    <div class="px-12 py-8">
                        <div class="flex justify-between">
                            $company_logo
                            <div class="flex flex-col">
                                $company_details
                            </div>
                            <div class="flex flex-col">
                                <div class="flex px-3">
                                    <section class="w-1/2 flex flex-col mr-4">
                                        $entity_labels
                                    </section>
                                    <section class="flex align-end flex-col">
                                        $entity_details
                                    </section>
                                </div>
                                <section class="flex bg-gray-300 px-3">
                                    <p class="w-1/2 mr-4">Balance Due</p>
                                    <p>$0.00</p>
                                </section>
                            </div>
                        </div>
			';

	}

	public function body() {

        return '
            <div class="flex flex-col mt-8">
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
                            $total_tax_labels
                            $line_tax_labels
                        </section>
                        <section class="w-1/2 text-right flex flex-col">
                            $total_tax_values
                            $line_tax_values
                        </section>
                    </div>
                    <section class="flex bg-gray-300 px-3 mt-1">
                        <p class="w-1/2 text-right">$balance_due_label</p>
                        <p class="text-right w-1/2">$balance_due</p>
                    </section>
                </div>

                </div>
            </body>
        </html>';

	}

}
