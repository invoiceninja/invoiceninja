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

namespace Tests\Unit;

use App\Services\Download\ArchiveWriter;
use PhpZip\ZipFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ArchiveWriterTest extends TestCase
{
    public function testWriteCreatesArchiveFromRawContents(): void
    {
        $archive = (new ArchiveWriter())->write([
            ['contents' => 'name,value', 'file_name' => 'report.csv'],
            ['contents' => '%PDF-1.4', 'file_name' => 'report.pdf'],
        ]);

        $zip = (new ZipFile())->openFromString($archive);

        try {
            $this->assertSame(['report.csv', 'report.pdf'], $zip->getListFiles());
            $this->assertSame('name,value', $zip->getEntryContents('report.csv'));
            $this->assertSame('%PDF-1.4', $zip->getEntryContents('report.pdf'));
        } finally {
            $zip->close();
        }
    }

    public function testWriteStandardizesArchiveFailures(): void
    {
        try {
            (new ArchiveWriter())->write([
                ['contents' => 'name,value', 'file_name' => ''],
            ]);

            $this->fail('Expected archive creation to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to create protected download archive.', $exception->getMessage());
            $this->assertSame(500, $exception->getCode());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }
}
