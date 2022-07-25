<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

use App\Utils\Traits\MakesHash;
use stdClass;

/**
 * FreeCompanySettings.
 */
class FreeCompanySettings extends BaseSettings
{
    use MakesHash;

    /*Group settings based on functionality*/
    public $credit_design_id = 'VolejRejNm';

    public $client_number_pattern = '';

    public $client_number_counter = 1;

    public $credit_number_pattern = '';

    public $credit_number_counter = 1;

    public $currency_id = '1';

    public $custom_value1 = '';

    public $custom_value2 = '';

    public $custom_value3 = '';

    public $custom_value4 = '';

    public $date_format_id = '';

//    public $enabled_item_tax_rates          = 0;
    public $expense_number_pattern = '';

    public $expense_number_counter = 1;

    public $inclusive_taxes = false;

    public $invoice_design_id = 'VolejRejNm';

    public $invoice_number_pattern = '';

    public $invoice_number_counter = 1;

    public $invoice_taxes = 0;

    public $language_id = '';

    public $military_time = false;

    public $payment_number_pattern = '';

    public $payment_number_counter = 1;

    public $payment_terms = '';

    public $payment_type_id = '0';

    public $portal_design_id = '1';

    public $quote_design_id = 'VolejRejNm';

    public $quote_number_pattern = '';

    public $quote_number_counter = 1;

    public $timezone_id = '';

    public $show_currency_code = false;

    public $company_gateway_ids = '';

    public $task_number_pattern = '';

    public $task_number_counter = 1;

    public $tax_name1 = '';

    public $tax_rate1 = 0;

    public $tax_name2 = '';

    public $tax_rate2 = 0;

    public $tax_name3 = '';

    public $tax_rate3 = 0;

    public $ticket_number_pattern = '';

    public $ticket_number_counter = 1;

    public $translations;

    public $vendor_number_pattern = '';

    public $vendor_number_counter = 1;

    /* Company Meta data that we can use to build sub companies*/

    public $address1 = '';

    public $address2 = '';

    public $city = '';

    public $company_logo = '';

    public $country_id;

    public $email = '';

    public $id_number = '';

    public $name = '';

    public $phone = '';

    public $postal_code = '';

    public $state = '';

    public $vat_number = '';

    public $website = '';

    public static $casts = [
        'portal_design_id'					 => 'string',
        'currency_id'                        => 'string',
        'task_number_pattern'                => 'string',
        'task_number_counter'                => 'int',
        'expense_number_pattern'             => 'string',
        'expense_number_counter'             => 'int',
        'vendor_number_pattern'              => 'string',
        'vendor_number_counter'              => 'int',
        'ticket_number_pattern'              => 'string',
        'ticket_number_counter'              => 'int',
        'payment_number_pattern'             => 'string',
        'payment_number_counter'             => 'int',
        'company_gateway_ids'                => 'string',
        'address1'                           => 'string',
        'address2'                           => 'string',
        'city'                               => 'string',
        'company_logo'                       => 'string',
        'country_id'                         => 'string',
        'currency_id'                        => 'string',
        'custom_value1'                      => 'string',
        'custom_value2'                      => 'string',
        'custom_value3'                      => 'string',
        'custom_value4'                      => 'string',
        'inclusive_taxes'                    => 'bool',
        'name'                               => 'string',
        'payment_terms'                      => 'string',
        'payment_type_id'                    => 'string',
        'phone'                              => 'string',
        'postal_code'                        => 'string',
        'quote_design_id'                    => 'string',
        'credit_design_id'                   => 'string',
        'recurring_number_prefix'            => 'string',
        'state'                              => 'string',
        'email'                              => 'string',
        'vat_number'                         => 'string',
        'id_number'                          => 'string',
        'tax_name1'                          => 'string',
        'tax_name2'                          => 'string',
        'tax_name3'                          => 'string',
        'tax_rate1'                          => 'float',
        'tax_rate2'                          => 'float',
        'tax_rate3'                          => 'float',
        'timezone_id'                        => 'string',
        'date_format_id'                     => 'string',
        'military_time'                      => 'bool',
        'language_id'                        => 'string',
        'show_currency_code'                 => 'bool',
        'design'                             => 'string',
        'website'                            => 'string',
    ];

    /**
     * Cast object values and return entire class
     * prevents missing properties from not being returned
     * and always ensure an up to date class is returned.
     *
     * @param $obj
     */
    public function __construct($obj)
    {
    }

    /**
     * Provides class defaults on init.
     * @return stdClass
     */
    public static function defaults(): stdClass
    {
        $config = json_decode(config('ninja.settings'));

        $data = (object) get_class_vars(CompanySettings::class);

        unset($data->casts);
        unset($data->protected_fields);

        $data->timezone_id = (string) config('ninja.i18n.timezone_id');
        $data->currency_id = (string) config('ninja.i18n.currency_id');
        $data->language_id = (string) config('ninja.i18n.language_id');
        $data->payment_terms = (int) config('ninja.i18n.payment_terms');
        $data->military_time = (bool) config('ninja.i18n.military_time');
        $data->date_format_id = (string) config('ninja.i18n.date_format_id');
        $data->country_id = (string) config('ninja.i18n.country_id');
        $data->translations = (object) [];
        $data->pdf_variables = (object) self::getEntityVariableDefaults();

        return self::setCasts($data, self::$casts);
    }
}
