<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils;

use App\Models\Client;
use App\Utils\Traits\MakesDates;
use stdClass;

class Helpers
{
    use MakesDates;

    public static function sharedEmailVariables(?Client $client, array $settings = null): array
    {
        if (!$client) {
            $elements['signature'] = '';
            $elements['settings'] = new stdClass;
            $elements['whitelabel'] = true;

            return $elements;
        }

        $_settings = is_null($settings) ? $client->getMergedSettings() : $settings;

        $elements['signature'] = $_settings->email_signature;
        $elements['settings'] = $_settings;
        $elements['whitelabel'] = $client->user->account->isPaid() ? true : false;

        return $elements;
    }

    /**
     * A centralised method to format the custom fields content.
     * 
     * @param mixed|null $custom_fields
     * @param mixed $field 
     * @param mixed $value 
     * @param \App\Models\Client|null $client 
     * 
     * @return null|string 
     */
    public function formatCustomFieldValue($custom_fields = null, $field, $value, Client $client = null): ?string
    {
        $custom_field = '';

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode('|', $custom_field);

            if (count($custom_field_parts) >= 2) {
                $custom_field = $custom_field_parts[1];
            }
        }

        switch ($custom_field) {
            case 'date':
                return is_null($client) ? $value : $this->formatDate($value, $client->date_format());
                break;

            case 'switch':
                return trim($value) == 'yes' ? ctrans('texts.yes') : ctrans('texts.no');
                break;

            default:
                return is_null($value) ? '' : $value;
                break;
        }
    }

    /**
     * A centralised method to make custom field.
     * @param mixed|null $custom_fields 
     * @param mixed $field 
     * 
     * @return string 
     */
    public function makeCustomField($custom_fields = null, $field): string
    {
        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};

            $custom_field_parts = explode('|', $custom_field);

            return $custom_field_parts[0];
        }

        return '';
    }
}
