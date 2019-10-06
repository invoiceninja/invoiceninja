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

use App\DataMapper\CompanySettings;
use App\Models\Company;

/**
 * CompanySettings
 */
class CompanySettings extends BaseSettings
{

	public $timezone_id = '';
	public $date_format_id = '';
	public $datetime_format_id = '';
	public $military_time = false;

	public $language_id = '';
	public $precision = 2;
	public $show_currency_symbol = true;
	public $show_currency_code = false;

	public $payment_terms = 1; 

	public $custom_label1 = '';
	public $custom_label2 = '';
	public $custom_label3 = '';
	public $custom_label4 = '';

	public $custom_value1 = '';
	public $custom_value2 = '';
	public $custom_value3 = '';
	public $custom_value4 = '';
	
	public $custom_client_label1 = '';
	public $custom_client_label2 = '';
	public $custom_client_label3 = '';
	public $custom_client_label4 = '';			
	public $custom_client_contact_label1 = '';
	public $custom_client_contact_label2 = '';
	public $custom_client_contact_label3 = '';
	public $custom_client_contact_label4 = '';			
	public $custom_invoice_label1 = '';
	public $custom_invoice_label2 = '';
	public $custom_invoice_label3 = '';
	public $custom_invoice_label4 = '';				
	public $custom_product_label1 = '';
	public $custom_product_label2 = '';
	public $custom_product_label3 = '';
	public $custom_product_label4 = '';			
	public $custom_task_label1 = '';
	public $custom_task_label2 = '';
	public $custom_task_label3 = '';
	public $custom_task_label4 = '';		
	public $custom_expense_label1 = '';
	public $custom_expense_label2 = '';
	public $custom_expense_label3 = '';
	public $custom_expense_label4 = '';

	public $custom_invoice_taxes1 = false;
	public $custom_invoice_taxes2 = false;

	public $default_task_rate = 0;
	public $send_reminders = false;
	public $show_tasks_in_portal = false;

	public $custom_message_dashboard = '';
	public $custom_message_unpaid_invoice = '';
	public $custom_message_paid_invoice = '';
	public $custom_message_unapproved_quote = '';
	public $lock_sent_invoices = false;
	public $auto_archive_invoice = false;

	public $inclusive_taxes = false;

	public $translations;

	/**
	 * Counter Variables
	 */
	public $invoice_number_prefix = '';
	public $invoice_number_pattern = '';
	public $invoice_number_counter = 1;

	public $quote_number_prefix = '';
	public $quote_number_pattern = '';
	public $quote_number_counter = 1;

	public $client_number_prefix = '';
	public $client_number_pattern = '';
	public $client_number_counter = 1;

	public $credit_number_prefix = '';
	public $credit_number_pattern = '';
	public $credit_number_counter = 1;

	public $shared_invoice_quote_counter = false;

	public $recurring_invoice_number_prefix = 'R';
	public $reset_counter_frequency_id = '';
	public $reset_counter_date = '';
	public $counter_padding = 0;

	public $design = 'views/pdf/design1.blade.php';

	public $company_gateways = '';

	public $invoice_terms = '';
	public $quote_terms = '';
	public $invoice_taxes = false;
	public $invoice_item_taxes = false;
	public $invoice_design_id = '';
	public $quote_design_id = '';
	public $invoice_footer = '';
	public $invoice_labels = '';
	public $show_item_taxes = false;
	public $fill_products = false;
	public $tax_name1 = '';
	public $tax_rate1 = 0;
	public $tax_name2 = '';
	public $tax_rate2 = 0;
	public $tax_name3 = '';
	public $tax_rate3 = 0;
	public $enable_second_tax_rate = false;
	public $payment_type_id = '';
	public $convert_products = false;
	public $custom_fields = '';
	public $invoice_fields = '';
	public $email_footer = '';
	public $email_subject_invoice = '';
	public $email_subject_quote = '';
	public $email_subject_payment = '';
	public $email_template_invoice = '';
	public $email_template_quote = '';
	public $email_template_payment = '';
	public $email_subject_reminder1 = '';
	public $email_subject_reminder2 = '';
	public $email_subject_reminder3 = '';
	public $email_template_reminder1 = '';
	public $email_template_reminder2 = '';
	public $email_template_reminder3 = '';
	public $enable_portal_password = false;
	public $show_accept_invoice_terms = false;
	public $show_accept_quote_terms = false;
	public $require_invoice_signature = false;
	public $require_quote_signature = false;

	/* Company Meta data that we can use to build sub companies*/

	public $name = '';
	public $logo_url = '';
	public $website = '';
	public $address1 = '';
	public $address2 = '';
	public $city = '';
	public $state = '';
	public $postal_code = '';
	public $phone = '';
	public $email = '';
	public $country_id;
	public $vat_number = '';
	public $id_number = '';

	public static $casts = [
		'name' => 'string',
		'logo_url' => 'string',
		'website' => 'string',
		'address1' => 'string',
		'address2' => 'string',
		'city' => 'string',
		'state' => 'string',
		'postal_code' => 'string',
		'phone' => 'string',
		'email' => 'string',
		'country_id' => 'string',
		'vat_number' => 'string',
		'id_number' => 'string',
		'tax_name1' => 'string',
		'tax_name2' => 'string',
		'tax_name3' => 'string',
		'tax_rate1' => 'float',
		'tax_rate2' => 'float',
		'tax_rate3' => 'float',
		'require_invoice_signature' => 'bool',
		'require_quote_signature' => 'bool',
		'show_accept_quote_terms' => 'bool',
		'show_accept_invoice_terms' => 'bool',
		'timezone_id' => 'string',
		'date_format_id' => 'string',
		'datetime_format_id' => 'string',
		'military_time' => 'bool',
		'language_id' => 'string',
		'precision' => 'int',
		'show_currency_symbol' => 'bool',
		'show_currency_code' => 'bool',
		'payment_terms' => 'int', 
		'custom_label1' => 'string',
		'custom_label2' => 'string',
		'custom_label3' => 'string',
		'custom_label4' => 'string',
		'custom_value1' => 'string',
		'custom_value2' => 'string',
		'custom_value3' => 'string',
		'custom_value4' => 'string',
		'custom_client_label1' => 'string',
		'custom_client_label2' => 'string',
		'custom_client_label3' => 'string',
		'custom_client_label4' => 'string',			
		'custom_client_contact_label1' => 'string',
		'custom_client_contact_label2' => 'string',
		'custom_client_contact_label3' => 'string',
		'custom_client_contact_label4' => 'string',			
		'custom_invoice_label1' => 'string',
		'custom_invoice_label2' => 'string',
		'custom_invoice_label3' => 'string',
		'custom_invoice_label4' => 'string',				
		'custom_product_label1' => 'string',
		'custom_product_label2' => 'string',
		'custom_product_label3' => 'string',
		'custom_product_label4' => 'string',			
		'custom_task_label1' => 'string',
		'custom_task_label2' => 'string',
		'custom_task_label3' => 'string',
		'custom_task_label4' => 'string',		
		'custom_expense_label1' => 'string',
		'custom_expense_label2' => 'string',
		'custom_expense_label3' => 'string',
		'custom_expense_label4' => 'string',
		'custom_invoice_taxes1' => 'bool',
		'custom_invoice_taxes2' => 'bool',
		'default_task_rate' => 'float',
		'send_reminders' => 'bool',
		'show_tasks_in_portal' => 'bool',
		'custom_message_dashboard' => 'string',
		'custom_message_unpaid_invoice' => 'string',
		'custom_message_paid_invoice' => 'string',
		'custom_message_unapproved_quote' => 'string',
		'lock_sent_invoices' => 'bool',
		'auto_archive_invoice' => 'bool',
		'inclusive_taxes' => 'bool',
		'invoice_number_prefix' => 'string',
		'invoice_number_pattern' => 'string',
		'invoice_number_counter' => 'int',
		'quote_number_prefix' => 'string',
		'quote_number_pattern' => 'string',
		'quote_number_counter' => 'int',
		'client_number_prefix' => 'string',
		'client_number_pattern' => 'string',
		'client_number_counter' => 'int',
		'credit_number_prefix' => 'string',
		'credit_number_pattern' => 'string',
		'credit_number_counter' => 'int',
		'shared_invoice_quote_counter' => 'bool',
		'recurring_invoice_number_prefix' => 'string',
		'reset_counter_frequency_id' => 'int',
		'reset_counter_date' => 'string',
		'counter_padding' => 'int',
		'design' => 'string',
		'company_gateways' => 'string',
	];

	/**
	 * Cast object values and return entire class
	 * prevents missing properties from not being returned
	 * and always ensure an up to date class is returned
	 * 
	 * @return \stdClass	
     */
	public function __construct($obj)
	{
	//	parent::__construct($obj);
	}

	/**
	 * Provides class defaults on init
	 * @return object
	 */
	public static function defaults() : \stdClass
	{
		
		$config = json_decode(config('ninja.settings'));
		
		$data = (object)get_class_vars(CompanySettings::class);
		unset($data->casts);

		$data->timezone_id = (string)config('ninja.i18n.timezone_id');
		$data->language_id = (string)config('ninja.i18n.language_id');
		$data->payment_terms = (int)config('ninja.i18n.payment_terms');
		$data->datetime_format_id = (string)config('ninja.i18n.datetime_format_id');
		$data->military_time = (bool )config('ninja.i18n.military_time');
		$data->date_format_id = (string)config('ninja.i18n.date_format_id');
		$data->country_id = (string)config('ninja.i18n.country_id');
		$data->translations = (object) [];
		
		return self::setCasts($data, self::$casts);

	}

	/**
	 * In case we update the settings object in the future we
	 * need to provide a fallback catch on old settings objects which will
	 * set new properties to the object prior to being returned.
	 * 
	 * @param object $data The settings object to be checked
	 */
	public static function setProperties($settings) :\stdClass
	{

		$company_settings = (object)get_class_vars(CompanySettings::class);

		foreach($company_settings as $key => $value)
		{

			if(!property_exists($settings, $key))
				$settings->{$key} = self::castAttribute($key, $company_settings->{$key});
			
		}

		return $settings;

	}

}
