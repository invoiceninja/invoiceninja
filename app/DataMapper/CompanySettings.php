<?php

namespace App\DataMapper;

/**
 * CompanySettings
 */
class CompanySettings extends BaseSettings
{

	public $timezone_id;
	public $date_format_id;
	public $datetime_format_id;
	public $military_time;
	public $start_of_week;
	public $financial_year_start;

	public $language_id;
	public $currency_id;
	public $show_currency_symbol;
	public $show_currency_code;

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

	public $default_task_rate;
	public $send_reminders;
	public $show_tasks_in_portal;

	public $custom_message_dashboard;
	public $custom_message_unpaid_invoice;
	public $custom_message_paid_invoice;
	public $custom_message_unapproved_quote;
	
	public $inclusive_taxes;

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
		parent::__construct($obj);
	}

	/**
	 * Provides class defaults on init
	 * @return object
	 */
	public static function defaults() : \stdClass
	{
		$config = json_decode(config('ninja.settings'));

		return (object) [
			'timezone_id' => config('ninja.i18n.timezone'),
			'language_id' => config('ninja.i18n.language'),
			'currency_id' => config('ninja.i18n.currency'),
			'payment_terms' => config('ninja.i18n.payment_terms'),
			'datetime_format_id' => config('ninja.i18n.datetime_format'),
			'military_time' => config('ninja.i18n.military_time'),
			'date_format_id' => config('ninja.i18n.date_format'),
			'start_of_week' => config('ninja.i18n.start_of_week'),
			'financial_year_start' => config('ninja.i18n.financial_year_start'),
			'default_task_rate' => 0,
			'send_reminders' => 1,
			'show_tasks_in_portal' => 1,
			'show_currency_symbol' => 1,
			'show_currency_code' => 0,
			'inclusive_taxes' => 1,
			
			'translations' => (object) [],
		];
	}
}
