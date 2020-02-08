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

use App\Models\Company;
use App\Models\Invoice;

class Designer
{

	protected $design;

	protected $input_variables;

	protected $exported_variables;

	protected $html;

	private static $custom_fields = [
        'invoice1',
        'invoice2',
        'invoice3',
        'invoice4',
        'surcharge1',
        'surcharge2',
        'surcharge3',
        'surcharge4',
        'client1',
        'client2',
        'client3',
        'client4',
        'contact1',
        'contact2',
        'contact3',
        'contact4',
        'company1',
        'company2',
        'company3',
        'company4',
	];

	public function __construct($design, array $input_variables)
	{
		$this->design = $design;

		$this->input_variables = $input_variables;
	}

	/**
	 * Returns the design
	 * formatted HTML
	 * @return string The HTML design built
	 */
	public function build(Invoice $invoice) :Designer
	{

		$this->exportVariables($invoice)
			 ->setDesign($this->getSection('header'))
			 ->setDesign($this->getSection('body'))
			 ->setDesign($this->getTable($invoice))
			 ->setDesign($this->getSection('footer'));

		return $this;
	}

	public function getTable(Invoice $invoice) :string
	{

		$table_header = $invoice->table_header($this->input_variables['table_columns'], $this->design->table_styles());
		$table_body = $invoice->table_body($this->input_variables['table_columns'], $this->design->table_styles());

		$data = str_replace('$table_header', $table_header, $this->getSection('table'));
		$data = str_replace('$table_body', $table_body, $data);

		return $data;

	}

	public function getHtml() :string
	{
		return $this->html;
	}

	private function setDesign($section)
	{

		$this->html .= $section;

		return $this;
	}

	/**
	 * Returns the template section on with the 
	 * stacked variables replaced with single variables.
	 * 
	 * @param  string $section the method name to be executed ie header/body/table/footer
	 * @return string The HTML of the template section
	 */
	public function getSection($section) :string
	{
		return str_replace(array_keys($this->exported_variables), array_values($this->exported_variables), $this->design->{$section}());
	}

	private function exportVariables($invoice)
	{
		$company = $invoice->company;

		$this->exported_variables['$client_details'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['client_details']), $this->clientDetails($company));
		$this->exported_variables['$company_details'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['company_details']), $this->companyDetails($company));
		$this->exported_variables['$company_address'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['company_address']), $this->companyAddress($company));
		$this->exported_variables['$invoice_details_labels'] = $this->processLabels($this->processInputVariables($company, $this->input_variables['invoice_details']), $this->invoiceDetails($company));
		$this->exported_variables['$invoice_details'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['invoice_details']), $this->invoiceDetails($company));

		return $this;
	}

	private function processVariables($input_variables, $variables) :string
	{

		$output = '';

		foreach($input_variables as $value)
			$output .= $variables[$value];

		return $output;

	}

	private function processLabels($input_variables, $variables) :string
	{
		$output = '';

		foreach($input_variables as $value) {
			
			$tmp = str_replace("</span>", "_label</span>", $variables[$value]);

			$output .= $tmp;
		}

		return $output;
	}

	// private function exportVariables()
	// {
	// 	/*
	// 	 * $invoice_details_labels
	// 	 * $invoice_details
	// 	 */
	// 	$header = $this->design->header();
		
	// 	/*
	// 	 * $company_logo - full URL
	// 	 * $client_details
	// 	 */
	// 	$body = $this->design->body();

	// 	/* 
	// 	 * $table_header
	// 	 * $table_body
	// 	 * $total_labels
	// 	 * $total_values
 // 		 */
	// 	$table = $this->design->table();

	// 	/*
	// 	 * $company_details
	// 	 * $company_address
	// 	 */
	// 	$footer = $this->design->footer();
	// }

	private function clientDetails(Company $company)
	{

		$data = [
			'name' => '<p>$client.name</p>',
			'id_number' => '<p>$client.id_number</p>',
			'vat_number' => '<p>$client.vat_number</p>',
			'address1' => '<p>$client.address1</p>',
			'address2' => '<p>$client.address2</p>',
			'city_state_postal' => '<p>$client.city_state_postal</p>',
			'postal_city_state' => '<p>$client.postal_city_state</p>',
			'country' => '<p>$client.country</p>',
			'email' => '<p>$client.email</p>',
			'client1' => '<p>$client1</p>',
			'client2' => '<p>$client2</p>',
			'client3' => '<p>$client3</p>',
			'client4' => '<p>$client4</p>',
			'contact1' => '<p>$contact1</p>',
			'contact2' => '<p>$contact2</p>',
			'contact3' => '<p>$contact3</p>',
			'contact4' => '<p>$contact4</p>',
		];

		return $this->processCustomFields($company, $data);
	}

	private function companyDetails(Company $company)
	{
		$data = [
			'company_name' => '<span>$company.company_name</span>',
			'id_number' => '<span>$company.id_number</span>',
			'vat_number' => '<span>$company.vat_number</span>',
			'website' => '<span>$company.website</span>',
			'email' => '<span>$company.email</span>',
			'phone' => '<span>$company.phone</span>',
			'company1' => '<span>$company1</span>',
			'company2' => '<span>$company2</span>',
			'company3' => '<span>$company3</span>',
			'company4' => '<span>$company4</span>',
		];

		return $this->processCustomFields($company, $data);
	}

	private function companyAddress(Company $company)
	{

		$data = [
			'address1' => '<span>$company.address1</span>',
			'address2' => '<span>$company.address1</span>',
			'city_state_postal' => '<span>$company.city_state_postal</span>',
			'postal_city_state' => '<span>$company.postal_city_state</span>',
			'country' => '<span>$company.country</span>',
			'company1' => '<span>$company1</span>',
			'company2' => '<span>$company2</span>',
			'company3' => '<span>$company3</span>',
			'company4' => '<span>$company4</span>',
		];

		return $this->processCustomFields($company, $data);
	}

	private function invoiceDetails(Company $company)
	{

		$data = [
			'invoice_number' => '<span>$invoice_number</span>',
			'po_number' => '<span>$po_number</span>',
			'date' => '<span>$date</span>',
			'due_date' => '<span>$due_date</span>',
			'balance_due' => '<span>$balance_due</span>',
			'invoice_total' => '<span>$invoice_total</span>',
			'partial_due' => '<span>$partial_due</span>',
			'invoice1' => '<span>$invoice1</span>',
			'invoice2' => '<span>$invoice2</span>',
			'invoice3' => '<span>$invoice3</span>',
			'invoice4' => '<span>$invoice4</span>',
			'surcharge1' =>'<span>$surcharge1</span>',
			'surcharge2' =>'<span>$surcharge2</span>',
			'surcharge3' =>'<span>$surcharge3</span>',
			'surcharge4' =>'<span>$surcharge4</span>',
		];


		return $this->processCustomFields($company, $data);
	}

	private function processCustomFields(Company $company, $data)
	{

		$custom_fields = $company->custom_fields;

		foreach(self::$custom_fields as $cf)
		{

			if(!property_exists($custom_fields, $cf) || (strlen($custom_fields->{$cf}) == 0))
				unset($data[$cf]);

		}

		return $data;

	}

	private function processInputVariables($company, $variables)
	{

		$custom_fields = $company->custom_fields;

		$matches = array_intersect(self::$custom_fields, $variables);

		foreach($matches as $match)
		{

			if(!property_exists($custom_fields, $match) || (strlen($custom_fields->{$match}) == 0))
			{
				foreach($variables as $key => $value)
				{
					if($value == $match)
					{
						unset($variables[$key]);
					}
				}
			}


		}

		return $variables;

	}
}