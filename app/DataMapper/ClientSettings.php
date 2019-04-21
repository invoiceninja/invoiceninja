<?php

namespace App\DataMapper;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Utils\TranslationHelper;
use Illuminate\Support\Facades\Log;

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

	/**
	 * settings which which are unique to client settings
	 */
	public $industry_id;
	public $size_id;
	

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
			'timezone_id' => NULL,
			'language_id' => NULL,
			'currency_id' => NULL,
			'payment_terms' => NULL,
			'datetime_format_id' => NULL,
			'military_time' => NULL,
			'date_format_id' => NULL,
			'start_of_week' => NULL,
			'financial_year_start' => NULL,
			'default_task_rate' => NULL,
			'send_reminders' => NULL,
			'show_tasks_in_portal' => NULL,
			'show_currency_symbol' => NULL,
			'show_currency_code' => NULL,
			'inclusive_taxes' => NULL,
			'custom_taxes1' => NULL,
			'custom_taxes2' => NULL,
			'lock_sent_invoices' => NULL,
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

			if(!isset($client_settings->{$key}) && property_exists($company_settings, $key))
				$client_settings->{$key} = $company_settings->{$key};

		}
		
		return $client_settings;
	}

}

