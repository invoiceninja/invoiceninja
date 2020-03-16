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

/**
 * @wip: Table margins act weird.
 */
class Creative extends AbstractDesign
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
                </head>
                <body>
                <style>
                body {font-size:90%}
                @page
                {
                    size: auto;
                    margin-top: 6mm;
                }
                .table_header_thead_class { text-align: left; border-radius: .5rem; }
                .table_header_td_class { text-transform: uppercase; font-size: 1.25rem; color: #b83280; padding: 1.25rem 1rem; font-weight: 500 }
                .table_body_td_class { padding: 1rem;}
                </style>

        ';
    }


	public function header() {

		return '
                <div class="py-16 mx-16">
                    <div class="flex justify-between">
                        <div class="w-2/3 flex">
                            <div class="flex flex-col">
                                $client_details
                            </div>
                            <div class="ml-6 flex flex-col">
                                $company_details
                            </div>
                            <div class="ml-6 flex flex-col mr-4">
                                $company_address
                            </div>
                        </div>
                        $company_logo
                    </div>
			';

	}

	public function body() {

        return '
            <div class="flex justify-between mt-8">
                <div class="w-2/3 flex flex-col">
                    <h1 class="text-5xl uppercase font-semibold">$entity_label</h1>
                    <i class="ml-4 text-5xl text-pink-700">$entity_number</i>
                </div>
                <div class="flex">
                    <div class="flex justify-between flex-col">
                        $entity_labels
                    </div>
                    <div class="flex flex-col text-right">
                        $entity_details
                    </div>
                </div>
            </div>
            <table class="w-full table-auto mt-12 border-t-4 border-pink-700 bg-white">
            <thead class="text-left rounded-lg">
                $product_table_header
            </thead>
            <tbody>
                $product_table_body
            </tbody>
            </table>
            <table class="w-full table-auto mt-12 border-t-4 border-pink-700 bg-white">
                <thead class="text-left rounded-lg">
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
        <div class="border-b-4 border-pink-700">
            <div class="flex items-center justify-between mt-2 px-4 pb-4">
                <div class="w-1/2">
                    <div class="flex flex-col">
                        <p>$entity.public_notes</p>
                    </div>
                </div>
                <div class="w-1/3 flex flex-col">
                    <div class="flex px-3 mt-2">
                        <section class="w-1/2 text-right flex flex-col">
                            <span>$subtotal_label</span>
                            <span>$discount_label</span>
                            <span>$paid_to_date_label</span>
                        </section>
                        <section class="w-1/2 text-right flex flex-col">
                            <span>$subtotal</span>
                            <span>$discount</span>
                            <span>$paid_to_date</span>
                        </section>
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
        </div>

            </body>
        </html>';

	}

}
