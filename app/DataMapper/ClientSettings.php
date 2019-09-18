<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

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
	public $date_format;
	public $datetime_format;
	public $military_time;
	public $start_of_week;
	public $financial_year_start;
	public $payment_terms;

	public $language_id;
	public $precision;
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

	public $custom_invoice_taxes1;
	public $custom_invoice_taxes2;
	public $lock_sent_invoices;
	public $auto_bill;
	public $auto_archive_invoice;
	
	/**
	 * Counter Variables
	 *
	 * Currently we have only engineered counters to be implemented at the client level
	 * prefix/patterns and padding are not there yet.
	 */
	public $invoice_number_prefix;
	public $invoice_number_pattern;
	public $invoice_number_counter;

	public $quote_number_prefix;
	public $quote_number_pattern;
	public $quote_number_counter;
	
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

	public $design;

	public $company_gateways;


	public $invoice_design_id;
	public $quote_design_id;
	public $email_footer;
	public $email_subject_invoice;
	public $email_subject_quote;
	public $email_subject_payment;
	public $email_template_invoice;
	public $email_template_quote;
	public $email_template_payment;
	public $email_subject_reminder1;
	public $email_subject_reminder2;
	public $email_subject_reminder3;
	public $email_template_reminder1;
	public $email_template_reminder2;
	public $email_template_reminder3;
	
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

