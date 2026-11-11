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

namespace Tests\Feature\Export;

use App\Export\CSV\ClientExport;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Tests\MockAccountData;
use Tests\TestCase;

class ReportCsvEncodingTest extends TestCase
{
    use MockAccountData;

    private const ARABIC_CLIENT_NAME = 'شركة الاختبار';

    private const ARABIC_PUBLIC_NOTES = 'ملاحظات العميل باللغة العربية';

    private const CLIENT_NAME_HEADER = 'Name';

    private const CLIENT_PUBLIC_NOTES_HEADER = 'Public Notes';

    private const ARTIFACT_FILENAME = 'report_csv_encoding_arabic_client.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutExceptionHandling();

        $database = array_key_exists('db-ninja-01', config('database.connections')) ? 'db-ninja-01' : config('ninja.db.default');

        config([
            'database.default' => $database,
            'ninja.db.default' => $database,
            'queue.default' => 'sync',
        ]);

        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        if ($this->account && $this->account->exists) {
            $this->account->forceDelete();
        }

        parent::tearDown();
    }

    public function test_client_report_csv_preserves_arabic_as_valid_utf8(): void
    {
        $csv = $this->buildArabicClientCsv();

        $this->assertValidUtf8Csv($csv);

        $record = $this->firstCsvRecord($csv);

        $this->assertSame(self::ARABIC_CLIENT_NAME, $record[self::CLIENT_NAME_HEADER]);
        $this->assertSame(self::ARABIC_PUBLIC_NOTES, $record[self::CLIENT_PUBLIC_NOTES_HEADER]);
    }

    public function test_client_report_csv_only_garbles_when_a_client_decodes_the_bytes_with_the_wrong_charset(): void
    {
        $csv = $this->buildArabicClientCsv();

        $this->assertValidUtf8Csv($csv);

        $wronglyDecodedCsv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        $mojibakeClientName = mb_convert_encoding(self::ARABIC_CLIENT_NAME, 'UTF-8', 'Windows-1252');

        $this->assertStringContainsString(self::ARABIC_CLIENT_NAME, $csv);
        $this->assertStringNotContainsString(self::ARABIC_CLIENT_NAME, $wronglyDecodedCsv);
        $this->assertStringContainsString($mojibakeClientName, $wronglyDecodedCsv);
    }

    public function test_cached_report_download_streams_the_same_valid_utf8_csv_bytes(): void
    {
        $csv = $this->buildArabicClientCsv();
        $hash = (string) Str::uuid();

        Cache::put($hash, base64_encode($csv), 60);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/exports/preview/{$hash}");

        $response->assertStatus(200);
        $response->assertStreamed();
        $response->assertDownload('report.csv');
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $downloadedCsv = $response->streamedContent();
        $artifactPath = $this->writeCsvArtifact($downloadedCsv);

        $this->assertSame($csv, $downloadedCsv);
        $this->assertFileExists($artifactPath);
        $this->assertSame($downloadedCsv, file_get_contents($artifactPath));
        $this->assertValidUtf8Csv($downloadedCsv);
        $this->assertSame(self::ARABIC_CLIENT_NAME, $this->firstCsvRecord($downloadedCsv)[self::CLIENT_NAME_HEADER]);
    }

    private function buildArabicClientCsv(): string
    {
        $this->client->name = self::ARABIC_CLIENT_NAME;
        $this->client->public_notes = self::ARABIC_PUBLIC_NOTES;
        $this->client->save();

        $export = new ClientExport($this->company, [
            'client_id' => $this->client->hashed_id,
            'date_range' => 'all',
            'include_deleted' => false,
            'report_keys' => [
                'client.name',
                'client.public_notes',
            ],
            'send_email' => false,
            'user_id' => $this->user->id,
        ]);

        return $export->run();
    }

    private function assertValidUtf8Csv(string $csv): void
    {
        $this->assertTrue(mb_check_encoding($csv, 'UTF-8'));
        $this->assertStringNotContainsString("\xEF\xBF\xBD", $csv);
        $this->assertNotEmpty($this->firstCsvRecord($csv));
    }

    private function writeCsvArtifact(string $csv): string
    {
        $path = base_path('tests/artifacts/' . self::ARTIFACT_FILENAME);
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $csv);

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    private function firstCsvRecord(string $csv): array
    {
        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);

        $records = iterator_to_array($reader->getRecords(), false);

        return $records[0] ?? [];
    }
}
