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

class Designer {

	protected $design;

	protected $input_variables;

	protected $exported_variables;

	protected $html;

	protected $entity_string;

	protected $entity;

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

	public function __construct($entity, $design, $input_variables, $entity_string) 
	{
		$this->entity = $entity;

		$this->design = $design;

		$this->input_variables = (array) $input_variables;

		$this->entity_string = $entity_string;

	}

	/**
	 * Returns the design
	 * formatted HTML
	 * @return string The HTML design built
	 */
	public function build():Designer 
	{

		$this->setHtml()
			->exportVariables()
			->setDesign($this->getSection('includes'))
		    ->setDesign($this->getSection('header'))
			->setDesign($this->getSection('body'))
			->setDesign($this->getProductTable($this->entity))
			->setDesign($this->getSection('footer'));

		return $this;

	}

	public function init()
	{
		$this->setHtml()
		->exportVariables();

		return $this;
	}

	public function getHeader()
	{

		$this->setDesign($this->getSection('includes'))
		->setDesign($this->getSection('header'));

		return $this;
	}

	public function getFooter()
	{

		$this->setDesign($this->getSection('footer'));

		return $this;		
	}

	public function getBody() 
	{

		$this->setDesign($this->getSection('includes'))
			->setDesign($this->getSection('body'))
			->setDesign($this->getProductTable());
		
		return $this;
	}

	public function getProductTable():string 
	{

		$table_header = $this->entity->table_header($this->input_variables['product_columns'], $this->design->table_styles());
		$table_body   = $this->entity->table_body($this->input_variables['product_columns'], $this->design->table_styles());

		$data = str_replace('$product_table_header', $table_header, $this->getSection('product'));
		$data = str_replace('$product_table_body', $table_body, $data);

		return $data;

	}

	public function getTaskTable():string 
	{

		$table_header = $this->entity->table_header($this->input_variables['task_columns'], $this->design->table_styles());
		$table_body   = $this->entity->table_body($this->input_variables['task_columns'], $this->design->table_styles());

		$data = str_replace('$task_table_header', $table_header, $this->getSection('task'));
		$data = str_replace('$task_table_body', $table_body, $data);

		return $data;

	}

	public function getHtml():string 
	{
		return $this->html;
	}

	public function setHtml()
	{
		$this->html =  '';

		return $this;
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
	public function getSection($section):string 
	{
		return str_replace(array_keys($this->exported_variables), array_values($this->exported_variables), $this->design->{$section}());
	}

	private function exportVariables() 
	{

		$company = $this->entity->company;

		$this->exported_variables['$client_details']  = $this->processVariables($this->processInputVariables($company, $this->input_variables['client_details']), $this->clientDetails($company));
		$this->exported_variables['$company_details'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['company_details']), $this->companyDetails($company));
		$this->exported_variables['$company_address'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['company_address']), $this->companyAddress($company));

		if($this->entity_string == 'invoice')
		{
			$this->exported_variables['$entity_labels']  = $this->processLabels($this->processInputVariables($company, $this->input_variables['invoice_details']), $this->invoiceDetails($company));
			$this->exported_variables['$entity_details'] = $this->processVariables($this->processInputVariables($company, $this->input_variables['invoice_details']), $this->invoiceDetails($company));
		}
		elseif($this->entity_string == 'credit')
		{
			$this->exported_variables['$entity_labels']   = $this->processLabels($this->processInputVariables($company, $this->input_variables['credit_details']), $this->creditDetails($company));
			$this->exported_variables['$entity_details']  = $this->processVariables($this->processInputVariables($company, $this->input_variables['credit_details']), $this->creditDetails($company));
		}
		elseif($this->entity_string == 'quote')
		{
			$this->exported_variables['$entity_labels']    = $this->processLabels($this->processInputVariables($company, $this->input_variables['quote_details']), $this->quoteDetails($company));
			$this->exported_variables['$entity_details']   = $this->processVariables($this->processInputVariables($company, $this->input_variables['quote_details']), $this->quoteDetails($company));
		}
		return $this;
	}

	private function processVariables($input_variables, $variables):string 
	{

		$output = '';

		foreach ($input_variables as $value)
		$output .= $variables[$value];

		return $output;

	}

	private function processLabels($input_variables, $variables):string 
	{
		$output = '';

		foreach ($input_variables as $value) {

			$tmp = str_replace("</span>", "_label</span>", $variables[$value]);

			$output .= $tmp;
		}

		return $output;
	}

	private function clientDetails(Company $company) 
	{

		$data = [
			'name'              => '<p>$client.name</p>',
			'id_number'         => '<p>$client.id_number</p>',
			'vat_number'        => '<p>$client.vat_number</p>',
			'address1'          => '<p>$client.address1</p>',
			'address2'          => '<p>$client.address2</p>',
			'city_state_postal' => '<p>$client.city_state_postal</p>',
			'postal_city_state' => '<p>$client.postal_city_state</p>',
			'country'           => '<p>$client.country</p>',
			'email'             => '<p>$client.email</p>',
			'client1'           => '<p>$client1</p>',
			'client2'           => '<p>$client2</p>',
			'client3'           => '<p>$client3</p>',
			'client4'           => '<p>$client4</p>',
			'contact1'          => '<p>$contact1</p>',
			'contact2'          => '<p>$contact2</p>',
			'contact3'          => '<p>$contact3</p>',
			'contact4'          => '<p>$contact4</p>',
		];

		return $this->processCustomFields($company, $data);
	}

	private function companyDetails(Company $company) 
	{

		$data = [
			'company_name' => '<span>$company.company_name</span>',
			'id_number'    => '<span>$company.id_number</span>',
			'vat_number'   => '<span>$company.vat_number</span>',
			'website'      => '<span>$company.website</span>',
			'email'        => '<span>$company.email</span>',
			'phone'        => '<span>$company.phone</span>',
			'company1'     => '<span>$company1</span>',
			'company2'     => '<span>$company2</span>',
			'company3'     => '<span>$company3</span>',
			'company4'     => '<span>$company4</span>',
		];

		return $this->processCustomFields($company, $data);

	}

	private function companyAddress(Company $company) 
	{

		$data = [
			'address1'          => '<span>$company.address1</span>',
			'address2'          => '<span>$company.address1</span>',
			'city_state_postal' => '<span>$company.city_state_postal</span>',
			'postal_city_state' => '<span>$company.postal_city_state</span>',
			'country'           => '<span>$company.country</span>',
			'company1'          => '<span>$company1</span>',
			'company2'          => '<span>$company2</span>',
			'company3'          => '<span>$company3</span>',
			'company4'          => '<span>$company4</span>',
		];

		return $this->processCustomFields($company, $data);

	}

	private function invoiceDetails(Company $company) 
	{

		$data = [
			'invoice_number' => '<span>$invoice_number</span>',
			'po_number'      => '<span>$po_number</span>',
			'date'           => '<span>$date</span>',
			'due_date'       => '<span>$due_date</span>',
			'balance_due'    => '<span>$balance_due</span>',
			'invoice_total'  => '<span>$invoice_total</span>',
			'partial_due'    => '<span>$partial_due</span>',
			'invoice1'       => '<span>$invoice1</span>',
			'invoice2'       => '<span>$invoice2</span>',
			'invoice3'       => '<span>$invoice3</span>',
			'invoice4'       => '<span>$invoice4</span>',
			'surcharge1'     => '<span>$surcharge1</span>',
			'surcharge2'     => '<span>$surcharge2</span>',
			'surcharge3'     => '<span>$surcharge3</span>',
			'surcharge4'     => '<span>$surcharge4</span>',
		];

		return $this->processCustomFields($company, $data);

	}

	private function quoteDetails(Company $company)
	{
		
		$data = [
			'quote_number' => '<span>$quote_number</span>',
			'po_number'      => '<span>$po_number</span>',
			'date'           => '<span>$date</span>',
			'valid_until'       => '<span>$valid_until</span>',
			'balance_due'    => '<span>$balance_due</span>',
			'quote_total'  => '<span>$quote_total</span>',
			'partial_due'    => '<span>$partial_due</span>',
			'quote1'       => '<span>$quote1</span>',
			'quote2'       => '<span>$quote2</span>',
			'quote3'       => '<span>$quote3</span>',
			'quote4'       => '<span>$quote4</span>',
			'surcharge1'     => '<span>$surcharge1</span>',
			'surcharge2'     => '<span>$surcharge2</span>',
			'surcharge3'     => '<span>$surcharge3</span>',
			'surcharge4'     => '<span>$surcharge4</span>',
		];

		return $this->processCustomFields($company, $data);

	}

	private function creditDetails(Company $company)
	{

		$data = [
			'credit_number' => '<span>$credit_number</span>',
			'po_number'      => '<span>$po_number</span>',
			'date'           => '<span>$date</span>',
			'credit_balance'    => '<span>$credit_balance</span>',
			'credit_amount'  => '<span>$credit_amount</span>',
			'partial_due'    => '<span>$partial_due</span>',
			'invoice1'       => '<span>$invoice1</span>',
			'invoice2'       => '<span>$invoice2</span>',
			'invoice3'       => '<span>$invoice3</span>',
			'invoice4'       => '<span>$invoice4</span>',
			'surcharge1'     => '<span>$surcharge1</span>',
			'surcharge2'     => '<span>$surcharge2</span>',
			'surcharge3'     => '<span>$surcharge3</span>',
			'surcharge4'     => '<span>$surcharge4</span>',
		];

		return $this->processCustomFields($company, $data);

	}

	private function processCustomFields(Company $company, $data) 
	{

		$custom_fields = $company->custom_fields;

		if (!$custom_fields) {
			return $data;
		}

		foreach (self::$custom_fields as $cf) {

			if (!property_exists($custom_fields, $cf) || (strlen($custom_fields->{ $cf}) == 0)) {
				unset($data[$cf]);

			}
		}

		return $data;

	}

	private function processInputVariables($company, $variables) 
	{

		$custom_fields = $company->custom_fields;

		$matches = array_intersect(self::$custom_fields, $variables);

		foreach ($matches as $match) {

			if (!property_exists($custom_fields, $match) || (strlen($custom_fields->{$match}) == 0)) {
				foreach ($variables as $key => $value) {
					if ($value == $match) {
						unset($variables[$key]);
					}
				}
			}

		}

		return $variables;

	}

}