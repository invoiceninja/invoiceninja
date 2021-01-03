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

namespace App\Utils;

class TempFile
{
    public static function path($url) :string
    {
        $temp_path = tempnam(sys_get_temp_dir(), basename($url));
        copy($url, $temp_path);

        return $temp_path;
    }
}
