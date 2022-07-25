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

use App\Models\Client;
use stdClass;

/**
 * ClientSettings.
 *
 * Client settings are built as a superset of Company Settings
 *
 * If no client settings is specified, the default company setting is used.
 *
 * Client settings are passed down to the entity level where they can be further customized and then saved
 * into the settings column of the entity, so there is no need to create additional entity level settings handlers.
 */
class ClientSettings extends BaseSettings
{
    /**
     * Settings which which are unique to client settings.
     */
    public $industry_id;

    public $size_id;

    public static $casts = [
        'industry_id' => 'string',
        'size_id' => 'string',
    ];

    public static $property_casts = [
        'language_id' => 'string',
        'currency_id' => 'string',
        'payment_terms' => 'string',
        'valid_until' => 'string',
        'default_task_rate' => 'float',
        'send_reminders' => 'bool',
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
        parent::__construct($obj);
    }

    /**
     * Default Client Settings scaffold.
     *
     * @return stdClass
     */
    public static function defaults() : stdClass
    {
        $data = (object) [
            'entity' => (string) Client::class,
            'industry_id' => '',
            'size_id' => '',
        ];

        return self::setCasts($data, self::$casts);
    }

    /**
     * Merges settings from Company to Client.
     *
     * @param  stdClass $company_settings
     * @param  stdClass $client_settings
     * @return stdClass of merged settings
     */
    public static function buildClientSettings($company_settings, $client_settings)
    {
        if (! $client_settings) {
            return $company_settings;
        }

        foreach ($company_settings as $key => $value) {
            /* pseudo code
                if the property exists and is a string BUT has no length, treat it as TRUE
            */
            if (((property_exists($client_settings, $key) && is_string($client_settings->{$key}) && (iconv_strlen($client_settings->{$key}) < 1)))
                || ! isset($client_settings->{$key})
                && property_exists($company_settings, $key)) {
                $client_settings->{$key} = $company_settings->{$key};
            }
        }

        return $client_settings;
    }
}
