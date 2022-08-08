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

namespace App\DataMapper;

use App\Models\User;
use stdClass;

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
     * @return stdClass
     */
    public static function userSettings() : stdClass
    {
        return (object) [
            //    class_basename(User::class) => self::userSettingsObject(),
        ];
    }

    /**
     * @return stdClass
     */
    private static function userSettingsObject() : stdClass
    {
        return (object) [
            //    'per_page' => self::$per_page,
        ];
    }
}
