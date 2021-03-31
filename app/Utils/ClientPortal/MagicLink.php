<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\ClientPortal;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MagicLink
{
    
    //return a magic login link URL
    public static function create($email) :string
    {
        $magic_key = Str::random(64);
        $timeout = 600; //seconds

        Cache::add($magic_key, $email, $timeout);

        return route('client.contact_magic_link', ['magic_link' => $magic_key]);
    }
}
