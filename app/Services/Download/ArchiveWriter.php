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

use PhpZip\ZipFile;
use RuntimeException;
use Throwable;

class ArchiveWriter
{
    /**
     * @param array<int, array{file: string, file_name: string, mime: string}> $files
     */
    public function write(array $files): string
    {
        $zip = new ZipFile();

        try {
            foreach ($files as $file) {
                $contents = base64_decode($file['file'], true);

                if ($contents === false) {
                    throw new RuntimeException('Archive entry content is not valid base64.');
                }

                $zip->addFromString($file['file_name'], $contents);
            }

            return $zip->outputAsString();
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to create protected download archive.', 500, $exception);
        } finally {
            $zip->close();
        }
    }
}
