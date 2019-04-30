<?php

namespace App\DataMapper;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Utils\TranslationHelper;

/**
 * ClientSettings
 *
 * Client settings are built as a superset of Company Settings
 * 
 * If no client settings is specified, the default company setting is used.
 * 
 * Client settings are passed down to the entity level where they can be further customized and then saved
 * into the settings column of the entity, so there is no need to create additional entity level settings handlers.
 * 
 */
class ClientSettings extends BaseSettings
{
	/**
	 * Settings which also have a parent company setting
	 */
	public $timezone_id;
	public $date_format_id;
	public $datetime_format_id;
	public $military_time;
	public $start_of_week;
	public $financial_year_start;
	public $payment_terms;

	public $language_id;
	public $currency_id;
	public $default_task_rate;
	public $send_reminders;
	public $show_tasks_in_portal;
	public $custom_message_dashboard;
	public $custom_message_unpaid_invoice;
	public $custom_message_paid_invoice;
	public $custom_message_unapproved_quote;
	public $show_currency_symbol;
	public $show_currency_code;
	public $inclusive_taxes;

	public $custom_taxes1;
	public $custom_taxes2;
	public $lock_sent_invoices;
	public $auto_bill;

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
	
	public $credit_number_prefix;
	public $credit_number_pattern;
	public $credit_number_counter;

	public $shared_invoice_quote_counter;
	public $recurring_invoice_number_prefix;
	
	public $counter_padding;

	/**
	 * Settings which which are unique to client settings
	 */
	public $industry_id;
	public $size_id;
	public $invoice_email_list;	//default comma separated list of contact ids to email

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
	 *
	 * Default Client Settings scaffold
	 * 
	 * @return \stdClass
	 *
     */
	public static function defaults() : \stdClass
	{

		return (object)[
			'entity' => Client::class,
			'industry_id' => NULL,
			'size_id' => NULL,
			'invoice_email_list' => NULL,
		];

	}


	/**
	 * Merges settings from Company to Client
	 * 
	 * @param  \stdClass $company_settings
	 * @param  \stdClass $client_settings
	 * @return \stdClass of merged settings
	 */
	public static function buildClientSettings($company_settings, $client_settings) 
	{

		
		foreach($client_settings as $key => $value)
		{

			if(!isset($client_settings->{$key}) && property_exists($company_settings, $key)) {
				$client_settings->{$key} = $company_settings->{$key};
			}

		}
		
		return $client_settings;
	}

}

