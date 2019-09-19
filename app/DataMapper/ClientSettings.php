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
	 * Settings which which are unique to client settings
	 */
	public $industry_id;
	public $size_id;

	public static $casts = [
		'industry_id' => 'string',
		'size_id' => 'string',
	];
	
	/*

	public static $casts = [
		'timezone_id' => 'string',
		'date_format' => 'string',
		'datetime_format' => 'string',
		'military_time' => 'bool',
		'start_of_week' => 'int',
		'financial_year_start' => 'int',
		'payment_terms' => 'int',
		'language_id' => 'string',
		'precision' => 'int',
		'default_task_rate' => 'float',
		'send_reminders' => 'bool',
		'show_tasks_in_portal' => 'bool',
		'custom_message_dashboard' => 'string',
		'custom_message_unpaid_invoice' => 'string',
		'custom_message_paid_invoice' => 'string',
		'custom_message_unapproved_quote' => 'string',
		'show_currency_symbol' => 'bool',
		'show_currency_code' => 'bool',
		'inclusive_taxes' => 'bool',
		'custom_invoice_taxes1' => 'bool',
		'custom_invoice_taxes2' => 'bool',
		'lock_sent_invoices' => 'bool',
		'auto_bill' => 'bool',
		'auto_archive_invoice' => 'bool',
		'invoice_number_prefix' => 'string',
		'invoice_number_pattern' => 'string',
		'invoice_number_counter' => 'int',
		'quote_number_prefix' => 'string',
		'quote_number_pattern' => 'string',
		'quote_number_counter' => 'int',
		'credit_number_prefix' => 'string',
		'credit_number_pattern' => 'string',
		'credit_number_counter' => 'int',
		'shared_invoice_quote_counter' => 'int',
		'recurring_invoice_number_prefix' => 'string',
		'counter_padding' => 'int',
		'industry_id' => 'string',
		'size_id' => 'string',
		'design' => 'string',
		'company_gateways' => 'string',
		'invoice_design_id' => 'string',
		'quote_design_id' => 'string',
		'email_footer' => 'string',
		'email_subject_invoice' => 'string',
		'email_subject_quote' => 'string',
		'email_subject_payment' => 'string',
		'email_template_invoice' => 'string',
		'email_template_quote' => 'string',
		'email_template_payment' => 'string',
		'email_subject_reminder1' => 'string',
		'email_subject_reminder2' => 'string',
		'email_subject_reminder3' => 'string',
		'email_template_reminder1' => 'string',
		'email_template_reminder2' => 'string',
		'email_template_reminder3' => 'string',
	];
*/
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

		$data = (object)[
			'entity' => (string)Client::class,
			'industry_id' => '',
			'size_id' => '',
		];

		return self::setCasts($data, self::$casts);

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

		
		foreach($company_settings as $key => $value)
		{
			/* pseudo code
				if the property exists and is a string BUT has no length, treat it as TRUE
			*/
			if( ( (property_exists($client_settings, $key) && is_string($client_settings->{$key}) && (iconv_strlen($client_settings->{$key}) <1))) 
				|| !isset($client_settings->{$key}) 
				&& property_exists($company_settings, $key)) {
				$client_settings->{$key} = $company_settings->{$key};
			}

		}
		
		return $client_settings;
	}

}

