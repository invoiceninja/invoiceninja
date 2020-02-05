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

use App\Models\ClientContact;

class Designer
{

	protected $design;

	protected $contact;

	protected $html;

	public function __construct($design, ClientContact $contact)
	{
		$this->design = $design;

		$this->contact = $contact;
	}

	/**
	 * Returns the design
	 * formatted HTML
	 * @return string The HTML design built
	 */
	public function build() :string
	{

	}

	private function setDesign($section)
	{

		$this->html .= $section;

	}

	public function clientDetails()
	{

			return [
			'name' => '<p>$client.name</p>',

			'custom_value1' => '<span>$client.custom_value1</span>',
			'custom_value2' => '<span>$client.custom_value2</span>',
			'custom_value3' => '<span>$client.custom_value3</span>',
			'custom_value4' => '<span>$client.custom_value4</span>',
		];
            // 'client.client_name',
            // 'client.id_number',
            // 'client.vat_number',
            // 'client.address1',
            // 'client.address2',
            // 'client.city_state_postal',
            // 'client.country',
            // 'client.email',
            // 'client.custom_value1',
            // 'client.custom_value2',
            // 'client.custom_value3',
            // 'client.custom_value4',
            // 'contact.custom_value1',
            // 'contact.custom_value2',
            // 'contact.custom_value3',
            // 'contact.custom_value4',
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