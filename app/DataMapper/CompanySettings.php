<?php

namespace App\DataMapper;

use App\Models\Company;

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

	public $custom_taxes1;
	public $custom_taxes2;

	public $default_task_rate;
	public $send_reminders;
	public $show_tasks_in_portal;

	public $custom_message_dashboard;
	public $custom_message_unpaid_invoice;
	public $custom_message_paid_invoice;
	public $custom_message_unapproved_quote;
	public $lock_sent_invoices;

	public $inclusive_taxes;

	public $translations;

	/**
	 * Counter Variables
	 */
	public $invoice_number_prefix;
	public $invoice_number_pattern;
	public $invoice_number_counter;

	public $quote_number_prefix;
	public $quote_number_pattern;
	public $quote_number_counter;

	public $client_number_prefix;
	public $client_number_pattern;
	public $client_number_counter;

	public $credit_number_prefix;
	public $credit_number_pattern;
	public $credit_number_counter;

	public $shared_invoice_quote_counter;

	public $entity_number_padding;
	public $recurring_invoice_number_prefix;
	public $reset_counter_frequency_id;
	public $reset_counter_date;
	public $counter_padding;

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
			'entity' => Company::class,
			'timezone_id' => config('ninja.i18n.timezone_id'),
			'language_id' => config('ninja.i18n.language_id'),
			'currency_id' => config('ninja.i18n.currency_id'),
			'payment_terms' => config('ninja.i18n.payment_terms'),
			'datetime_format_id' => config('ninja.i18n.datetime_format'),
			'military_time' => config('ninja.i18n.military_time'),
			'date_format_id' => config('ninja.i18n.date_format'),
			'start_of_week' => config('ninja.i18n.start_of_week'),
			'financial_year_start' => config('ninja.i18n.financial_year_start'),
			'default_task_rate' => 0,
			'send_reminders' => 'TRUE',
			'show_tasks_in_portal' => 'TRUE',
			'show_currency_symbol' => 'TRUE',
			'show_currency_code' => 'FALSE',
			'inclusive_taxes' => 'TRUE',
			'custom_taxes1' => 'FALSE',
			'custom_taxes2' => 'FALSE',
			'lock_sent_invoices' => 'TRUE',
			'shared_invoice_quote_counter' => 'FALSE',
			'invoice_number_counter' => 1,
			'quote_number_counter' => 1,
			'credit_number_counter' => 1,
			'client_number_counter' => 1,
			'counter_padding' => 0,
			'recurring_invoice_number_prefix' => 'R',
			
			'translations' => (object) [],
		];
	}
}
