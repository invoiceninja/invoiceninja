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

use App\Models\Company;
use App\Models\User;

class ProtectedZipDownloadStore
{
    public function __construct(
        private ArchiveWriter $archive_writer,
        private TemporaryDownloadPublisher $publisher,
    ) {}

    /**
     * @param array<int, array{file: string, file_name: string, mime: string}> $files
     */
    public function store(
        array $files,
        string $archive_name,
        Company $company,
        ?User $user = null,
    ): ProtectedDownloadResult {
        return $this->publisher->publish(
            contents: $this->archive_writer->write($files),
            storage_path: $company->file_path() . "downloads/{$archive_name}",
            download_name: $archive_name,
            expires_at: now()->addHour(),
            user: $user,
        );
    }
}
