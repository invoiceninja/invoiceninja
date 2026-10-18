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
use RuntimeException;
use Throwable;

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
            contents: $this->archive_writer->write($this->buildArchiveEntries($files)),
            storage_path: $company->file_path() . "downloads/{$archive_name}",
            download_name: $archive_name,
            expires_at: now()->addHour(),
            user: $user,
        );
    }

    /**
     * @param array<int, array{file: string, file_name: string, mime: string}> $files
     * @return array<int, array{contents: string, file_name: string}>
     */
    private function buildArchiveEntries(array $files): array
    {
        try {
            return array_map(function (array $file): array {
                $contents = base64_decode($file['file'], true);

                if ($contents === false) {
                    throw new RuntimeException('Archive entry content is not valid base64.');
                }

                return [
                    'contents' => $contents,
                    'file_name' => $file['file_name'],
                ];
            }, $files);
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to create protected download archive.', 500, $exception);
        }
    }
}
