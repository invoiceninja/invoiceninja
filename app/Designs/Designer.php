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

class Designer
{

	protected $design;

	protected $input_variables;

	protected $exported_variables;

	protected $html;

	private static $labels = [
		'$client_details',
		'$invoice_details',
		'$company_details',
		'$company_address',
		'$invoice_details_labels',
		'$invoice_details_labels',
		'$invoice_details_labels',
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
	public function build() :string
	{

		$this->setDesign($this->getSection('header'))
			 ->setDesign($this->getSection('body'))
			 ->setDesign($this->getSection('table'))
			 ->setDesign($this->getSection('footer'));

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
		$this->exportVariables();

		return str_replace(array_keys($this->exported_variables), array_values($this->exported_variables), $this->design->{$section}());
	}

	private function exportVariables()
	{
		$this->exported_variables['$client_details'] = $this->processVariables($this->input_variables['client_details'], $this->clientDetails());
		$this->exported_variables['$company_details'] = $this->processVariables($this->input_variables['company_details'], $this->companyDetails());
		$this->exported_variables['$company_address'] = $this->processVariables($this->input_variables['company_address'], $this->companyAddress());
		$this->exported_variables['$invoice_details'] = $this->processVariables($this->input_variables['invoice_details'], $this->invoiceDetails());

		return $this;
	}

	private function processVariables($input_variables, $variables)
	{

		$output = [];

		foreach($input_variables as $key => $value)
			$output[$key] = $variables[$key];

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

	private function clientDetails()
	{

		return [
			'name' => '<p>$client.name</p>',
			'id_number' => '<p>$client.id_number</p>',
			'vat_number' => '<p>$client.vat_number</p>',
			'address1' => '<p>$client.address1</p>',
			'address2' => '<p>$client.address2</p>',
			'city_state_postal' => '<p>$client.city_state_postal</p>',
			'postal_city_state' => '<p>$client.postal_city_state</p>',
			'country' => '<p>$client.country</p>',
			'email' => '<p>$client.email</p>',
			'custom_value1' => '<p>$client.custom_value1</p>',
			'custom_value2' => '<p>$client.custom_value2</p>',
			'custom_value3' => '<p>$client.custom_value3</p>',
			'custom_value4' => '<p>$client.custom_value4</p>',
		];

	}

	private function companyDetails()
	{
		return [
			'company_name' => '<span>$company.company_name</span>',
			'id_number' => '<span>$company.id_number</span>',
			'vat_number' => '<span>$company.vat_number</span>',
			'website' => '<span>$company.website</span>',
			'email' => '<span>$company.email</span>',
			'phone' => '<span>$company.phone</span>',
			'custom_value1' => '<span>$company.custom_value1</span>',
			'custom_value2' => '<span>$company.custom_value2</span>',
			'custom_value3' => '<span>$company.custom_value3</span>',
			'custom_value4' => '<span>$company.custom_value4</span>',
		];
	}

	private function companyAddress()
	{

		return [
			'address1' => '<span>$company.address1</span>',
			'address2' => '<span>$company.address1</span>',
			'city_state_postal' => '<span>$company.city_state_postal</span>',
			'postal_city_state' => '<span>$company.postal_city_state</span>',
			'country' => '<span>$company.country</span>',
			'custom_value1' => '<span>$company.custom_value1</span>',
			'custom_value2' => '<span>$company.custom_value2</span>',
			'custom_value3' => '<span>$company.custom_value3</span>',
			'custom_value4' => '<span>$company.custom_value4</span>',
		];

	}

	private function invoiceDetails()
	{

		return [
			'invoice_number' => '<span>$invoice_number</span>',
			'po_number' => '<span>$po_number</span>',
			'date' => '<span>$date</span>',
			'due_date' => '<span>$due_date</span>',
			'balance_due' => '<span>$balance_due</span>',
			'invoice_total' => '<span>$invoice_total</span>',
			'partial_due' => '<span>$partial_due</span>',
			'custom_value1' => '<span>$custom_value1</span>',
			'custom_value2' => '<span>$custom_value2</span>',
			'custom_value3' => '<span>$custom_value3</span>',
			'custom_value4' => '<span>$custom_value4</span>',
		];

	}
}