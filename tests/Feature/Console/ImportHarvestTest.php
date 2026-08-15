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

use App\Import\Harvest\AddressParser;
use App\Import\Harvest\CsvImporter;
use App\Import\Harvest\Entity;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ImportHarvestTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/harvest-' . Str::uuid());
        File::makeDirectory($this->directory, 0o755, true);

        $this->app->instance('countries', collect([
            (new Country())->forceFill([
                'id' => 124,
                'iso_3166_2' => 'CA',
                'currency_code' => 'CAD',
            ]),
        ]));
        $this->app->instance('currencies', collect([
            (new Currency())->forceFill([
                'id' => 9,
                'code' => 'CAD',
            ]),
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    private function harvestApiUrl(string $path = ''): string
    {
        $path = $path === '' || str_starts_with($path, '/') ? $path : '/' . $path;

        return rtrim((string) config('app.url'), '/') . '/api/v1' . $path;
    }

    public function test_it_merges_harvest_clients_and_contacts_into_api_payloads(): void
    {
        $this->writeHarvestExports();
        File::put($this->directory . '/time.csv', "Date,Hours\n2026-01-01,2\n");

        $result = app(CsvImporter::class)->build($this->directory, [Entity::Clients]);
        $records = $result['records'][Entity::Clients->value];

        $this->assertCount(2, $records);
        $this->assertSame(['clients.csv'], $result['files']['clients']);
        $this->assertSame(['contacts.csv'], $result['files']['contacts']);
        $this->assertSame(['time.csv'], $result['unsupported_files']);
        $this->assertCount(1, $result['unmatched_contacts']);

        $record = collect($records)->first(
            fn(array $record): bool => $record['payload']['name'] === 'Acme & Co',
        );
        $payload = $record['payload'];

        $this->assertSame('1 Main Street', $payload['address1']);
        $this->assertSame('Suite 2, Sydney NSW 2000', $payload['address2']);
        $this->assertCount(2, $payload['contacts']);
        $this->assertSame([
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
            'phone' => '02 5555 1111',
            'custom_value1' => 'Director',
            'custom_value2' => '0400 000 001',
            'custom_value3' => '02 5555 2222',
            'custom_value4' => 'Recipient',
            'send_email' => true,
            'cc_only' => false,
        ], $payload['contacts'][0]);
        $this->assertSame('0400 000 002', $payload['contacts'][1]['phone']);
        $this->assertFalse($payload['contacts'][1]['send_email']);
        $this->assertTrue($payload['contacts'][1]['cc_only']);
    }

    public function test_it_delimits_the_harvest_address_variations(): void
    {
        $parser = app(AddressParser::class);
        $addresses = [
            "P.O. Box 777 Exampleville,\nExample City, CA 90210\nTel: 415 555-0100" => [
                'address1' => 'P.O. Box 777 Exampleville',
                'city' => 'Example City',
                'state' => 'CA',
                'postal_code' => '90210',
                'country_code' => 'US',
                'phone' => '415 555-0100',
            ],
            "100 Example Road\nSampleton, ON K1A 0B1\nCanada" => [
                'address1' => '100 Example Road',
                'city' => 'Sampleton',
                'state' => 'ON',
                'postal_code' => 'K1A 0B1',
                'country_code' => 'CA',
            ],
            "200 Sample Avenue\nExampleville ON A1A 1A1" => [
                'address1' => '200 Sample Avenue',
                'city' => 'Exampleville',
                'state' => 'ON',
                'postal_code' => 'A1A 1A1',
                'country_code' => 'CA',
            ],
            "RR 9 Placeholder Ontario,\nCanada B2B 2B2" => [
                'address1' => 'RR 9',
                'city' => 'Placeholder',
                'state' => 'ON',
                'postal_code' => 'B2B 2B2',
                'country_code' => 'CA',
            ],
            "300 Fictional Drive\nDemo City ON  C3C 3C3\nCanada" => [
                'address1' => '300 Fictional Drive',
                'city' => 'Demo City',
                'state' => 'ON',
                'postal_code' => 'C3C 3C3',
                'country_code' => 'CA',
            ],
        ];

        foreach ($addresses as $address => $expected) {
            $this->assertSame($expected, $parser->parse($address), $address);
        }
    }

    public function test_it_resolves_a_literal_shell_escaped_directory_path(): void
    {
        $directory = $this->directory . '/Example Harvest Export';
        File::makeDirectory($directory, 0o755, true);
        $this->writeHarvestExports($directory);

        $escaped_directory = str_replace(' ', '\ ', $directory);
        $result = app(CsvImporter::class)->build($escaped_directory, [Entity::Clients]);

        $this->assertCount(2, $result['records'][Entity::Clients->value]);
    }

    public function test_it_scans_subdirectories_recursively_for_csv_files(): void
    {
        $directory = $this->directory . '/nested/harvest/exports';
        File::makeDirectory($directory, 0o755, true);
        $this->writeHarvestExports($directory);

        $result = app(CsvImporter::class)->build($this->directory, [Entity::Clients]);

        $this->assertCount(2, $result['records'][Entity::Clients->value]);
        $this->assertSame(['clients.csv'], $result['files']['clients']);
        $this->assertSame(['contacts.csv'], $result['files']['contacts']);
    }

    public function test_it_resolves_currency_from_each_client_country_only_when_enabled(): void
    {
        File::put($this->directory . '/clients.csv', <<<'CSV'
            Client Name,Address
            Canadian Client,"100 Example Road
            Sampleton, ON K1A 0B1
            Canada"
            Unknown Country Client,10 Example Road
            CSV);

        $result_without_currency = app(CsvImporter::class)->build($this->directory, [Entity::Clients]);
        $result = app(CsvImporter::class)->build($this->directory, [Entity::Clients], true);
        $payload_without_currency = $result_without_currency['records']['clients'][0]['payload'];
        $payload = $result['records']['clients'][0]['payload'];

        $this->assertArrayNotHasKey('settings', $payload_without_currency);
        $this->assertSame('CA', $payload['country_code']);
        $this->assertSame('9', $payload['settings']['currency_id']);
        $this->assertArrayNotHasKey('settings', $result['records']['clients'][1]['payload']);
    }

    public function test_entity_option_accepts_a_comma_separated_list_and_empty_means_all(): void
    {
        $this->assertSame(Entity::importOrder(), Entity::fromOption(null));
        $this->assertSame(Entity::importOrder(), Entity::fromOption(''));
        $this->assertSame(
            [Entity::Clients, Entity::Tasks],
            Entity::fromOption(' tasks, client, tasks '),
        );
    }

    public function test_entity_option_documents_every_supported_value(): void
    {
        $description = Artisan::all()['ninja:import-harvest']
            ->getDefinition()
            ->getOption('entities')
            ->getDescription();

        foreach (Entity::importOrder() as $entity) {
            $this->assertStringContainsString($entity->value, $description);
        }

        $this->assertStringContainsString('clients (includes contacts)', $description);
        $this->assertStringContainsString('Omit to import all', $description);
    }

    public function test_entity_option_rejects_unknown_entities_without_enforcing_dependencies(): void
    {
        try {
            Entity::fromOption('clients,wombats');
            $this->fail('Unknown entities should be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unsupported Harvest entity [wombats]', $exception->getMessage());
        }

        $this->assertSame([Entity::Projects], Entity::fromOption('projects'));
        $this->assertSame([Entity::InvoicePayments], Entity::fromOption('invoice_payments'));
    }

    public function test_it_transforms_all_supported_harvest_entities(): void
    {
        $this->writeAllHarvestExports();

        $result = app(CsvImporter::class)->build($this->directory);

        $this->assertSame([
            'clients' => 2,
            'users' => 1,
            'tasks' => 1,
            'expense_categories' => 1,
            'projects' => 1,
            'time_entries' => 1,
            'expenses' => 1,
            'invoices' => 1,
            'estimates' => 1,
            'invoice_payments' => 1,
        ], array_map('count', $result['records']));

        $project = $result['records']['projects'][0];
        $this->assertSame('Website Redesign', $project['payload']['name']);
        $this->assertSame(['entity' => 'clients', 'key' => 'acme & co', 'name' => 'Acme & Co'], $project['references']['client_id']);

        $time_entry = $result['records']['time_entries'][0];
        $this->assertSame('Consulting', $time_entry['payload']['custom_value1']);
        $this->assertSame([
            'entity' => 'projects',
            'key' => 'acme & co|website redesign',
            'name' => 'Acme & Co: Website Redesign',
        ], $time_entry['references']['project_id']);
        $this->assertSame(['entity' => 'users', 'key' => 'ada@example.com'], $time_entry['references']['assigned_user_id']);

        $invoice = $result['records']['invoices'][0];
        $this->assertSame('INV-100', $invoice['payload']['number']);
        $this->assertSame(2.0, $invoice['payload']['line_items'][0]['quantity']);
        $this->assertSame(100.0, $invoice['payload']['line_items'][0]['cost']);
        $this->assertFalse($invoice['payload']['uses_inclusive_taxes']);
        $this->assertArrayNotHasKey('tax_rate1', $invoice['payload']);
        $this->assertArrayNotHasKey('tax_rate2', $invoice['payload']);
        $this->assertSame('TAX (13%)', $invoice['payload']['line_items'][0]['tax_name1']);
        $this->assertSame(13.0, $invoice['payload']['line_items'][0]['tax_rate1']);
        $this->assertSame('TAX2 (5%)', $invoice['payload']['line_items'][0]['tax_name2']);
        $this->assertSame(5.0, $invoice['payload']['line_items'][0]['tax_rate2']);

        $payment = $result['records']['invoice_payments'][0];
        $this->assertSame([
            'entity' => 'invoices',
            'key' => 'inv-100',
            'name' => 'INV-100',
        ], $payment['references']['invoices.0.invoice_id']);
        $quote = $result['records']['estimates'][0];
        $this->assertSame('EST-100', $quote['payload']['number']);
        $this->assertSame('TAX (13%)', $quote['payload']['tax_name1']);
        $this->assertSame(13.0, $quote['payload']['tax_rate1']);
        $this->assertArrayNotHasKey('tax_rate1', $quote['payload']['line_items'][0]);
        $this->assertSame(
            "Phase two\n\nHarvest quote status:\nAccepted Date: 2026-03-04\nDeclined Date: Not set",
            $quote['payload']['private_notes'],
        );
        $this->assertSame(['approve'], $quote['actions']);
    }

    public function test_it_preserves_projects_with_duplicate_harvest_codes_using_unique_numbers(): void
    {
        File::put($this->directory . '/projects.csv', <<<'CSV'
            Client,Project,Project Code
            Acme & Co,First Project,CODE
            Solo Client,Second Project,CODE
            Third Client,Third Project,CODE-2
            CSV);

        $records = app(CsvImporter::class)
            ->build($this->directory, [Entity::Projects])['records'][Entity::Projects->value];

        $this->assertSame(
            ['CODE', 'CODE-2', 'CODE-2-2'],
            array_column(array_column($records, 'payload'), 'number'),
        );
    }

    public function test_it_preserves_documents_and_relations_when_harvest_ids_repeat(): void
    {
        File::put($this->directory . '/invoices.csv', <<<'CSV'
            Issue Date,ID,Client,Subtotal,Tax,Tax2
            2026-01-01,INV-1,Client One,100,0,0
            2026-02-01,INV-1,Client Two,200,0,0
            CSV);
        File::put($this->directory . '/invoice_line_items.csv', <<<'CSV'
            Invoice ID,Client,Item Description,Item Quantity,Item Unit Price,Item Amount,Issue Date
            INV-1,Client One,First invoice line,1,100,100,2026-01-01
            INV-1,Client Two,Second invoice line,1,200,200,2026-02-01
            CSV);
        File::put($this->directory . '/payments.csv', <<<'CSV'
            Payment Date,Invoice ID,Invoice Issue Date,Client,Payment Amount
            2026-01-10,INV-1,2026-01-01,Client One,100
            2026-02-10,INV-1,2026-02-01,Client Two,200
            CSV);
        File::put($this->directory . '/estimates.csv', <<<'CSV'
            Issue Date,ID,Client,Subject,Estimate Amount,Subtotal,Accepted Date,Declined Date
            2026-03-01,EST-1,Client One,First estimate,300,300,,
            2026-04-01,EST-1,Client Two,Second estimate,400,400,,
            CSV);

        $result = app(CsvImporter::class)->build($this->directory, [
            Entity::Invoices,
            Entity::Estimates,
            Entity::InvoicePayments,
        ]);

        $invoices = $result['records'][Entity::Invoices->value];
        $this->assertSame(['INV-1', 'INV-1-2'], array_column(array_column($invoices, 'payload'), 'number'));
        $this->assertSame('First invoice line', $invoices[0]['payload']['line_items'][0]['notes']);
        $this->assertSame('Second invoice line', $invoices[1]['payload']['line_items'][0]['notes']);

        $payments = $result['records'][Entity::InvoicePayments->value];
        $this->assertSame('inv-1', $payments[0]['references']['invoices.0.invoice_id']['key']);
        $this->assertSame('inv-1-2', $payments[1]['references']['invoices.0.invoice_id']['key']);
        $this->assertSame('INV-1-2', $payments[1]['references']['invoices.0.invoice_id']['name']);

        $estimates = $result['records'][Entity::Estimates->value];
        $this->assertSame(['EST-1', 'EST-1-2'], array_column(array_column($estimates, 'payload'), 'number'));
    }

    public function test_dry_run_resolves_client_ids_without_creating_records(): void
    {
        $this->writeAllHarvestExports();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => [[
                    'number' => 'INV-100',
                    'hashed_id' => 'existing-invoice-100',
                ]]], 200);
            }

            if (str_contains($request->url(), '/projects')) {
                return Http::response(['data' => [[
                    'name' => 'Website Redesign',
                    'client_id' => 'existing-client-acme',
                    'hashed_id' => 'existing-project-website',
                ]]], 200);
            }

            return Http::response(['data' => [
                ['name' => 'Acme & Co', 'hashed_id' => 'existing-client-acme'],
                ['name' => 'Solo Client', 'hashed_id' => 'existing-client-solo'],
            ]], 200);
        });

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--resolve-currency' => true,
            '--dry-run' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit_code);
        $this->assertStringContainsString('Prepared 11 record(s) across 10 Harvest entity type(s)', $output);
        $this->assertStringContainsString('"currency_id": "9"', $output);
        $this->assertStringContainsString('"client_id": "existing-client-acme"', $output);
        $this->assertStringContainsString('"invoice_id": "existing-invoice-100"', $output);
        $this->assertStringContainsString('"project_id": "existing-project-website"', $output);
        $this->assertStringContainsString('Dry run complete; no records were created.', $output);

        Http::assertSentCount(4);
        Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/clients'));
        Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/invoices'));
        Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/projects'));
        Http::assertSent(fn(Request $request): bool => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/expense_categories'));
        Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST');
    }

    public function test_it_only_imports_the_requested_entities(): void
    {
        $this->writeAllHarvestExports();
        Http::fake(fn() => Http::response(['data' => ['id' => Str::uuid()->toString()]], 201));

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients,tasks',
        ])->assertSuccessful();

        Http::assertSentCount(4);
        Http::assertSent(fn(Request $request): bool => $request->url() === $this->harvestApiUrl() . '/products');
        Http::assertNotSent(fn(Request $request): bool => $request->url() === $this->harvestApiUrl() . '/projects');
    }

    public function test_it_sends_selected_entities_without_enforcing_dependencies(): void
    {
        $this->writeAllHarvestExports();

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => [[
                    'name' => '  ACME & CO ',
                    'hashed_id' => 'existing-client-acme',
                ]]], 200);
            }

            $this->assertSame($this->harvestApiUrl() . '/projects', $request->url());
            $this->assertSame('existing-client-acme', $request['client_id']);

            return Http::response(['data' => ['id' => 'project-website']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'projects',
        ])->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_it_reports_unmatched_client_names_after_the_import(): void
    {
        $this->writeAllHarvestExports();

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => []], 200);
            }

            $this->assertSame($this->harvestApiUrl() . '/projects', $request->url());
            $this->assertFalse(isset($request['client_id']));

            return Http::response(['data' => ['id' => 'project-website']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'projects',
        ])
            ->expectsOutputToContain('1 client name(s) could not be matched to an Invoice Ninja client ID:')
            ->expectsOutputToContain('Acme & Co')
            ->assertSuccessful();

        Http::assertSentCount(2);
    }

    public function test_it_resolves_api_ids_for_dependent_entities(): void
    {
        $this->writeAllHarvestExports();
        $client_map_requests = 0;
        $project_created = false;
        $request_urls = [];

        Http::fake(function (Request $request) use (&$client_map_requests, &$project_created, &$request_urls) {
            $request_urls[] = $request->url();

            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                $client_map_requests++;

                return Http::response(['data' => $client_map_requests === 1 ? [] : [
                    ['name' => 'Acme & Co', 'hashed_id' => 'client-acme'],
                    ['name' => 'Solo Client', 'hashed_id' => 'client-solo'],
                ]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/projects')) {
                return Http::response(['data' => $project_created ? [[
                    'name' => 'Website Redesign',
                    'client_id' => 'client-acme',
                    'hashed_id' => 'project-website',
                ]] : []], 200);
            }

            if (str_ends_with($request->url(), '/clients')) {
                $id = $request['name'] === 'Acme & Co' ? 'client-acme' : 'client-solo';

                return Http::response(['data' => [
                    'name' => $request['name'],
                    'hashed_id' => $id,
                ]], 201);
            }

            if (str_ends_with($request->url(), '/projects')) {
                $this->assertSame('client-acme', $request['client_id']);
                $project_created = true;

                return Http::response(['data' => [
                    'id' => 'project-website',
                    'name' => 'Website Redesign',
                    'client_id' => 'client-acme',
                ]], 201);
            }

            $this->assertSame($this->harvestApiUrl() . '/tasks', $request->url());
            $this->assertSame('client-acme', $request['client_id']);
            $this->assertSame('project-website', $request['project_id']);

            return Http::response(['data' => ['id' => 'task-1']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients,projects,time_entries',
        ])->assertSuccessful();

        Http::assertSentCount(8);
        $this->assertSame([
            $this->harvestApiUrl() . '/clients?per_page=1000&page=1',
            $this->harvestApiUrl() . '/clients',
            $this->harvestApiUrl() . '/clients',
            $this->harvestApiUrl() . '/clients?per_page=1000&page=1',
            $this->harvestApiUrl() . '/projects?per_page=1000&page=1',
            $this->harvestApiUrl() . '/projects',
            $this->harvestApiUrl() . '/projects?per_page=1000&page=1',
            $this->harvestApiUrl() . '/tasks',
        ], $request_urls);
    }

    public function test_it_reuses_existing_expense_categories_and_resolves_expense_category_ids(): void
    {
        $this->writeExpenseCategoryExports();

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => []], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/expense_categories')) {
                return Http::response(['data' => [[
                    'name' => '  TRAVEL ',
                    'hashed_id' => 'category-travel',
                ]]], 200);
            }

            $this->assertSame($this->harvestApiUrl() . '/expenses', $request->url());
            $this->assertSame('category-travel', $request['category_id']);

            return Http::response(['data' => ['hashed_id' => 'expense-1']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'expense_categories,expenses',
        ])
            ->expectsOutputToContain('Using existing expense category: Travel')
            ->assertSuccessful();

        Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/expense_categories'));
        Http::assertSentCount(3);
    }

    public function test_it_recovers_a_duplicate_expense_category_race_without_aborting(): void
    {
        $this->writeExpenseCategoryExports();
        $category_map_requests = 0;

        Http::fake(function (Request $request) use (&$category_map_requests) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => []], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/expense_categories')) {
                $category_map_requests++;

                return Http::response(['data' => $category_map_requests === 1 ? [] : [[
                    'name' => 'Travel',
                    'hashed_id' => 'category-travel',
                ]]], 200);
            }

            if (str_ends_with($request->url(), '/expense_categories')) {
                return Http::response([
                    'message' => 'The given data was invalid.',
                    'errors' => ['name' => ['The name has already been taken.']],
                ], 422);
            }

            $this->assertSame($this->harvestApiUrl() . '/expenses', $request->url());
            $this->assertSame('category-travel', $request['category_id']);

            return Http::response(['data' => ['hashed_id' => 'expense-1']], 201);
        });

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'expense_categories,expenses',
            '--abort-on-failure' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit_code);
        $this->assertSame(2, $category_map_requests);
        $this->assertStringContainsString('Using existing expense category: Travel', $output);
        $this->assertStringNotContainsString('Failure report', $output);
        $this->assertStringNotContainsString('Import aborted', $output);
        Http::assertSentCount(5);
    }

    public function test_importing_only_expenses_uses_the_existing_expense_category_map(): void
    {
        $this->writeExpenseCategoryExports();

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => []], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/expense_categories')) {
                return Http::response(['data' => [[
                    'name' => 'Travel',
                    'hashed_id' => 'category-travel',
                ]]], 200);
            }

            $this->assertSame($this->harvestApiUrl() . '/expenses', $request->url());
            $this->assertSame('category-travel', $request['category_id']);

            return Http::response(['data' => ['hashed_id' => 'expense-1']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'expenses',
        ])->assertSuccessful();

        Http::assertSentCount(3);
    }

    public function test_it_creates_projects_from_exact_harvest_time_entry_headers_before_tasks(): void
    {
        File::put($this->directory . '/time_entries.csv', <<<'CSV'
            Date,Client,Project,Project Code,Task,Notes,Hours,Hours Rounded,Billable?,Invoiced?,First Name,Last Name,Employee Id,Roles,Teams,Employee?,Billable Rate,Billable Amount,Cost Rate,Cost Amount,Currency,External Reference URL
            2026-05-01,Acme & Co,Migration,MIG,Development,Built import pipeline,1.25,1.5,Yes,No,Ada,Lovelace,EMP-1,Developer,Platform,Yes,150,187.50,75,93.75,CAD,https://example.test/time/1
            CSV);

        $project_created = false;
        $request_urls = [];

        Http::fake(function (Request $request) use (&$project_created, &$request_urls) {
            $request_urls[] = $request->url();

            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Acme & Co',
                    'hashed_id' => 'client-acme',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/projects')) {
                return Http::response(['data' => $project_created ? [[
                    'name' => 'Migration',
                    'client_id' => 'client-acme',
                    'hashed_id' => 'project-migration',
                ]] : []], 200);
            }

            if (str_ends_with($request->url(), '/projects')) {
                $this->assertSame('client-acme', $request['client_id']);
                $this->assertSame('Migration', $request['name']);
                $this->assertSame('MIG', $request['number']);
                $this->assertSame(150.0, $request['task_rate']);
                $project_created = true;

                return Http::response(['data' => ['id' => 'project-migration']], 201);
            }

            $this->assertSame($this->harvestApiUrl() . '/tasks', $request->url());
            $this->assertSame('client-acme', $request['client_id']);
            $this->assertSame('project-migration', $request['project_id']);
            $this->assertSame("Development\nBuilt import pipeline", $request['description']);
            $this->assertSame(150.0, $request['rate']);
            $this->assertSame(4500, $request['time_log'][0][1] - $request['time_log'][0][0]);
            $this->assertTrue($request['time_log'][0][3]);
            $this->assertFalse(isset($request['assigned_user_id']));

            return Http::response(['data' => ['id' => 'task-migration']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'time_entries',
        ])->assertSuccessful();

        Http::assertSentCount(6);
        $this->assertSame([
            $this->harvestApiUrl() . '/clients?per_page=1000&page=1',
            $this->harvestApiUrl() . '/clients?per_page=1000&page=1',
            $this->harvestApiUrl() . '/projects?per_page=1000&page=1',
            $this->harvestApiUrl() . '/projects',
            $this->harvestApiUrl() . '/projects?per_page=1000&page=1',
            $this->harvestApiUrl() . '/tasks',
        ], $request_urls);
    }

    public function test_it_trims_unicode_boundary_whitespace_from_harvest_values(): void
    {
        $non_breaking_space = "\u{00A0}";
        File::put(
            $this->directory . '/time_entries.csv',
            "Date,Client,Project,Task,Notes,Hours,Billable?\n"
                . "2026-05-01,Acme & Co,Migration,Development,{$non_breaking_space}Built import pipeline{$non_breaking_space},1.25,Yes\n",
        );

        $result = app(CsvImporter::class)->build($this->directory, [Entity::TimeEntries]);
        $payload = $result['records'][Entity::TimeEntries->value][0]['payload'];

        $this->assertSame("Development\nBuilt import pipeline", $payload['description']);
        $this->assertSame('Built import pipeline', $payload['time_log'][0][2]);
    }

    public function test_it_reuses_an_existing_project_for_harvest_time_entries(): void
    {
        File::put($this->directory . '/time_entries.csv', <<<'CSV'
            Date,Client,Project,Project Code,Task,Notes,Hours,Hours Rounded,Billable?,Invoiced?,First Name,Last Name,Employee Id,Roles,Teams,Employee?,Billable Rate,Billable Amount,Cost Rate,Cost Amount,Currency,External Reference URL
            2026-05-01,Acme & Co,Migration,MIG,Development,Built import pipeline,1.25,1.5,Yes,No,Ada,Lovelace,EMP-1,Developer,Platform,Yes,150,187.50,75,93.75,CAD,https://example.test/time/1
            CSV);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Acme & Co',
                    'hashed_id' => 'client-acme',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/projects')) {
                return Http::response(['data' => [[
                    'name' => 'Migration',
                    'client_id' => 'client-acme',
                    'hashed_id' => 'existing-project-migration',
                ]]], 200);
            }

            $this->assertSame($this->harvestApiUrl() . '/tasks', $request->url());
            $this->assertSame('existing-project-migration', $request['project_id']);

            return Http::response(['data' => ['id' => 'task-migration']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'time_entries',
        ])
            ->expectsOutputToContain('Using existing project: Migration')
            ->assertSuccessful();

        Http::assertSentCount(5);
        Http::assertNotSent(fn(Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/projects'));
    }

    public function test_it_resolves_invoice_and_payment_api_ids(): void
    {
        $this->writeAllHarvestExports();
        $request_urls = [];

        Http::fake(function (Request $request) use (&$request_urls) {
            $request_urls[] = $request->url();

            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => []], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => []], 200);
            }

            if (str_ends_with($request->url(), '/clients')) {
                $id = $request['name'] === 'Acme & Co' ? 'client-acme' : 'client-solo';

                return Http::response(['data' => [
                    'name' => $request['name'],
                    'hashed_id' => $id,
                ]], 201);
            }

            if ($request->url() === $this->harvestApiUrl() . '/invoices?mark_sent=true') {
                $this->assertSame('client-acme', $request['client_id']);

                return Http::response(['data' => [
                    'id' => 'invoice-100',
                    'number' => 'INV-100',
                    'amount' => 236,
                    'balance' => 0,
                    'status_id' => 1,
                ]], 201);
            }

            if (str_ends_with($request->url(), '/tax_rates')) {
                $this->assertContains($request['name'], ['TAX (13%)', 'TAX2 (5%)']);
                $this->assertContains((float) $request['rate'], [13.0, 5.0]);

                return $request['name'] === 'TAX2 (5%)'
                    ? Http::response(['message' => 'The name has already been taken.'], 422)
                    : Http::response(['data' => ['id' => 'tax-rate-1']], 201);
            }

            $this->assertSame($this->harvestApiUrl() . '/payments?email_receipt=false', $request->url());
            $this->assertSame('client-acme', $request['client_id']);
            $this->assertSame('invoice-100', $request['invoices'][0]['invoice_id']);

            return Http::response(['data' => ['id' => 'payment-1']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients,invoices,invoice_payments',
        ])->assertSuccessful();

        Http::assertSentCount(8);
        $this->assertSame([
            $this->harvestApiUrl() . '/clients?per_page=1000&page=1',
            $this->harvestApiUrl() . '/invoices?per_page=1000&page=1',
            $this->harvestApiUrl() . '/clients',
            $this->harvestApiUrl() . '/clients',
            $this->harvestApiUrl() . '/invoices?mark_sent=true',
            $this->harvestApiUrl() . '/tax_rates',
            $this->harvestApiUrl() . '/tax_rates',
            $this->harvestApiUrl() . '/payments?email_receipt=false',
        ], $request_urls);
    }

    public function test_it_rounds_created_tax_rates_to_two_decimal_places(): void
    {
        File::put($this->directory . '/invoices.csv', <<<'CSV'
            Issue Date,ID,Client,Invoice Amount,Subtotal,Tax,Tax2
            2026-02-28,INV-TAX-ROUND,Example Client,113.336,100,13.336,0
            CSV);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Example Client',
                    'hashed_id' => 'client-example',
                ]]], 200);
            }

            if ($request->url() === $this->harvestApiUrl() . '/invoices?mark_sent=true') {
                $this->assertSame('TAX (13.34%)', $request['tax_name1']);
                $this->assertSame(13.336, (float) $request['tax_rate1']);

                return Http::response(['data' => ['hashed_id' => 'invoice-tax-round']], 201);
            }

            $this->assertStringEndsWith('/tax_rates', $request->url());
            $this->assertSame('TAX (13.34%)', $request['name']);
            $this->assertSame(13.34, (float) $request['rate']);

            return Http::response(['data' => ['hashed_id' => 'tax-rate-round']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'invoices',
        ])->assertSuccessful();

        Http::assertSentCount(3);
    }

    public function test_it_maps_exact_harvest_payment_headers_to_an_existing_invoice(): void
    {
        File::put($this->directory . '/payments.csv', <<<'CSV'
            Payment Date,Invoice ID,Invoice Issue Date,Client,Invoice Amount,Payment Amount,Tax,Tax2,Currency,Currency Symbol,Document Type
            2026-03-10,INV-900,2026-02-28,Acme & Co,236,200,26,10,CAD,$,Invoice
            CSV);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Acme & Co',
                    'hashed_id' => 'client-acme',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => [[
                    'number' => 'INV-900',
                    'hashed_id' => 'invoice-900',
                    'amount' => 236,
                    'balance' => 236,
                    'status_id' => 2,
                ]]], 200);
            }

            $this->assertSame('POST', $request->method());
            $this->assertSame($this->harvestApiUrl() . '/payments?email_receipt=false', $request->url());
            $this->assertSame('client-acme', $request['client_id']);
            $this->assertSame(200.0, $request['amount']);
            $this->assertSame('2026-03-10', $request['date']);
            $this->assertSame([[
                'amount' => 200.0,
                'invoice_id' => 'invoice-900',
            ]], $request['invoices']);
            $this->assertFalse(isset($request['tax']));
            $this->assertFalse(isset($request['tax2']));
            $this->assertFalse(isset($request['currency']));

            return Http::response(['data' => ['id' => 'payment-900']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'invoice_payments',
        ])->assertSuccessful();

        Http::assertSentCount(3);
    }

    public function test_it_caps_an_overpayment_application_at_the_invoice_balance(): void
    {
        File::put($this->directory . '/payments.csv', <<<'CSV'
            Payment Date,Invoice ID,Invoice Issue Date,Client,Invoice Amount,Payment Amount,Tax,Tax2,Currency,Currency Symbol,Document Type
            2026-03-10,INV-900,2026-02-28,Acme & Co,200,250,0,0,CAD,$,Invoice
            CSV);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Acme & Co',
                    'hashed_id' => 'client-acme',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => [[
                    'number' => 'INV-900',
                    'hashed_id' => 'invoice-900',
                    'amount' => 200,
                    'balance' => 200,
                    'status_id' => 2,
                ]]], 200);
            }

            $this->assertSame(250.0, $request['amount']);
            $this->assertSame(200.0, $request['invoices'][0]['amount']);

            return Http::response(['data' => ['id' => 'payment-900']], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'invoice_payments',
        ])->assertSuccessful();
    }

    public function test_it_normalizes_payment_application_float_arithmetic(): void
    {
        File::put($this->directory . '/payments.csv', <<<'CSV'
            Payment Date,Invoice ID,Invoice Issue Date,Client,Invoice Amount,Payment Amount,Tax,Tax2,Currency,Currency Symbol,Document Type
            2026-03-10,INV-900,2026-02-28,Acme & Co,226.2,113.1,0,0,CAD,$,Invoice
            2026-03-11,INV-900,2026-02-28,Acme & Co,226.2,113.1,0,0,CAD,$,Invoice
            CSV);
        $applications = [];

        Http::fake(function (Request $request) use (&$applications) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Acme & Co',
                    'hashed_id' => 'client-acme',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => [[
                    'number' => 'INV-900',
                    'hashed_id' => 'invoice-900',
                    'amount' => 226.2,
                    'balance' => 226.2,
                    'status_id' => 2,
                ]]], 200);
            }

            $applications[] = $request['invoices'][0]['amount'];

            return Http::response(['data' => ['id' => 'payment-' . count($applications)]], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'invoice_payments',
        ])->assertSuccessful();

        $this->assertSame([113.1, 113.1], $applications);
    }

    public function test_it_assigns_distinct_idempotency_keys_to_identical_harvest_payments(): void
    {
        File::put($this->directory . '/payments.csv', <<<'CSV'
            Payment Date,Invoice ID,Invoice Issue Date,Client,Invoice Amount,Payment Amount,Tax,Tax2,Currency,Currency Symbol,Document Type
            2026-03-10,INV-900,2026-02-28,Acme & Co,500,100,0,0,CAD,$,Invoice
            2026-03-10,INV-900,2026-02-28,Acme & Co,500,100,0,0,CAD,$,Invoice
            CSV);
        $idempotency_keys = [];

        Http::fake(function (Request $request) use (&$idempotency_keys) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Acme & Co',
                    'hashed_id' => 'client-acme',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => [[
                    'number' => 'INV-900',
                    'hashed_id' => 'invoice-900',
                    'amount' => 500,
                    'balance' => 500,
                    'status_id' => 2,
                ]]], 200);
            }

            $idempotency_keys[] = $request['idempotency_key'];

            return Http::response(['data' => ['id' => 'payment-' . count($idempotency_keys)]], 201);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'invoice_payments',
        ])->assertSuccessful();

        $this->assertCount(2, array_unique($idempotency_keys));
        $this->assertStringStartsWith('harvest-', $idempotency_keys[0]);
        $this->assertSame(64, strlen($idempotency_keys[0]));
    }

    public function test_it_does_not_apply_header_and_line_discounts_twice(): void
    {
        File::put($this->directory . '/invoices.csv', <<<'CSV'
            Issue Date,ID,Client,Invoice Amount,Subtotal,Discount,Tax,Tax2
            2026-02-28,INV-DISCOUNT,Acme & Co,80,100,20,0,0
            CSV);
        File::put($this->directory . '/invoice_lines.csv', <<<'CSV'
            Invoice ID,Client,Item Type,Item Description,Item Quantity,Item Unit Price,Item Amount,Item Discount,Item Tax,Item Tax2,Issue Date
            INV-DISCOUNT,Acme & Co,Service,Discounted work,1,100,100,20,0,0,2026-02-28
            CSV);

        $invoice = app(CsvImporter::class)
            ->build($this->directory, [Entity::Invoices])['records'][Entity::Invoices->value][0]['payload'];

        $this->assertSame(0.0, $invoice['discount']);
        $this->assertSame(20.0, $invoice['line_items'][0]['discount']);
        $this->assertTrue($invoice['is_amount_discount']);
    }

    public function test_it_approves_quotes_with_a_harvest_accepted_date(): void
    {
        $this->writeAllHarvestExports();

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => []], 200);
            }

            if (str_ends_with($request->url(), '/clients')) {
                $id = $request['name'] === 'Acme & Co' ? 'client-acme' : 'client-solo';

                return Http::response(['data' => [
                    'name' => $request['name'],
                    'hashed_id' => $id,
                ]], 201);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/quotes')) {
                $this->assertSame('client-acme', $request['client_id']);
                $this->assertStringContainsString('Accepted Date: 2026-03-04', $request['private_notes']);

                return Http::response(['data' => ['id' => 'quote-100']], 201);
            }

            $this->assertSame('GET', $request->method());
            $this->assertSame($this->harvestApiUrl() . '/quotes/quote-100/approve', $request->url());

            return Http::response(['data' => ['id' => 'quote-100', 'status_id' => 3]], 200);
        });

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients,estimates',
        ])
            ->expectsOutputToContain('Applied approve status: EST-100')
            ->assertSuccessful();

        Http::assertSentCount(5);
    }

    public function test_it_includes_quote_action_failures_in_the_failure_report(): void
    {
        $this->writeAllHarvestExports();
        File::put($this->directory . '/estimates.csv', <<<'CSV'
            Issue Date,ID,Client,Subject,Estimate Amount,Subtotal,Accepted Date
            2026-03-01,EST-100,Acme & Co,First proposal,500,500,2026-03-04
            2026-03-02,EST-101,Acme & Co,Second proposal,600,600,2026-03-05
            CSV);
        $quote_requests = 0;

        Http::fake(function (Request $request) use (&$quote_requests) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => []], 200);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/clients')) {
                return Http::response(['data' => [
                    'hashed_id' => $request['name'] === 'Acme & Co' ? 'client-acme' : 'client-solo',
                ]], 201);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/quotes')) {
                $quote_requests++;

                return Http::response(['data' => ['hashed_id' => 'quote-100']], 201);
            }

            $this->assertSame(
                $this->harvestApiUrl() . '/quotes/quote-100/approve',
                $request->url(),
            );

            return Http::response([
                'message' => 'The quote could not be approved.',
                'errors' => ['status_id' => ['The status transition is not available.']],
            ], 409);
        });

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients,estimates',
            '--abort-on-failure' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit_code);
        $this->assertSame(1, $quote_requests);
        $this->assertStringContainsString('Failed estimates record', $output);
        $this->assertStringContainsString('during action:approve; HTTP 409; fields: status_id', $output);
        $this->assertStringContainsString('The quote could not be approved.', $output);
        $this->assertStringContainsString('Failure report (1)', $output);
        $this->assertStringContainsString('Import aborted after the first failure', $output);
    }

    public function test_it_includes_tax_rate_failures_in_the_failure_report(): void
    {
        File::put($this->directory . '/invoices.csv', <<<'CSV'
            Issue Date,ID,Client,Invoice Amount,Subtotal,Tax,Tax2
            2026-02-28,INV-TAX-FAIL,Example Client,113,100,13,0
            CSV);
        File::put($this->directory . '/invoice_line_items.csv', <<<'CSV'
            Invoice ID,Client,Item Type,Item Description,Item Quantity,Item Unit Price,Item Amount,Item Tax,Item Tax2,Issue Date
            INV-TAX-FAIL,Example Client,Service,Taxed work,1,100,100,13,0,2026-02-28
            CSV);
        File::put($this->directory . '/invoice_payments.csv', <<<'CSV'
            Payment Date,Invoice ID,Invoice Issue Date,Client,Invoice Amount,Payment Amount,Tax,Tax2,Currency,Currency Symbol,Document Type
            2026-03-05,INV-TAX-FAIL,2026-02-28,Example Client,113,50,0,0,CAD,$,Invoice
            CSV);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/clients')) {
                return Http::response(['data' => [[
                    'name' => 'Example Client',
                    'hashed_id' => 'client-example',
                ]]], 200);
            }

            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices')) {
                return Http::response(['data' => []], 200);
            }

            if ($request->url() === $this->harvestApiUrl() . '/invoices?mark_sent=true') {
                return Http::response(['data' => ['hashed_id' => 'invoice-tax-fail']], 201);
            }

            $this->assertStringEndsWith('/tax_rates', $request->url());

            return Http::response([
                'message' => 'The tax configuration could not be created.',
                'errors' => ['rate' => ['The rate 13 is unavailable.']],
            ], 500);
        });

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'invoices,invoice_payments',
            '--abort-on-failure' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('Failed tax_rates record', $output);
        $this->assertStringContainsString('during create; HTTP 500; fields: rate', $output);
        $this->assertStringContainsString('The tax configuration could not be created.', $output);
        $this->assertStringContainsString('The rate [redacted-number] is unavailable.', $output);
        $this->assertStringNotContainsString('The rate 13 is unavailable.', $output);
        $this->assertStringContainsString('Failure report (1)', $output);
        $this->assertStringContainsString('Import aborted after the first failure', $output);
        Http::assertNotSent(fn(Request $request): bool => $request->url() === $this->harvestApiUrl() . '/payments?email_receipt=false');
    }

    public function test_it_preserves_a_harvest_declined_date_without_approving_the_quote(): void
    {
        File::put($this->directory . '/estimates.csv', <<<'CSV'
            Issue Date,ID,PO Number,Client,Subject,Estimate Amount,Subtotal,Discount,Tax,Tax2,Currency,Accepted Date,Declined Date
            2026-04-01,EST-DECLINED,,Declined Client,Declined proposal,100,100,0,13,0,CAD,,2026-04-03
            CSV);

        $result = app(CsvImporter::class)->build($this->directory, [Entity::Clients, Entity::Estimates]);
        $quote = $result['records']['estimates'][0];

        $this->assertSame(
            "Declined proposal\n\nHarvest quote status:\nAccepted Date: Not set\nDeclined Date: 2026-04-03",
            $quote['payload']['private_notes'],
        );
        $this->assertSame([], $quote['actions']);
    }

    public function test_it_reports_api_failures_and_returns_a_failure_exit_code(): void
    {
        $this->writeHarvestExports();
        Http::fakeSequence()
            ->push(['data' => []], 200)
            ->push([
                'message' => 'The payload was rejected.',
                'errors' => ['amount' => ['Amount cannot exceed the invoice balance.']],
            ], 422)
            ->push(['data' => []], 201);

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients',
        ]);
        $output = Artisan::output();
        $fingerprint = substr(hash('sha256', 'harvest|clients|acme & co'), 0, 12);

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString(
            "Failed clients record [#1 / {$fingerprint}] during create; HTTP 422; fields: amount",
            $output,
        );
        $this->assertStringContainsString('The payload was rejected.', $output);
        $this->assertStringContainsString('Amount cannot exceed the invoice balance.', $output);
        $this->assertStringContainsString('Created client: Solo Client', $output);
        $this->assertStringContainsString('Failure report (1)', $output);
        $this->assertStringContainsString('http_status', $output);
        $this->assertStringNotContainsString('Unable to import Acme & Co', $output);
    }

    public function test_abort_on_failure_stops_before_the_next_record(): void
    {
        $this->writeHarvestExports();
        Http::fakeSequence()
            ->push(['data' => []], 200)
            ->push([
                'message' => 'The payload was rejected.',
                'errors' => ['name' => ['The name is unavailable.']],
            ], 422);

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients',
            '--abort-on-failure' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('Failure report (1)', $output);
        $this->assertStringContainsString('Import aborted after the first failure', $output);
        $this->assertStringNotContainsString('Created client: Solo Client', $output);
        Http::assertSentCount(2);
    }

    public function test_it_redacts_payload_and_incidental_personal_data_from_exception_failures(): void
    {
        File::put($this->directory . '/clients.csv', <<<'CSV'
            Client Name,Address
            Confidential Client,"10 Private Street
            Toronto ON M5V 1A1"
            CSV);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET') {
                return Http::response(['data' => []], 200);
            }

            throw new RuntimeException(
                'Could not send Confidential Client to private.person@example.test from /private/import/clients.csv or +1 416 555 0199.',
            );
        });

        $exit_code = Artisan::call('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $this->directory,
            '--entities' => 'clients',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exit_code);
        $this->assertStringContainsString('Failed clients record', $output);
        $this->assertStringContainsString('during create: RuntimeException:', $output);
        $this->assertStringContainsString('Failure report (1)', $output);
        $this->assertStringContainsString('[redacted]', $output);
        $this->assertStringContainsString('[redacted-email]', $output);
        $this->assertStringContainsString('[redacted-path]', $output);
        $this->assertStringContainsString('[redacted-phone]', $output);
        $this->assertStringNotContainsString('Confidential Client', $output);
        $this->assertStringNotContainsString('private.person@example.test', $output);
        $this->assertStringNotContainsString('/private/import/clients.csv', $output);
        $this->assertStringNotContainsString('+1 416 555 0199', $output);
    }

    public function test_it_fails_when_the_directory_does_not_exist(): void
    {
        $missing_directory = $this->directory . '/missing';

        $this->artisan('ninja:import-harvest', [
            'api_token' => 'test-api-token',
            'directory' => $missing_directory,
        ])
            ->expectsOutputToContain("Harvest import directory does not exist: {$missing_directory}")
            ->assertFailed();
    }

    private function writeHarvestExports(?string $directory = null): void
    {
        $directory ??= $this->directory;

        File::put($directory . '/clients.csv', <<<'CSV'
            Client Name,Address
            Acme & Co,"1 Main Street
            Suite 2
            Sydney NSW 2000"
            Solo Client,"Melbourne VIC"
            CSV);

        File::put($directory . '/contacts.csv', <<<'CSV'
            Client,First Name,Last Name,Title,Email,Office Phone,Mobile Phone,Fax,Invoice Email Default
            Acme & Co,Grace,Hopper,,grace@example.com,,0400 000 002,,CC
            Acme & Co,Ada,Lovelace,Director,ada@example.com,02 5555 1111,0400 000 001,02 5555 2222,Recipient
            Missing Client,Linus,Torvalds,,linus@example.com,02 5555 3333,,,None
            CSV);
    }

    private function writeExpenseCategoryExports(): void
    {
        File::put($this->directory . '/expense_categories.csv', <<<'CSV'
            Name
            Travel
            CSV);
        File::put($this->directory . '/expenses.csv', <<<'CSV'
            Date,Client,Project,Category,Notes,Cost,Currency,Billable?
            2026-02-02,,,Travel,Train,45.50,CAD,Yes
            CSV);
    }

    private function writeAllHarvestExports(): void
    {
        $this->writeHarvestExports();

        File::put($this->directory . '/projects.csv', <<<'CSV'
            Client,Project,Project Code,Start Date,End Date,Project Notes,Hourly Rate,Budgeted Hours
            Acme & Co,Website Redesign,WEB,2026-01-01,2026-06-30,Main redesign,125,100
            CSV);
        File::put($this->directory . '/tasks.csv', <<<'CSV'
            Task Name,Default Hourly Rate
            Consulting,125
            CSV);
        File::put($this->directory . '/people.csv', <<<'CSV'
            First Name,Last Name,Email,Telephone
            Ada,Lovelace,ada@example.com,0400 000 001
            CSV);
        File::put($this->directory . '/time.csv', <<<'CSV'
            Date,Client,Project,Task,Notes,Hours,Billable?,Billable Rate,First Name,Last Name,Email
            2026-02-01,Acme & Co,Website Redesign,Consulting,Architecture,2.5,Yes,125,Ada,Lovelace,ada@example.com
            CSV);
        File::put($this->directory . '/expense_categories.csv', <<<'CSV'
            Name
            Travel
            CSV);
        File::put($this->directory . '/expenses.csv', <<<'CSV'
            Date,Client,Project,Category,Notes,Cost,Currency,Billable?,First Name,Last Name,Email
            2026-02-02,Acme & Co,Website Redesign,Travel,Train,45.50,CAD,Yes,Ada,Lovelace,ada@example.com
            CSV);
        File::put($this->directory . '/invoices.csv', <<<'CSV'
            Issue Date,Last Payment Date,ID,PO Number,Client,Subject,Invoice Amount,Paid Amount,Balance,Subtotal,Discount,Tax,Tax2,Currency,Currency Symbol,Document Type,Client Address
            2026-02-28,2026-03-10,INV-100,PO-10,Acme & Co,February services,236,236,0,200,0,26,10,CAD,$,Invoice,1 Main Street
            CSV);
        File::put($this->directory . '/invoice_line_items.csv', <<<'CSV'
            Invoice ID,Client,Project,Item Type,Item Description,Item Quantity,Item Unit Price,Item Amount,Item Discount,Item Tax,Item Tax2,Currency,Invoice Type,Issue Date
            INV-100,Acme & Co,Website Redesign,Consulting,Architecture,2,100,200,0,26,10,CAD,Invoice,2026-02-28
            CSV);
        File::put($this->directory . '/invoice_payments.csv', <<<'CSV'
            Date,Invoice Number,Client,Amount Paid,Notes
            2026-03-10,INV-100,Acme & Co,236,Bank transfer
            CSV);
        File::put($this->directory . '/estimates.csv', <<<'CSV'
            Issue Date,ID,PO Number,Client,Subject,Estimate Amount,Subtotal,Discount,Tax,Tax2,Currency,Accepted Date,Declined Date
            2026-03-01,EST-100,PO-20,Acme & Co,Phase two,500,500,0,65,0,CAD,2026-03-04,
            CSV);
        File::put($this->directory . '/estimate_line_items.csv', <<<'CSV'
            Estimate Number,Client,Description,Quantity,Unit Price,Item Type
            EST-100,Acme & Co,Development,4,125,Consulting
            CSV);
    }
}
