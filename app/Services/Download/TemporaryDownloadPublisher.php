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

namespace App\Services\Download;

use App\Events\Socket\DownloadAvailable;
use App\Jobs\Util\UnlinkFile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TemporaryDownloadPublisher
{
    public function publish(
        string $contents,
        string $storage_path,
        string $download_name,
        Carbon $expires_at,
        ?User $user = null,
    ): ProtectedDownloadResult {
        try {
            $disk = config('filesystems.protected_download_disk');

            if (! is_string($disk) || $disk === '') {
                throw new RuntimeException('Protected download disk is not configured.');
            }

            $hash = Str::uuid()->toString();

            if (config('filesystems.protected_download_allow_unsigned')) {
                $url = URL::route('protected_download', ['hash' => $hash]);
            } else {
                $signed_path = URL::temporarySignedRoute(
                    'protected_download',
                    $expires_at,
                    ['hash' => $hash],
                    absolute: false,
                );

                $url = URL::to($signed_path);
            }

            if (! Storage::disk($disk)->put($storage_path, $contents, 'private')) {
                throw new RuntimeException('Unable to store protected download.');
            }

            $record = [
                'disk' => $disk,
                'path' => $storage_path,
                'download_name' => $download_name,
                'expires_at' => $expires_at->timestamp,
            ];

            if (! Cache::put($hash, $record, $expires_at)) {
                throw new RuntimeException('Unable to cache protected download.');
            }

            UnlinkFile::dispatch($disk, $storage_path)->delay($expires_at);

            if ($user) {
                DownloadAvailable::notify($user, $url, $download_name);
            }

            return new ProtectedDownloadResult(
                url: $url,
                hash: $hash,
                storage_path: $storage_path,
                expires_at: $expires_at,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to publish protected download.', 500, $exception);
        }
    }
}
