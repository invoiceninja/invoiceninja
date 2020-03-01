<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils;

use Illuminate\Support\Facades\DB;

/**
 * Class Ninja.
 */
class Ninja
{
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

    public static function getDebugInfo()
    {
        $mysql_version = DB::select(DB::raw("select version() as version"))[0]->version;
        
        $info = "App Version: v" . config('ninja.app_version') . "\\n" .
            "White Label: " . "\\n" . // TODO: Implement white label with hasFeature.
            "Server OS: " . php_uname('s') . ' ' . php_uname('r') . "\\n" .
            "PHP Version: " . phpversion() . "\\n" .
            "MySQL Version: " . $mysql_version;

        return $info;
    }
}
