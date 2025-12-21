<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class Atomic
{
    public static function set($key, $value = true, $ttl = 1): bool
    {
        $new_ttl = now()->addSeconds($ttl);

        try {
            return Redis::connection('sentinel-cache')->set($key, $value, 'EX', $ttl, 'NX') ? true : false;
        } catch (\Throwable) {
            return Cache::add($key, $value, $new_ttl) ? true : false;
        }
    
    }

    public static function get($key)
    {
        try {
            return Redis::connection('sentinel-cache')->get($key);
        } catch (\Throwable) {
            return Cache::get($key);
        }

    }

    public static function del($key)
    {
        try {
            return Redis::connection('sentinel-cache')->del($key);
        } catch (\Throwable) {
            return Cache::forget($key);
        }
    }
}