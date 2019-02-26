<?php

namespace App\DataMapper;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;

/**
 * ClientSettings
 */
class ClientSettings extends BaseSettings
{
	/**
	 * settings which also have a parent company setting
	 */
	public $timezone_id;
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
		];

	}

	/**
	 * Merges permissions from Company to Client
	 * 
	 * @param  \stdClass $company_settings
	 * @param  \stdClass $client_settings
	 * @return \stdClass of merged settings
	 */
	public static function buildClientSettings(CompanySettings $company_settings, ClientSettings $client_settings) : ClientSettings
	{

		/** Currency ID */

		foreach($client_settings as $key => $value)
		{

			if(!isset($client_settings->{$key}) && property_exists($company_settings, $key))
				$client_settings->{$key} = $company_settings->{$key};
		}


		return $client_settings;
	}

}

