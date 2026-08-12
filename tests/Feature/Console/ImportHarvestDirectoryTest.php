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

namespace Tests\Feature\Console;

use Closure;
use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Import\Harvest\CsvImporter;
use App\Import\Harvest\Entity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TaxRate;
use App\Models\User;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request as LaravelRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Psr\Http\Message\RequestInterface;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportHarvestDirectoryTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public function test_it_imports_and_reconciles_every_record_in_a_harvest_directory(): void
    {
        set_time_limit(0);

        $directory = $this->harvestDirectory();
        $expected = app(CsvImporter::class)->build($directory);

        $this->assertDirectoryCoverage($directory, $expected);
        $this->assertSourceRowCounts($directory, $expected);

        $this->makeTestData();
        [$company, $api_token, $owner] = $this->scaffoldImportCompany();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->routeOutgoingHttpThroughApplication();

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => $api_token,
            'directory' => $directory,
        ]);
        $this->assertSame(0, $exit_code, "Harvest import failed:\n" . $this->failureOutput(Artisan::output()));

        $this->assertPersistedModelCounts($company, $owner, $expected['records']);
        $ids = $this->assertPersistedApiPayloads($api_token, $owner, $expected['records']);
        $this->assertPaymentApplications($company, $expected['records'][Entity::InvoicePayments->value], $ids);
        $this->assertInvoiceTaxRates($company, $expected['records'][Entity::Invoices->value]);
        $this->assertDocumentActions($company, $expected['records'][Entity::Estimates->value]);
    }

    public function test_it_applies_a_full_harvest_payment_to_its_real_invoice(): void
    {
        $this->harvestDirectory();
        $directory = storage_path('framework/testing/harvest-real-payment-' . Str::uuid());
        File::makeDirectory($directory, 0o755, true);

        try {
            File::put($directory . '/clients.csv', <<<'CSV'
                Client Name,Address
                Atlanta Music Project,"883 Dill Ave
                SW Atlanta, GA 30310"
                CSV);
            File::put($directory . '/invoices.csv', <<<'CSV'
                Issue Date,Last Payment Date,ID,PO Number,Client,Subject,Invoice Amount,Paid Amount,Balance,Subtotal,Discount,Tax,Tax2,Currency,Currency Symbol,Document Type,Client Address
                2011-08-01,2011-08-01,AMP.11.105,,Atlanta Music Project,AMP Website Updates - August 2011,425,425,0,425,0,0,0,United States Dollar - USD,$,Standard Invoice,"883 Dill Ave
                SW Atlanta, GA 30310"
                CSV);
            File::put($directory . '/invoice_lines.csv', <<<'CSV'
                Invoice ID,Client,Project,Item Type,Item Description,Item Quantity,Item Unit Price,Item Amount,Item Discount,Item Tax,Item Tax2,Currency,Invoice Type,Issue Date
                AMP.11.105,Atlanta Music Project,,Service,Retainer Payment for website updates - August 2011,10,42.5,425,0,0,0,USD,Standard,2011-08-01
                CSV);
            File::put($directory . '/payments.csv', <<<'CSV'
                Payment Date,Invoice ID,Invoice Issue Date,Client,Invoice Amount,Payment Amount,Tax,Tax2,Currency,Currency Symbol,Document Type
                2011-08-01,AMP.11.105,2011-08-01,Atlanta Music Project,425,425,0,0,United States Dollar - USD,$,Standard Invoice
                2011-08-02,AMP.11.105,2011-08-01,Atlanta Music Project,425,-5,0,0,United States Dollar - USD,$,Standard Invoice
                CSV);

            $this->makeTestData();
            [$company, $api_token] = $this->scaffoldImportCompany();
            $this->withoutMiddleware(ThrottleRequests::class);
            $this->routeOutgoingHttpThroughApplication();

            $exit_code = Artisan::call('ninja:import-harvest', [
                'api_token' => $api_token,
                'directory' => $directory,
            ]);

            $this->assertSame(0, $exit_code, "Harvest import failed:\n" . Artisan::output());
            $invoice = Invoice::query()->where('company_id', $company->id)->sole();
            $payments = Payment::query()->where('company_id', $company->id)->orderBy('date')->get();
            $this->assertSame(425.0, (float) $invoice->amount);
            $this->assertSame([425.0, -5.0], $payments->map(fn(Payment $payment): float => (float) $payment->amount)->all());
            $this->assertSame($invoice->id, $payments[0]->paymentables()->sole()->paymentable_id);
            $this->assertSame(0, $payments[1]->paymentables()->count());
        } finally {
            File::deleteDirectory($directory);
        }
    }

    private function failureOutput(string $output): string
    {
        $lines = preg_split('/\R/', $output) ?: [];
        $failures = array_values(array_filter(
            $lines,
            fn(string $line): bool => str_contains($line, 'ERROR')
                || str_contains($line, 'Unable')
                || str_contains($line, 'failed'),
        ));

        return implode("\n", $failures === [] ? array_slice($lines, -30) : $failures);
    }

    private function harvestDirectory(): string
    {
        $directory = getenv('HARVEST_IMPORT_TEST_DIRECTORY');

        if (! is_string($directory) || trim($directory) === '') {
            $this->markTestSkipped(
                'Set HARVEST_IMPORT_TEST_DIRECTORY to a directory containing a complete Harvest CSV export.',
            );
        }

        $resolved = realpath(str_replace('\ ', ' ', trim($directory)));

        if ($resolved === false || ! File::isDirectory($resolved)) {
            $this->fail("Harvest import test directory does not exist: {$directory}");
        }

        return $resolved;
    }

    /**
     * @return array{Company, string, User}
     */
    private function scaffoldImportCompany(): array
    {
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);
        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->settings = CompanySettings::defaults();
        $company->save();

        $owner = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('harvest-import-owner') . '@example.test',
        ]);
        $company_user = CompanyUserFactory::create($owner->id, $company->id, $this->account->id);
        $company_user->is_owner = true;
        $company_user->is_admin = true;
        $company_user->is_locked = false;
        $company_user->permissions = '[]';
        $company_user->save();

        $api_token = Str::random(64);
        $company_token = new CompanyToken();
        $company_token->user_id = $owner->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'Harvest full-import integration test';
        $company_token->token = $api_token;
        $company_token->is_system = true;
        $company_token->save();

        return [$company, $api_token, $owner];
    }

    private function routeOutgoingHttpThroughApplication(): void
    {
        $kernel = app(HttpKernel::class);

        $handler = function (RequestInterface $request, array $options) use ($kernel): PromiseInterface {
            set_time_limit(0);

            $uri = $request->getUri()->getPath();

            if (! str_starts_with($uri, '/api/v1/')) {
                return Create::rejectionFor(new RuntimeException("Unexpected external HTTP request during Harvest import: {$uri}"));
            }

            if ($request->getUri()->getQuery() !== '') {
                $uri .= '?' . $request->getUri()->getQuery();
            }

            $laravel_request = LaravelRequest::create(
                $uri,
                $request->getMethod(),
                [],
                [],
                [],
                [
                    'HTTPS' => 'on',
                    'HTTP_HOST' => $request->getUri()->getHost(),
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
                (string) $request->getBody(),
            );

            foreach ($request->getHeaders() as $name => $values) {
                $laravel_request->headers->set($name, $values);
            }

            $response = $kernel->handle($laravel_request);
            $content = $response->getContent();
            $kernel->terminate($laravel_request, $response);
            set_time_limit(0);

            return Create::promiseFor(new Psr7Response(
                $response->getStatusCode(),
                $response->headers->all(),
                $content === false ? '' : $content,
            ));
        };

        $factory = new HarvestKernelHttpFactory(app(Dispatcher::class), $handler);
        $this->app->instance(HttpFactory::class, $factory);
        Http::swap($factory);
    }

    /**
     * @param array{
     *     records: array<string, array<int, array<string, mixed>>>,
     *     files: array<string, array<int, string>>,
     *     unsupported_files: array<int, string>,
     *     unmatched_contacts: array<int, array<string, string>>
     * } $expected
     */
    private function assertDirectoryCoverage(string $directory, array $expected): void
    {
        $csv_file_count = collect(File::allFiles($directory))
            ->filter(fn(\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'csv')
            ->count();
        $recognized_file_count = array_sum(array_map('count', $expected['files']));

        $this->assertGreaterThan(0, $csv_file_count, 'The supplied directory must contain CSV files.');
        $this->assertSame(
            $csv_file_count,
            $recognized_file_count + count($expected['unsupported_files']),
            'Every CSV must be accounted for as recognized or unsupported.',
        );
        $this->assertSame([], $expected['unsupported_files'], 'The full-import directory contains unsupported CSV files.');
        $this->assertSame([], $expected['unmatched_contacts'], 'Every Harvest contact must resolve to a client.');

        foreach ([
            Entity::Clients,
            Entity::Tasks,
            Entity::ExpenseCategories,
            Entity::Projects,
            Entity::TimeEntries,
            Entity::Expenses,
            Entity::Invoices,
            Entity::InvoicePayments,
            Entity::Estimates,
        ] as $entity) {
            $this->assertNotEmpty(
                $expected['records'][$entity->value],
                "The full-import directory did not produce any {$entity->value} records.",
            );
        }
    }

    /**
     * @param array{
     *     records: array<string, array<int, array<string, mixed>>>,
     *     files: array<string, array<int, string>>,
     *     unsupported_files: array<int, string>,
     *     unmatched_contacts: array<int, array<string, string>>
     * } $expected
     */
    private function assertSourceRowCounts(string $directory, array $expected): void
    {
        $contacts = array_sum(array_map(
            fn(array $record): int => count($record['payload']['contacts'] ?? []),
            $expected['records'][Entity::Clients->value],
        ));
        $invoice_lines = array_sum(array_map(
            fn(array $record): int => count($record['payload']['line_items'] ?? []),
            $expected['records'][Entity::Invoices->value],
        ));

        $this->assertSame($this->sourceRowCount($directory, $expected['files']['clients'] ?? []), count($expected['records'][Entity::Clients->value]));
        $this->assertSame(
            $this->uniqueContactCount($directory, $expected['files']['contacts'] ?? []),
            $contacts,
            'Every distinct Harvest contact must be present under its client.',
        );
        $this->assertSame($this->sourceRowCount($directory, $expected['files']['time_entries'] ?? []), count($expected['records'][Entity::TimeEntries->value]));
        $this->assertSame($this->sourceRowCount($directory, $expected['files']['expenses'] ?? []), count($expected['records'][Entity::Expenses->value]));
        $this->assertSame($this->sourceRowCount($directory, $expected['files']['invoices'] ?? []), count($expected['records'][Entity::Invoices->value]));
        $this->assertSame($this->sourceRowCount($directory, $expected['files']['invoice_lines'] ?? []), $invoice_lines);
        $this->assertSame($this->sourceRowCount($directory, $expected['files']['invoice_payments'] ?? []), count($expected['records'][Entity::InvoicePayments->value]));
        $this->assertSame($this->sourceRowCount($directory, $expected['files']['estimates'] ?? []), count($expected['records'][Entity::Estimates->value]));
    }

    /** @param array<int, string> $filenames */
    private function sourceRowCount(string $directory, array $filenames): int
    {
        return count($this->sourceRows($directory, $filenames));
    }

    /** @param array<int, string> $filenames */
    private function uniqueContactCount(string $directory, array $filenames): int
    {
        $contacts = [];

        foreach ($this->sourceRows($directory, $filenames) as $row) {
            $client = mb_strtolower(trim((string) ($row['Client'] ?? '')));
            $email = mb_strtolower(trim((string) ($row['Email'] ?? '')));
            $phone = trim((string) (($row['Office Phone'] ?? '') ?: ($row['Mobile Phone'] ?? '')));
            $contact = $email !== ''
                ? "email:{$email}"
                : 'contact:' . mb_strtolower(trim(implode('|', [
                    (string) ($row['First Name'] ?? ''),
                    (string) ($row['Last Name'] ?? ''),
                    $phone,
                ])));
            $contacts["{$client}|{$contact}"] = true;
        }

        return count($contacts);
    }

    /**
     * @param array<int, string> $filenames
     * @return array<int, array<string, string|null>>
     */
    private function sourceRows(string $directory, array $filenames): array
    {
        $remaining = array_count_values($filenames);
        $rows = [];

        foreach (File::allFiles($directory) as $file) {
            $filename = $file->getFilename();

            if (($remaining[$filename] ?? 0) < 1) {
                continue;
            }

            $reader = Reader::from($file->getPathname(), 'r');
            $reader->setHeaderOffset(0);
            array_push($rows, ...iterator_to_array($reader->getRecords(), false));
            $remaining[$filename]--;
        }

        $this->assertSame(0, array_sum($remaining), 'Not every classified Harvest CSV could be reopened.');

        return $rows;
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     */
    private function assertPersistedModelCounts(Company $company, User $owner, array $records): void
    {
        $company_id = $company->id;
        $expected_contacts = array_sum(array_map(
            fn(array $record): int => count($record['payload']['contacts'] ?? []),
            $records[Entity::Clients->value],
        ));
        $expected_placeholder_clients = collect($records[Entity::Clients->value])
            ->filter(fn(array $record): bool => ($record['payload']['contacts'] ?? []) === [])
            ->map(fn(array $record): string => (string) $record['payload']['name'])
            ->sort()
            ->values()
            ->all();
        $contacts = ClientContact::query()
            ->where('company_id', $company_id)
            ->with('client')
            ->get();
        $actual_placeholder_clients = $contacts
            ->filter(fn(ClientContact $contact): bool => $this->isPlaceholderContact($contact->toArray()))
            ->map(fn(ClientContact $contact): string => $contact->client->name)
            ->sort()
            ->values()
            ->all();
        $expected_invoice_lines = array_sum(array_map(
            fn(array $record): int => count($record['payload']['line_items'] ?? []),
            $records[Entity::Invoices->value],
        ));

        $this->assertSame(count($records[Entity::Clients->value]), Client::query()->where('company_id', $company_id)->count());
        $this->assertSame(
            $expected_contacts + count($expected_placeholder_clients),
            $contacts->count(),
            'The API must persist every Harvest contact plus one required placeholder for each contactless client.',
        );
        $this->assertSame(
            $expected_placeholder_clients,
            $actual_placeholder_clients,
            'Blank contacts must only be the placeholders Invoice Ninja requires for contactless clients.',
        );
        $this->assertSame(count($records[Entity::Tasks->value]), Product::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::ExpenseCategories->value]), ExpenseCategory::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::Projects->value]), Project::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::TimeEntries->value]), Task::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::Expenses->value]), Expense::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::Invoices->value]), Invoice::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::Estimates->value]), Quote::query()->where('company_id', $company_id)->count());
        $this->assertSame(count($records[Entity::InvoicePayments->value]), Payment::query()->where('company_id', $company_id)->count());
        $this->assertSame(
            count($records[Entity::Users->value]),
            CompanyUser::query()->where('company_id', $company_id)->where('user_id', '!=', $owner->id)->count(),
        );
        $this->assertSame(
            $expected_invoice_lines,
            Invoice::query()->where('company_id', $company_id)->get()->sum(
                fn(Invoice $invoice): int => count($invoice->line_items),
            ),
        );
    }

    /**
     * @param array<string, array<int, array<string, mixed>>> $records
     * @return array<string, array<string, string>>
     */
    private function assertPersistedApiPayloads(string $api_token, User $owner, array $records): array
    {
        $ids = [];
        $api_records = [];

        foreach (Entity::importOrder() as $entity) {
            $api_records[$entity->value] = $this->apiRecords($entity->endpoint(), $api_token);

            if ($entity === Entity::Users) {
                $api_records[$entity->value] = array_values(array_filter(
                    $api_records[$entity->value],
                    fn(array $record): bool => ($record['id'] ?? null) !== $owner->hashed_id,
                ));
            }

            $this->assertCount(
                count($records[$entity->value]),
                $api_records[$entity->value],
                "Unexpected persisted {$entity->value} API record count.",
            );

            if (! in_array($entity, [
                Entity::Clients,
                Entity::Users,
                Entity::Tasks,
                Entity::ExpenseCategories,
                Entity::Projects,
                Entity::Invoices,
                Entity::Estimates,
            ], true)) {
                continue;
            }

            foreach ($records[$entity->value] as $record) {
                $payload = $this->expectedApiPayload($entity, $this->resolvePayload($record, $ids));
                $identity = $this->identity($entity, $payload);
                $matches = array_values(array_filter(
                    $api_records[$entity->value],
                    fn(array $actual): bool => $this->identity($entity, $actual) === $identity,
                ));

                $this->assertCount(1, $matches, "Expected one persisted {$entity->value} API record for [{$identity}].");
                $this->assertPayloadMatches($payload, $matches[0], "{$entity->value} [{$identity}]");
                $this->assertIsString($matches[0]['id'] ?? null);
                $ids[$entity->value][(string) $record['key']] = $matches[0]['id'];
            }
        }

        foreach (Entity::importOrder() as $entity) {
            $expected_groups = [];
            $actual_groups = [];

            foreach ($records[$entity->value] as $record) {
                $payload = $this->expectedApiPayload($entity, $this->resolvePayload($record, $ids));

                if ($entity === Entity::InvoicePayments) {
                    unset($payload['invoices']);
                }

                $expected_groups[$this->identity($entity, $payload)][] = $payload;
            }

            foreach ($api_records[$entity->value] as $payload) {
                $actual_groups[$this->identity($entity, $payload)][] = $payload;
            }

            ksort($expected_groups);
            ksort($actual_groups);
            $this->assertSame(array_keys($expected_groups), array_keys($actual_groups), "Unexpected persisted {$entity->value} identities.");

            foreach ($expected_groups as $identity => $expected_payloads) {
                $actual_payloads = $actual_groups[$identity];
                $this->assertCount(count($expected_payloads), $actual_payloads, "Unexpected {$entity->value} count for [{$identity}].");

                foreach ($expected_payloads as $expected_payload) {
                    $match = null;

                    foreach ($actual_payloads as $key => $actual_payload) {
                        if ($this->payloadMatches($expected_payload, $actual_payload)) {
                            $match = $key;

                            break;
                        }
                    }

                    $this->assertNotNull(
                        $match,
                        sprintf(
                            "No persisted %s API payload matched [%s].\nExpected subset: %s",
                            $entity->value,
                            $identity,
                            json_encode($expected_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        ),
                    );
                    unset($actual_payloads[$match]);
                }
            }
        }

        return $ids;
    }

    /**
     * Invoice Ninja always persists one blank primary contact for a client whose
     * submitted contacts array is empty.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function expectedApiPayload(Entity $entity, array $payload): array
    {
        if ($entity !== Entity::Clients || ($payload['contacts'] ?? null) !== []) {
            return $payload;
        }

        $payload['contacts'] = [[
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'custom_value1' => '',
            'custom_value2' => '',
            'custom_value3' => '',
            'custom_value4' => '',
            'is_primary' => true,
        ]];

        return $payload;
    }

    /** @param array<string, mixed> $contact */
    private function isPlaceholderContact(array $contact): bool
    {
        foreach ([
            'first_name',
            'last_name',
            'email',
            'phone',
            'custom_value1',
            'custom_value2',
            'custom_value3',
            'custom_value4',
        ] as $field) {
            if (trim((string) ($contact[$field] ?? '')) !== '') {
                return false;
            }
        }

        return filter_var($contact['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<int, array<string, mixed>> */
    private function apiRecords(string $endpoint, string $api_token): array
    {
        $records = [];
        $page = 1;
        $total_pages = 1;

        do {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'X-API-TOKEN' => $api_token,
            ])->getJson("/api/v1/{$endpoint}?per_page=1000&page={$page}");
            $response->assertOk();

            $data = $response->json('data', []);

            if (is_array($data) && ! array_is_list($data)) {
                $data = [$data];
            }

            $this->assertIsArray($data);
            array_push($records, ...$data);
            $total_pages = max(1, (int) $response->json('meta.pagination.total_pages', 1));
            $page++;
        } while ($page <= $total_pages);

        return $records;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, array<string, string>> $ids
     * @return array<string, mixed>
     */
    private function resolvePayload(array $record, array $ids): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $record['payload'];

        /** @var array<string, array{entity: string, key: string, name?: string}> $references */
        $references = $record['references'];

        foreach ($references as $path => $reference) {
            $this->assertArrayHasKey(
                $reference['key'],
                $ids[$reference['entity']] ?? [],
                "Reference [{$reference['entity']}:{$reference['key']}] was not imported before {$record['label']}.",
            );
            data_set($payload, $path, $ids[$reference['entity']][$reference['key']]);
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function identity(Entity $entity, array $payload): string
    {
        $identity = match ($entity) {
            Entity::Clients => $payload['name'] ?? null,
            Entity::Users => $payload['email'] ?? null,
            Entity::Tasks => $payload['product_key'] ?? null,
            Entity::ExpenseCategories => $payload['name'] ?? null,
            Entity::Projects => ($payload['client_id'] ?? '') . '|' . ($payload['name'] ?? ''),
            Entity::Invoices, Entity::Estimates => $payload['number'] ?? null,
            Entity::TimeEntries => json_encode([
                $payload['client_id'] ?? '',
                $payload['project_id'] ?? '',
                $payload['description'] ?? '',
                $payload['time_log'] ?? [],
                $payload['custom_value1'] ?? '',
                $payload['custom_value2'] ?? '',
            ], JSON_THROW_ON_ERROR),
            Entity::Expenses => json_encode([
                $payload['client_id'] ?? '',
                $payload['project_id'] ?? '',
                $payload['category_id'] ?? '',
                $payload['date'] ?? '',
                $payload['amount'] ?? 0,
                $payload['private_notes'] ?? '',
                $payload['custom_value1'] ?? '',
            ], JSON_THROW_ON_ERROR),
            Entity::InvoicePayments => json_encode([
                $payload['client_id'] ?? '',
                $payload['date'] ?? '',
                $payload['amount'] ?? 0,
                $payload['transaction_reference'] ?? '',
            ], JSON_THROW_ON_ERROR),
        };

        if (! is_string($identity) && ! is_int($identity)) {
            throw new RuntimeException("Unable to identify imported {$entity->value} payload.");
        }

        return mb_strtolower(trim((string) $identity));
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    private function assertPayloadMatches(array $expected, array $actual, string $label): void
    {
        $this->assertTrue(
            $this->payloadMatches($expected, $actual),
            sprintf(
                "Persisted API payload does not match %s.\nExpected subset: %s\nActual: %s",
                $label,
                json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ),
        );
    }

    private function payloadMatches(mixed $expected, mixed $actual): bool
    {
        if (is_array($expected)) {
            if (! is_array($actual)) {
                return false;
            }

            if (array_is_list($expected) && count($expected) !== count($actual)) {
                return false;
            }

            foreach ($expected as $key => $value) {
                if (! array_key_exists($key, $actual) || ! $this->payloadMatches($value, $actual[$key])) {
                    return false;
                }
            }

            return true;
        }

        if (is_numeric($expected) && is_numeric($actual)) {
            return abs((float) $expected - (float) $actual) < 0.00001;
        }

        return $expected === $actual;
    }

    /**
     * @param array<int, array<string, mixed>> $payment_records
     * @param array<string, array<string, string>> $ids
     */
    private function assertPaymentApplications(Company $company, array $payment_records, array $ids): void
    {
        $expected = [];
        $remaining = [];

        foreach ($ids[Entity::Invoices->value] as $invoice_key => $invoice_id) {
            $invoice = Invoice::query()
                ->where('company_id', $company->id)
                ->where('id', $this->decodePrimaryKey($invoice_id))
                ->firstOrFail();
            $remaining[$invoice_key] = (float) $invoice->amount;
        }

        foreach ($payment_records as $record) {
            $payload = $this->resolvePayload($record, $ids);
            $invoice_key = $record['references']['invoices.0.invoice_id']['key'] ?? null;

            if (! is_string($invoice_key)) {
                continue;
            }

            foreach ($payload['invoices'] as $invoice) {
                $applied_amount = min(
                    max(0.0, (float) $invoice['amount']),
                    $remaining[$invoice_key] ?? 0.0,
                );

                if ($applied_amount <= 0) {
                    continue;
                }

                $expected[] = [
                    'client_id' => $payload['client_id'],
                    'invoice_id' => $invoice['invoice_id'],
                    'payment_amount' => (float) $payload['amount'],
                    'applied_amount' => $applied_amount,
                    'date' => $payload['date'],
                    'transaction_reference' => $payload['transaction_reference'] ?? '',
                ];
                $remaining[$invoice_key] -= $applied_amount;
            }
        }

        $actual = [];
        $payments = Payment::query()
            ->where('company_id', $company->id)
            ->with('paymentables.paymentable')
            ->get();

        foreach ($payments as $payment) {
            foreach ($payment->paymentables as $paymentable) {
                $this->assertInstanceOf(Invoice::class, $paymentable->paymentable);
                $actual[] = [
                    'client_id' => $payment->client->hashed_id,
                    'invoice_id' => $paymentable->paymentable->hashed_id,
                    'payment_amount' => (float) $payment->amount,
                    'applied_amount' => (float) $paymentable->amount,
                    'date' => $payment->date,
                    'transaction_reference' => $payment->transaction_reference ?: '',
                ];
            }
        }

        $this->assertSame(
            count($expected),
            Paymentable::query()->whereIn('payment_id', $payments->pluck('id'))->count(),
            'Unexpected persisted payment application count.',
        );

        $this->assertSame(
            $this->canonicalPayloads($expected),
            $this->canonicalPayloads($actual),
            'Persisted payments were not applied to the Harvest invoice IDs and amounts.',
        );
    }

    /** @param array<int, array<string, mixed>> $invoice_records */
    private function assertInvoiceTaxRates(Company $company, array $invoice_records): void
    {
        $expected_tax_rates = [];

        foreach ($invoice_records as $record) {
            /** @var array<string, mixed> $payload */
            $payload = $record['payload'];
            $sources = [$payload, ...($payload['line_items'] ?? [])];

            foreach ($sources as $source) {
                if (! is_array($source)) {
                    continue;
                }

                foreach ([1, 2, 3] as $index) {
                    $name = $source["tax_name{$index}"] ?? null;
                    $rate = $source["tax_rate{$index}"] ?? null;

                    if (is_string($name) && $name !== '' && is_numeric($rate) && (float) $rate > 0) {
                        $expected_tax_rates[mb_strtolower($name) . '|' . (float) $rate] = [
                            'name' => $name,
                            'rate' => (float) $rate,
                        ];
                    }
                }
            }
        }

        $actual_tax_rates = TaxRate::query()
            ->where('company_id', $company->id)
            ->get(['name', 'rate'])
            ->map(fn(TaxRate $tax_rate): array => [
                'name' => $tax_rate->name,
                'rate' => (float) $tax_rate->rate,
            ])
            ->all();

        $this->assertSame(
            $this->canonicalPayloads(array_values($expected_tax_rates)),
            $this->canonicalPayloads($actual_tax_rates),
            'Created tax rates are inconsistent with the imported invoice taxes.',
        );
    }

    /** @param array<int, array<string, mixed>> $estimate_records */
    private function assertDocumentActions(Company $company, array $estimate_records): void
    {
        $approved_numbers = [];

        foreach ($estimate_records as $record) {
            if (in_array('approve', $record['actions'] ?? [], true)) {
                $approved_numbers[] = (string) ($record['payload']['number'] ?? '');
            }
        }

        sort($approved_numbers);
        $actual_approved_numbers = Quote::query()
            ->where('company_id', $company->id)
            ->where('status_id', Quote::STATUS_APPROVED)
            ->pluck('number')
            ->map(fn(string $number): string => $number)
            ->all();
        sort($actual_approved_numbers);

        $this->assertSame($approved_numbers, $actual_approved_numbers, 'Accepted Harvest estimates were not approved consistently.');
    }

    /**
     * @param array<int, array<string, mixed>> $payloads
     * @return array<int, string>
     */
    private function canonicalPayloads(array $payloads): array
    {
        $canonical = array_map(
            fn(array $payload): string => json_encode(
                $this->sortPayload($payload),
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            ),
            $payloads,
        );
        sort($canonical);

        return $canonical;
    }

    /**
     * @param array<mixed> $payload
     * @return array<mixed>
     */
    private function sortPayload(array $payload): array
    {
        foreach ($payload as &$value) {
            if (is_array($value)) {
                $value = $this->sortPayload($value);
            }
        }
        unset($value);

        if (! array_is_list($payload)) {
            ksort($payload);
        }

        return $payload;
    }
}

class HarvestKernelHttpFactory extends HttpFactory
{
    public function __construct(?Dispatcher $dispatcher, private readonly Closure $handler)
    {
        parent::__construct($dispatcher);
    }

    protected function newPendingRequest(): PendingRequest
    {
        return parent::newPendingRequest()->setHandler($this->handler);
    }
}
