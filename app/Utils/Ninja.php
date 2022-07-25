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

namespace App\Utils;

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
            'MySQL Version: '.$mysql_version.'\\n'.
            'Version: '.request()->has('version') ? request()->input('version') : 'No Version Supplied.';

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

    public static function eventVars($user_id = null)
    {
        $ip = '';

        if (request()->hasHeader('Cf-Connecting-Ip')) {
            $ip = request()->header('Cf-Connecting-Ip');
        } elseif (request()->hasHeader('X-Forwarded-For')) {
            $ip = request()->header('X-Forwarded-For');
        } else {
            $ip = request()->ip() ?: ' ';
        }

        return [
            'ip' => $ip,
            'token' => request()->header('X-API-TOKEN'),
            'is_system' => app()->runningInConsole(),
            'user_id' => $user_id,
        ];
    }

    public static function transformTranslations($settings) :array
    {
        $translations = [];

        $trans = (array) $settings->translations;

        if (count($trans) == 0) {
            return $translations;
        }

        foreach ($trans as $key => $value) {
            $translations['texts.'.$key] = $value;
        }

        return $translations;
    }

    public function createLicense($request)
    {
        // $affiliate = Affiliate::where('affiliate_key', '=', SELF_HOST_AFFILIATE_KEY)->first();
        // $email = trim(Input::get('email'));

        // if (! $email || $email == TEST_USERNAME) {
        //     return RESULT_FAILURE;
        // }

        // $license = new License();
        // $license->first_name = Input::get('first_name');
        // $license->last_name = Input::get('last_name');
        // $license->email = $email;
        // $license->transaction_reference = Request::getClientIp();
        // $license->license_key = self::generateLicense();
        // $license->affiliate_id = $affiliate->id;
        // $license->product_id = PRODUCT_SELF_HOST;
        // $license->is_claimed = 1;
        // $license->save();

        // return RESULT_SUCCESS;
    }

    // public static function generateLicense()
    // {
    //     $parts = [];
    //     for ($i = 0; $i < 5; $i++) {
    //         $parts[] = strtoupper(str_random(4));
    //     }

    //     return implode('-', $parts);
    // }
    //

    /*
     * Available - but not recommended for use
     *
     * This will guarantee a given string IS the correct format for a
     * base64 encoded string ,
     * but can't guarantee that it is a base64 encoded string
     *
     */
    public static function isBase64Encoded(string $s) : bool
    {

    // Check if there are valid base64 characters
        if (! preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s)) {
            return false;
        }
        // Decode the string in strict mode and check the results
        $decoded = base64_decode($s, true);
        if (false === $decoded) {
            return false;
        }
        // if string returned contains not printable chars
        if (0 < preg_match('/((?![[:graph:]])(?!\s)(?!\p{L}))./', $decoded, $matched)) {
            return false;
        }
        // Encode the string again
        if (base64_encode($decoded) != $s) {
            return false;
        }

        return true;
    }
}
