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

use App\Models\Account;
use App\Utils\CurlUtils;
use Illuminate\Support\Facades\DB;

/**
 * Class Ninja.
 */
class Ninja
{
    const TEST_USERNAME = 'user@example.com';

    public static function isSelfHost()
    {
        return config('ninja.environment') === 'selfhost';
    }

    public static function isHosted()
    {
        return config('ninja.environment') === 'hosted';
    }

    public static function isNinja()
    {
        return config('ninja.production');
    }

    public static function isNinjaDev()
    {
        return config('ninja.environment') == 'development';
    }

    public static function getDebugInfo()
    {
        $mysql_version = DB::select(DB::raw('select version() as version'))[0]->version;

        $info = 'App Version: v'.config('ninja.app_version').'\\n'.
            'White Label: '.'\\n'. // TODO: Implement white label with hasFeature.
            'Server OS: '.php_uname('s').' '.php_uname('r').'\\n'.
            'PHP Version: '.phpversion().'\\n'.
            'MySQL Version: '.$mysql_version;

        return $info;
    }

    public static function boot()
    {
        if (self::isNinjaDev()) {
            return true;
        }

        $data = [
            'license' => config('ninja.license'),
        ];

        $data = trim(CurlUtils::post('https://license.invoiceninja.com/api/check', $data));
        $data = json_decode($data);

        if ($data && property_exists($data, 'message') && $data->message == sha1(config('ninja.license'))) {
            return true;
        } else {
            return false;
        }
    }

    public static function parse()
    {
        return 'Invalid license.';
    }

    public static function selfHostedMessage()
    {
        return 'Self hosted installation limited to one account';
    }

    public static function registerNinjaUser($user)
    {
        if (! $user || $user->email == self::TEST_USERNAME || self::isNinjaDev()) {
            return false;
        }

        $url = config('ninja.license_url').'/signup/register';
        $data = '';
        $fields = [
            'first_name' => urlencode($user->first_name),
            'last_name' => urlencode($user->last_name),
            'email' => urlencode($user->email),
        ];

        foreach ($fields as $key => $value) {
            $data .= $key.'='.$value.'&';
        }
        rtrim($data, '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function eventVars()
    {
        return [
            'ip' => request()->getClientIp(),
            'token' => request()->header('X-API-TOKEN'),
            'is_system' => app()->runningInConsole(),
        ];
    }
}
