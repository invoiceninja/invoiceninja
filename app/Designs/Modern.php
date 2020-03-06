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

class Modern extends AbstractDesign
{

	public function __construct() {
	}


    public function includes()
    {
        return '
        	<!DOCTYPE html>
			<html lang="en">
			    <head>
			    	<title>$number</title>
			        <meta charset="utf-8">
			        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
			        <meta http-equiv="x-ua-compatible" content="ie=edge">
                    <link rel="stylesheet" href="/css/design/modern.css"> 
			        <style>
                    

    				 .table_header_thead_class {text-align:left; text-align:left; color:#fff; background-color:#1a202c;}
					 .table_header_td_class {padding-left:1rem;padding-right:1rem; padding-top:.5rem;padding-bottom:.5rem}
					 .table_body_td_class {border-top-width:1px; border-bottom-width:1px; border-color:#1a202c; padding-left:1rem;padding-right:1rem; padding-top:1rem;padding-bottom:1rem;}

					 @media screen {
					 	div.div_header {
						    display: flex;
						  }
						  div.div_footer {
						    display: flex;
						  }
						}
						@media print {
						  div.div_footer {
					     	display: flex; 
							position: running(footer);
					    	width: 100%;
						  }
						  div.div_header {
			    		    display: flex; 
							position: running(header);
					    	width:100%;
						  }
						}

						footer, header, hgroup, menu, nav, section {
						    display: block;
						}
                    </style>
			    </head>
				<body>

        ';
    }
    

	public function header() {

		return '
				<div class="div_header bg-orange-600 flex justify-between py-12 px-12" style="page-break-inside: avoid;">
					<div class="w-1/2">
						<h1 class="text-white font-bold text-5xl">$company.name</h1>
					</div>
					<div class="w-1/2 flex justify-end">
						<div class="w-56 flex flex-col text-white">
							$entity_labels
						</div>
						<div class="w-32 flex flex-col text-left text-white">
							$entity_details
						</div>
					</div>
				</div>
			';

	}

	public function body() {

		return '
			<section>
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

	public function table_styles() {
		return [
			'table_header_thead_class' => "text-left text-white bg-gray-900",
			'table_header_td_class'    => "px-4 py-2",
			'table_body_td_class'      => "border-t border-b border-gray-900 px-4 py-4",
		];
	}

    public function task() {
    }

	public function product() {
		return '
			<div class="px-12 pt-5 pb-20">
			    <table class="w-full table-auto mt-8">
			        <thead class="text-left text-white bg-gray-900 display: table-header-group;">
			            <tr>
			                $product_table_header
			            </tr>
			        </thead>
			        <tbody>
			                $product_table_body
			        </tbody>
			    </table>

			    <div class="flex px-4 mt-6 w-full" style="page-break-inside: avoid;">
			        <div class="w-1/2">
			            $entity.public_notes
			        </div>
			        <div class="w-1/2 flex" style="page-break-inside: avoid;">
			            <div class="w-1/2 text-right flex flex-col"  style="page-break-inside: avoid;">
			                $total_tax_labels
			                $line_tax_labels
			            </div>
			            <div class="w-1/2 text-right flex flex-col"  style="page-break-inside: avoid;">
			                $total_tax_values
			                $line_tax_values
			            </div>
			        </div>
			    </div>

			    <div class="flex px-4 mt-4 w-full items-end mt-5" style="page-break-inside: avoid;">
			        <div class="w-1/2" style="page-break-inside: avoid;">
			            <p class="font-semibold">$terms_label</p>
			            $terms
			        </div>
			    </div>

			    <div class="mt-8 px-4 py-2 bg-gray-900 text-white" style="page-break-inside: avoid;">
			        <div class="w-1/2"></div>
			        <div class="w-auto flex justify-end" style="page-break-inside: avoid;">
			            <div class="w-56" style="page-break-inside: avoid;">
			                <p class="font-bold">$balance_due_label</p>
			            </div>
			            <p>$balance_due</p>
			        </div>
			    </div>

			</div>

		';
	}

	public function footer() {

		return '
			</section>
			<footer>
			<div class="div_footer bg-orange-600 flex justify-between py-8 px-12" style="page-break-inside: avoid;">
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
			</footer>
			</html>
		';

	}

}