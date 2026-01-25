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

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Cache;

class Atomic
{
    public static function set(string $key, mixed $value = true, int $ttl = 1): bool
    {
        $new_ttl = now()->addSeconds($ttl);

        try {
            /** @var RedisFactory $redis */
            $redis = app('redis');
            $result = $redis->connection('sentinel-cache')->command('set', [$key, $value, 'EX', $ttl, 'NX']);
            return (bool) $result;
        } catch (\Throwable) {
            return Cache::add($key, $value, $new_ttl) ? true : false;
        }
    }

    public static function get(string $key): mixed
    {
        try {
            /** @var RedisFactory $redis */
            $redis = app('redis');
            return $redis->connection('sentinel-cache')->command('get', [$key]);
        } catch (\Throwable) {
            return Cache::get($key);
        }
    }

    public static function del(string $key): mixed
    {
        try {
            /** @var RedisFactory $redis */
            $redis = app('redis');
            return $redis->connection('sentinel-cache')->command('del', [$key]);
        } catch (\Throwable) {
            return Cache::forget($key);
        }
    }
}