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

namespace App\Http\Controllers;

use App\Exceptions\SystemError;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProtectedDownloadController extends BaseController
{
    public function index(Request $request, string $hash)
    {
        /** @var string $hashed_path */
        $hashed_path = Cache::get($hash);

        if (!$hashed_path) {
            throw new SystemError('File no longer available', 404);
        }

        if (!Storage::exists($hashed_path)) {
            throw new SystemError('File not found', 404);
        }

        $file_size = Storage::size($hashed_path);
        $filename = basename($hashed_path);
        $mime_type = Storage::mimeType($hashed_path) ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($hashed_path) {
            $stream = Storage::readStream($hashed_path);

            // if($stream ===false){
            if ($stream === null) {
                throw new SystemError('Unable to read file', 500);
            }

            // Stream the file in chunks to avoid memory issues
            while (!feof($stream)) {
                $chunk = fread($stream, 8192); // 8KB chunks
                if ($chunk === false) {
                    break;
                }
                echo $chunk;

                // Flush output buffer to ensure data is sent immediately
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => $mime_type,
            'Content-Length' => $file_size,
        ]);
    }
}
