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

namespace App\DataMapper;

use App\Models\Client;
use App\Models\User;

/**
 * Class DefaultSettings.
 */
class DefaultSettings extends BaseSettings
{
    /**
     * @var int
     */
    public static $per_page = 25;

    /**
     * @return \stdClass
     *
     * //todo user specific settings / preferences.
     */
    public static function userSettings() : \stdClass
    {
        return (object) [
        //    class_basename(User::class) => self::userSettingsObject(),
        ];
    }

    /**
     * @return \stdClass
     */
    private static function userSettingsObject() : \stdClass
    {
        return (object) [
        //    'per_page' => self::$per_page,
        ];
    }
}
