<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class TempFile
{
    public static function path($url): string
    {
        $temp_path = @tempnam(sys_get_temp_dir() . '/' . sha1((string) time()), basename($url));
        copy($url, $temp_path);

        return $temp_path;
    }

    /* Downloads a file to temp storage and returns the path - used for mailers */
    public static function filePath($data, $filename): string
    {
        $dir_hash = sys_get_temp_dir() . '/' . sha1(microtime());

        mkdir($dir_hash);

        $file_path = $dir_hash . '/' . $filename;

        file_put_contents($file_path, $data);

        return $file_path;
    }

    /* create a tmp file from a base64 string: https://gist.github.com/waska14/8b3bcebfad1f86f7fcd3b82927576e38*/
    public static function UploadedFileFromBase64(string $base64File, string|null $fileName = null, string|null $mimeType = null): UploadedFile
    {
        // Get file data base64 string
        $fileData = base64_decode(Arr::last(explode(',', $base64File)));

        // Create temp file and get its absolute path
        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        // Save file data in file
        file_put_contents($tempFilePath, $fileData);

        $tempFileObject = new File($tempFilePath);
        $file = new UploadedFile(
            $tempFileObject->getPathname(),
            $fileName ?: $tempFileObject->getFilename(),
            $mimeType ?: $tempFileObject->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        );

        // Close this file after response is sent.
        // Closing the file will cause to remove it from temp director!
        app()->terminating(function () use ($tempFile) {
            fclose($tempFile);
        });

        // return UploadedFile object
        return $file;
    }

    /* create a tmp file from a raw string: https://gist.github.com/waska14/8b3bcebfad1f86f7fcd3b82927576e38*/
    public static function UploadedFileFromRaw(string $fileData, string|null $fileName = null, string|null $mimeType = null): UploadedFile
    {
        // Create temp file and get its absolute path
        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        // Save file data in file
        file_put_contents($tempFilePath, $fileData);

        $tempFileObject = new File($tempFilePath);
        $file = new UploadedFile(
            $tempFileObject->getPathname(),
            $fileName ?: $tempFileObject->getFilename(),
            $mimeType ?: $tempFileObject->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        );

        // Close this file after response is sent.
        // Closing the file will cause to remove it from temp director!
        app()->terminating(function () use ($tempFile) {
            fclose($tempFile);
        });

        // return UploadedFile object
        return $file;
    }

    /* create a tmp file from a raw string: https://gist.github.com/waska14/8b3bcebfad1f86f7fcd3b82927576e38*/
    public static function UploadedFileFromUrl(string $url, string|null $fileName = null, string|null $mimeType = null): UploadedFile
    {
        // Create temp file and get its absolute path
        $tempFile = tmpfile();
        $tempFilePath = stream_get_meta_data($tempFile)['uri'];

        // Save file data in file
        file_put_contents($tempFilePath, file_get_contents($url));

        $tempFileObject = new File($tempFilePath);
        $file = new UploadedFile(
            $tempFileObject->getPathname(),
            $fileName ?: $tempFileObject->getFilename(),
            $mimeType ?: $tempFileObject->getMimeType(),
            0,
            true // Mark it as test, since the file isn't from real HTTP POST.
        );

        // Close this file after response is sent.
        // Closing the file will cause to remove it from temp director!
        app()->terminating(function () use ($tempFile) {
            fclose($tempFile);
        });

        // return UploadedFile object
        return $file;
    }
}
