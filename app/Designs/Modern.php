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

class Modern
{

    public function __construct()
    {
    }

	public function header()
	{

		return '
			<!DOCTYPE html>
			<html lang="en">
			    <head>
			        <meta charset="utf-8">
			        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
			        <meta http-equiv="x-ua-compatible" content="ie=edge">
			        <link rel="stylesheet" href="/assets/build/css/main.css">
			    </head>
			    <body>
			        
			<div class="bg-orange-600 flex justify-between py-12 px-12">
			    <div class="w-1/2">
			        <h1 class="text-white font-bold text-5xl">$company.name</h1>
			    </div>
			    <div class="w-1/2 flex justify-end">
			        <div class="w-56 flex flex-col text-white">
			            $invoice_details_labels
			        </div>
			        <div class="w-32 flex flex-col text-left text-white">
			        	$invoice_details
			        </div>
			    </div>
			</div>
			';

	}

	public function body()
	{

		return '
			<div class="flex justify-between px-12 pt-12">
			    <div class="w-1/2">
			        $company_logo
			    </div>
			    <div class="w-1/2 flex justify-end">
			        <div class="w-56 flex flex-col">
			            $client_details
			        </div>
			        <div class="w-32">
			            <!-- -->
			        </div>
			    </div>
			</div>
			';

	}

	public function table_styles()
	{
		return [
			'table_header_class' => "px-4 py-2",
			'table_body_class' => "border-t border-b border-gray-900 px-4 py-4",
		];
	}

	public function table()
	{

		return '
			<div class="px-12 pt-5 pb-20">
			    <table class="w-full table-auto mt-8">
			        <thead class="text-left text-white bg-gray-900">
			            <tr>
			                $table_header
			            </tr>
			        </thead>
			        <tbody>
			            <tr>
			                $table_body
			            </tr>
			        </tbody>
			    </table>

			    <div class="flex px-4 mt-6 w-full">
			        <div class="w-1/2">
			            All design content is copyrighted until payment is complete.
			        </div>
			        <div class="w-1/2 flex">
			            <div class="w-1/2 text-right flex flex-col">
			                $total_labels
			            </div>
			            <div class="w-1/2 text-right flex flex-col">
			                $total_values
			            </div>
			        </div>
			    </div>

			    <div class="flex px-4 mt-4 w-full items-end mt-5">
			        <div class="w-1/2">
			            <p class="font-semibold">$terms_label</p>
			            $terms
			        </div>
			    </div>

			    <div class="mt-8 px-4 py-2 bg-gray-900 text-white">
			        <div class="w-1/2"></div>
			        <div class="w-auto flex justify-end">
			            <div class="w-56">
			                <p class="font-bold">$balance_due_label</p>
			            </div>
			            <p>$balance_due</p>
			        </div>
			    </div>

			</div>
		';
	}

	public function footer()
	{

		return '
			<div class="bg-orange-600 flex justify-between py-8 px-12">
			    <div class="w-1/2">
			        <!-- // -->
			    </div>
			    <div class="w-1/2 flex justify-end">
			        <div class="w-56 flex flex-col text-white">
			            $company_details
			        </div>
			        <div class="w-32 flex flex-col text-left text-white">
			            $company_address
			        </div>
			    </div>
			</div>

			    </body>
			</html>
		';

	}

}