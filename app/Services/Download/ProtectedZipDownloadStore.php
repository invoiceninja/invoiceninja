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
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

class ProtectedZipDownloadStore
{
    /**
     * @param array<int, array{file: string, file_name: string, mime: string}> $files
     */
    public function store(
        array $files,
        string $archive_name,
        Company $company,
        ?User $user = null,
    ): ProtectedDownloadResult {
        $expiry_hours = 1;

        $zip = new \PhpZip\ZipFile();

        try {
            foreach ($files as $file) {
                $zip->addFromString($file['file_name'], base64_decode($file['file']));
            }

            $storage_path = $company->file_path()."downloads/{$archive_name}";

            if (! Storage::disk(config('filesystems.default'))->put($storage_path, $zip->outputAsString())) {
                throw new RuntimeException("Unable to store protected download at {$storage_path}.");
            }
        } finally {
            $zip->close();
        }

        $hash = Str::uuid()->toString();
        $expires_at = now()->addHours($expiry_hours);

        Cache::put($hash, $storage_path, $expires_at);

        UnlinkFile::dispatch(config('filesystems.default'), $storage_path)->delay($expires_at);

        $url = URL::temporarySignedRoute('protected_download', $expires_at, ['hash' => $hash]);

        nlog($url);
        
        if ($user) {
            DownloadAvailable::notify(
                $user,
                $url,
                $archive_name,
            );
        }

        return new ProtectedDownloadResult(
            url: $url,
            hash: $hash,
            storage_path: $storage_path,
            expires_at: $expires_at,
        );
    }
}
