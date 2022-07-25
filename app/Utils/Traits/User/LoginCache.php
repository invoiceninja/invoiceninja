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

namespace App\Utils\Traits\User;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

trait LoginCache
{
    public function setLoginCache($user)
    {
        $timeout = $user->company()->default_password_timeout;

        if ($timeout == 0) {
            $timeout = 30 * 60 * 1000 * 1000;
        } else {
            $timeout = $timeout / 1000;
        }

        Cache::put($user->hashed_id.'_'.$user->account_id.'_logged_in', Str::random(64), $timeout);
    }
}
