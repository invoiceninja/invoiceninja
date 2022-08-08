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

class TempFile
{
    public static function path($url) :string
    {
        $temp_path = @tempnam(sys_get_temp_dir().'/'.sha1(time()), basename($url));
        copy($url, $temp_path);

        return $temp_path;
    }

    /* Downloads a file to temp storage and returns the path - used for mailers */
    public static function filePath($data, $filename) :string
    {
        $dir_hash = sys_get_temp_dir().'/'.sha1(microtime());

        mkdir($dir_hash);

        $file_path = $dir_hash.'/'.$filename;

        file_put_contents($file_path, $data);

        return $file_path;
    }
}
