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

namespace App\Utils\ClientPortal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MagicLink
{
    //return a magic login link URL
    public static function create($email, $company_id, $url = null) :string
    {
        $magic_key = Str::random(64);
        $timeout = 600; //seconds

        $payload = [
            'email' => $email,
            'company_id' => $company_id,
        ];

        Cache::add($magic_key, $payload, $timeout);

        return route('client.contact_magic_link', ['magic_link' => $magic_key, 'redirect' => $url]);
    }
}
