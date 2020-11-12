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
use stdClass;

class Helpers
{
    public static function sharedEmailVariables(?Client $client, array $settings = null): array
    {
    	if(!$client){

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
     * Return absolute url to asset based on envinronment.
     *  
     * @param string $path 
     * @return null|string 
     */
    public static function asset(string $path): ?string
    {
        if (config('ninja.docker_url')) {
            return config('ninja.docker_url') . parse_url(asset($path), PHP_URL_PATH);
        }

        return asset($path);
    }
}
