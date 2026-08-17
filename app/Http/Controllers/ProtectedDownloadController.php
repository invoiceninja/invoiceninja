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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProtectedDownloadController extends BaseController
{
    public function index(string $hash): StreamedResponse
    {
        $download = Cache::get($hash);

        if (! $download) {
            throw new SystemError('File no longer available', 404);
        }

        if (is_string($download)) {
            $disk = config('filesystems.default');
            $storage_path = $download;
            $download_name = basename($storage_path);
        } elseif (
            is_array($download)
            && is_string($download['disk'] ?? null)
            && is_string($download['path'] ?? null)
            && is_string($download['download_name'] ?? null)
            && is_int($download['expires_at'] ?? null)
        ) {
            $disk = $download['disk'];
            $storage_path = $download['path'];
            $download_name = $download['download_name'];

            if ($download['expires_at'] <= now()->timestamp) {
                throw new SystemError('File no longer available', 404);
            }
        } else {
            throw new SystemError('File no longer available', 404);
        }

        if (! is_string($disk) || $disk === '') {
            throw new SystemError('File no longer available', 404);
        }

        $storage = Storage::disk($disk);

        if (! $storage->exists($storage_path)) {
            throw new SystemError('File not found', 404);
        }

        $file_size = $storage->size($storage_path);
        $mime_type = $storage->mimeType($storage_path) ?: 'application/octet-stream';

        return response()->streamDownload(function () use ($storage, $storage_path): void {
            $stream = $storage->readStream($storage_path);

            if (! is_resource($stream)) {
                throw new SystemError('Unable to read file', 500);
            }

            try {
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);

                    if ($chunk === false) {
                        throw new SystemError('Unable to read file', 500);
                    }

                    echo $chunk;

                    if (ob_get_level()) {
                        ob_flush();
                    }

                    flush();
                }
            } finally {
                fclose($stream);
            }
        }, $download_name, [
            'Content-Type' => $mime_type,
            'Content-Length' => $file_size,
        ]);
    }
}
