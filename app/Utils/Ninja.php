<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Class Ninja.
 */
class Ninja
{
    public const TEST_USERNAME = 'user@example.com';

    public static function isSelfHost()
    {
        return config('ninja.environment') === 'selfhost';
    }

    public static function isHosted()
    {
        return config('ninja.environment') === 'hosted';
    }

    /**
     * The new Flutter web app, unpacked from its release by react_release.yml: its boot page, and the
     * build folder the page names. A git checkout has neither.
     */
    public static function hasFlutterWebBuild(): bool
    {
        $page = self::flutterWebFile('flutter/index.html');
        $id = $page === null ? null : self::flutterWebBuildId((string) file_get_contents($page));

        return $id !== null && is_file(public_path("flutter/{$id}/flutter_bootstrap.js"));
    }

    /**
     * The build's folder under public/flutter/ that a boot page loads from: its base href is
     * <base href="__IN_BASE_HREF__<id>/">, so every URL the app loads changes with each build and
     * can be cached without rules on the server.
     */
    public static function flutterWebBuildId(string $page): ?string
    {
        // Exactly one base tag, as react_release.yml checks: with two, a browser and a regex could disagree.
        if (preg_match_all('#<base[^a-z0-9_-]#i', $page) !== 1) {
            return null;
        }

        return preg_match('#<base href="__IN_BASE_HREF__([0-9a-f]{8,64})/">#', $page, $match) === 1 ? $match[1] : null;
    }

    /**
     * A plain HTML page under resources/views (the Flutter web app's boot page, unpacked from its
     * release, or the committed landing page), found where views are. Read as a file, never compiled.
     */
    public static function flutterWebFile(string $name): ?string
    {
        foreach (view()->getFinder()->getPaths() as $path) {
            if (is_file($path.'/'.$name)) {
                return $path.'/'.$name;
            }
        }

        return null;
    }

    /**
     * The Flutter web app's boot page, its __IN_*__ tokens filled with escaped values.
     * A token this server doesn't know is left empty.
     *
     * @param array<string, string> $values Keyed by token name, e.g. 'BASE_HREF'
     */
    public static function flutterWebPage(array $values): string
    {
        $html = (string) file_get_contents((string) self::flutterWebFile('flutter/index.html'));

        return (string) preg_replace_callback('/__IN_([A-Z_]+)__/', fn ($match) => e($values[$match[1]] ?? ''), $html);
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
        $mysql_version = DB::select('select version() as version')[0]->version;

        $version = request()->input('version', 'No Version Supplied.');

        $info = 'App Version: v' . config('ninja.app_version') . '\\n'
            . 'White Label: ' . '\\n' // TODO: Implement white label with hasFeature.
            . 'Server OS: ' . php_uname('s') . ' ' . php_uname('r') . '\\n'
            . 'PHP Version: ' . phpversion() . '\\n'
            . 'MySQL Version: ' . $mysql_version . '\\n'
            . 'Version: ' . $version;

        return $info;
    }

    public static function boot()
    {
        if (self::isNinjaDev()) {
            return true;
        }

        try {
            $response = Http::asForm()->post('https://license.invoicing.co/api/check', [
                'license' => config('ninja.license'),
            ]);
            $data = json_decode(trim($response->body()));
        } catch (\Throwable) {
            return false;
        }

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

        try {
            Http::asForm()->post($url, [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
            ]);
        } catch (\Throwable) {
            // Fire-and-forget registration ping; signup must not fail if license server is down.
        }
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
            'user_id' => (app()->runningInConsole() && $ip == '127.0.0.1') ? null : $user_id,
        ];
    }

    public static function transformTranslations($settings): array
    {
        $translations = [];

        $trans = (array) $settings->translations;

        if (count($trans) == 0) {
            return $translations;
        }

        foreach ($trans as $key => $value) {
            $translations['texts.' . $key] = $value;
        }

        return $translations;
    }

    public static function triggerForwarding(string $company_key, string $email)
    {
        try {
            $x = Http::withHeaders([
                'X-API-HOSTED-SECRET' => config('ninja.ninja_hosted_secret'),
            ])->post(config('ninja.license_url') . '/api/v1/enable_forwarding', [
                'account_key' => $company_key,
                'email' => $email,
            ]);

            nlog($x->body());
        } catch (\Exception $e) {
            nlog("Attempt forwarding for {$email} - {$company_key} Failed");
        }
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
    public static function isBase64Encoded(string $s): bool
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
