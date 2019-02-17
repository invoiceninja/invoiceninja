<?php

namespace App\DataMapper;

/**
 * CompanySettings
 */
class CompanySettings extends BaseSettings
{

	public $timezone_id;
	public $language_id;
	public $currency_id;
	public $payment_terms;
	public $custom_label1;
	public $custom_value1;
	public $custom_label2;
	public $custom_value2;
	public $custom_label3;
	public $custom_value3;
	public $custom_label4;
	public $custom_value5;
	public $custom_client_label1;
	public $custom_client_label2;
	public $custom_client_label3;
	public $custom_client_label4;			
	public $custom_client_contact_label1;
	public $custom_client_contact_label2;
	public $custom_client_contact_label3;
	public $custom_client_contact_label4;			
	public $custom_invoice_label1;
	public $custom_invoice_label2;
	public $custom_invoice_label3;
	public $custom_invoice_label4;				
	public $custom_product_label1;
	public $custom_product_label2;
	public $custom_product_label3;
	public $custom_product_label4;			
	public $custom_task_label1;
	public $custom_task_label2;
	public $custom_task_label3;
	public $custom_task_label4;		
	public $custom_expense_label1;
	public $custom_expense_label2;
	public $custom_expense_label3;
	public $custom_expense_label4;
	public $translations;

	/**
	 * Cast object values and return entire class
	 * prevents missing properties from not being returned
	 * and always ensure an up to date class is returned
	 * 
	 * @return \stdClass	
     */
	public function __construct($obj)
	{
		foreach($obj as $key => $value)
			$this->{$key} = $value;
	}

	/**
	 * Provides class defaults on init
	 * @return object
	 */
	public static function defaults() : object
	{
		$config = json_decode(config('ninja.settings'));

		return (object) [
			'timezone_id' => $config->timezone_id,
			'language_id' => $config->language_id,
			'currency_id' => $config->currency_id,
			'payment_terms' => $config->payment_terms,
			'translations' => (object) [],
		];
	}
}

