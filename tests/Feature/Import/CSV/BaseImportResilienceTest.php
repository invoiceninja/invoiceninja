<?php

/**
 * Invoice Ninja (https://www.invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Import\CSV;

use App\Import\ImportException;
use App\Import\Providers\Csv;
use App\Models\Client;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;
use Throwable;
use TypeError;

class BaseImportResilienceTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['database.default' => config('ninja.db.default')]);

        $this->makeTestData();
    }

    public function testShortRowsArePaddedWithoutChangingFollowingRows(): void
    {
        $importer = $this->newImporter('client', [
            0 => 'client.name',
            1 => 'contact.email',
            2 => 'client.currency_id',
        ]);

        $data = $this->loadFixture($importer, 'client', 'csv_short_row_then_complete.csv');
        $result = $importer->preTransformCsv($data, 'client');

        $this->assertSame([
            1 => [
                'client.name' => 'Short row',
                'contact.email' => '',
                'client.currency_id' => '',
            ],
            2 => [
                'client.name' => 'Complete row',
                'contact.email' => 'complete@gmail.com',
                'client.currency_id' => 'AUD',
            ],
        ], $result);

        $importer->import('client');

        $short_client = Client::query()
            ->where('company_id', $this->company->id)
            ->where('name', 'Short row')
            ->firstOrFail();
        $complete_client = Client::query()
            ->with('contacts')
            ->where('company_id', $this->company->id)
            ->where('name', 'Complete row')
            ->firstOrFail();

        $this->assertSame('', $short_client->contacts->first()->email);
        $this->assertSame('complete@gmail.com', $complete_client->contacts->first()->email);
        $this->assertSame('AUD', app('currencies')->firstWhere('id', $complete_client->settings->currency_id)->code);
        $this->assertSame([], $importer->getErrors());
    }

    public function testOverWideRowsAreRejectedWithTheirSourceRowNumber(): void
    {
        $importer = $this->newImporter('client', [
            0 => 'client.name',
            1 => 'contact.email',
        ]);

        $data = $this->loadFixture($importer, 'client', 'csv_overwide_row.csv');
        $result = $importer->preTransformCsv($data, 'client');

        $this->assertSame([
            1 => [
                'client.name' => 'Valid row',
                'contact.email' => 'valid@gmail.com',
            ],
        ], $result);

        $error = $importer->getErrors()['client'][0];

        $this->assertSame('invalid_column_count', $error['code']);
        $this->assertSame(3, $error['client']['row']);
        $this->assertSame(2, $error['client']['expected_columns']);
        $this->assertSame(3, $error['client']['actual_columns']);
    }

    public function testOutOfRangeMappingsAreRejectedWithoutPaddingToTheMappingIndex(): void
    {
        $importer = $this->newImporter('client', [
            0 => 'client.name',
            1000000 => 'contact.email',
        ]);

        $data = $this->loadFixture($importer, 'client', 'csv_short_row_then_complete.csv');
        $result = $importer->preTransformCsv($data, 'client');

        $this->assertSame([], $result);

        $error = $importer->getErrors()['client'][0];

        $this->assertSame('invalid_column_mapping', $error['code']);
        $this->assertSame(1000000, $error['client']['source_column']);
    }

    public function testSystemFailuresAreAggregatedForTheUserAndReportedOnce(): void
    {
        $importer = $this->newImporter('expense', [0 => 'expense.amount']);
        $importer->column_map['invoice'] = [0 => 'invoice.number'];
        $importer->transformer = new class {
            public function transform(array $record): array
            {
                throw new TypeError('Sensitive internal failure');
            }
        };

        $data = $this->loadFixture($importer, 'expense', 'csv_system_error_rows.csv');
        $expense_data = array_filter(
            $data,
            static fn(int $source_row): bool => $source_row <= 7,
            ARRAY_FILTER_USE_KEY
        );
        $invoice_data = [
            0 => $data[0],
            8 => $data[8],
        ];

        $expense_records = $importer->preTransformCsv($expense_data, 'expense');
        $invoice_records = $importer->preTransformCsv($invoice_data, 'invoice');

        $this->assertSame(0, $importer->ingest($expense_records, 'expense'));
        $this->assertSame(0, $importer->ingest($invoice_records, 'invoice'));
        $this->assertSame([], $importer->error_array);

        $errors = $importer->getErrors();

        $this->assertCount(1, $errors['expense']);
        $this->assertCount(1, $errors['invoice']);
        $this->assertSame('system_error', $errors['expense'][0]['code']);
        $this->assertSame(7, $errors['expense'][0]['expense']['failed_records']);
        $this->assertSame([2, 3, 4, 5, 6], $errors['expense'][0]['expense']['sample_rows']);
        $this->assertSame(1, $errors['invoice'][0]['invoice']['failed_records']);
        $this->assertSame([9], $errors['invoice'][0]['invoice']['sample_rows']);
        $this->assertSame($errors['expense'][0]['reference'], $errors['invoice'][0]['reference']);
        $this->assertStringContainsString('7 expense records could not be imported because of a system error', $errors['expense'][0]['error']);
        $this->assertStringNotContainsString('Sensitive internal failure', $errors['expense'][0]['error']);
        $this->assertStringNotContainsString('private-value', json_encode($errors, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private-invoice-number', json_encode($errors, JSON_THROW_ON_ERROR));

        $importer->reportAggregatedSystemErrors();
        $importer->reportAggregatedSystemErrors();

        $this->assertSame(1, $importer->captured_exception_count);
        $this->assertInstanceOf(TypeError::class, $importer->captured_exception);
        $this->assertSame(8, $importer->captured_context['failures']);
        $this->assertSame(7, $importer->captured_context['entities']['expense']['count']);
        $this->assertSame(1, $importer->captured_context['entities']['invoice']['count']);
        $this->assertArrayNotHasKey('record', $importer->captured_context);
    }

    public function testImportExceptionsRemainActionableAndAreNotReportedAsSystemErrors(): void
    {
        $importer = $this->newImporter('invoice', [0 => 'invoice.number']);

        $importer->recordFailure(
            new ImportException('Invoice number already exists'),
            'invoice',
            ['invoice.number' => 'INV-1'],
            1
        );
        $importer->reportAggregatedSystemErrors();

        $errors = $importer->getErrors();

        $this->assertSame('Invoice number already exists', $errors['invoice'][0]['error']);
        $this->assertSame(0, $importer->captured_exception_count);
    }

    /** @param array<int, string> $mapping */
    private function newImporter(string $entity_type, array $mapping): TestableCsvImport
    {
        return new TestableCsvImport([
            'hash' => 'base-import-resilience-test',
            'column_map' => [
                $entity_type => ['mapping' => $mapping],
            ],
            'skip_header' => true,
            'import_type' => 'csv',
        ], $this->company);
    }

    /** @return array<int, array<int, string|null>> */
    private function loadFixture(TestableCsvImport $importer, string $entity_type, string $fixture): array
    {
        $contents = file_get_contents(base_path('tests/Fixtures/Import/' . $fixture));

        $this->assertNotFalse($contents);

        Cache::put($importer->hash . '-' . $entity_type, base64_encode($contents), 60);

        $data = $importer->getCsvData($entity_type);

        $this->assertIsArray($data);

        return $data;
    }
}

class TestableCsvImport extends Csv
{
    public int $captured_exception_count = 0;

    public ?Throwable $captured_exception = null;

    public array $captured_context = [];

    public function recordFailure(
        Throwable $exception,
        string $entity_type,
        mixed $record,
        int|string|null $source_row = null
    ): void {
        $this->handleImportFailure($exception, $entity_type, $record, $source_row);
    }

    public function reportAggregatedSystemErrors(): void
    {
        $this->reportSystemImportErrors();
    }

    protected function captureSystemImportException(Throwable $exception, array $context): void
    {
        $this->captured_exception_count++;
        $this->captured_exception = $exception;
        $this->captured_context = $context;
    }
}
