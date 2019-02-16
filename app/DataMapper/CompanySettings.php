<?php

namespace App\DataMapper;

/**
 * CompanySettings
 */
class CompanySettings extends BaseSettings
{
	/**
	 * @return \stdClass
	 *
	 * 
			
     */
	public static function defaults() : \stdClass
	{
		$config = json_decode(config('ninja.settings'));

		return (object)[
			'timezone_id' => $config->timezone_id,
			'language_id' => $config->language_id,
			'currency_id' => $config->currency_id,
			'payment_terms' => $config->payment_terms,
			'custom_label1' => NULL,
			'custom_value1' => NULL,
			'custom_label2' => NULL,
			'custom_value2' => NULL,
			'custom_label3' => NULL,
			'custom_value3' => NULL,
			'custom_label4' => NULL,
			'custom_value5' => NULL,
			'custom_client_label1' => NULL,
			'custom_client_label2' => NULL,
			'custom_client_label3' => NULL,
			'custom_client_label4' => NULL,				
			'custom_client_contact_label1' => NULL,
			'custom_client_contact_label2' => NULL,
			'custom_client_contact_label3' => NULL,
			'custom_client_contact_label4' => NULL,				
			'custom_invoice_label1' => NULL,
			'custom_invoice_label2' => NULL,
			'custom_invoice_label3' => NULL,
			'custom_invoice_label4' => NULL,				
			'custom_product_label1' => NULL,
			'custom_product_label2' => NULL,
			'custom_product_label3' => NULL,
			'custom_product_label4' => NULL,				
			'custom_task_label1' => NULL,
			'custom_task_label2' => NULL,
			'custom_task_label3' => NULL,
			'custom_task_label4' => NULL,				
			'custom_expense_label1' => NULL,
			'custom_expense_label2' => NULL,
			'custom_expense_label3' => NULL,
			'custom_expense_label4' => NULL,	
			'translations' => (object) [],
		];

	}
}

