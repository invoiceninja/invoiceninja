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

namespace Tests\Feature\Import\CSV;

use App\Http\Controllers\ImportController;
use App\Jobs\Import\CSVIngest;
use App\Models\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClientImportLimitTest extends TestCase
{
    private Account $account;

    private int $existing_clients = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.environment' => 'hosted', 'ninja.production' => true, 'cache.default' => 'array']);
        Bus::fake([CSVIngest::class]);

        $this->account = new Account();
        $this->account->plan = 'free';
        $this->account->hosted_client_count = 5;
        $this->account->key = 'client-import-limit-test';

        $clients = Mockery::mock(HasMany::class);
        $clients->shouldReceive('count')->andReturnUsing(fn (): int => $this->existing_clients);

        $company = Mockery::mock(Company::class)->makePartial();
        $company->setRelation('account', $this->account);
        $company->shouldReceive('clients')->andReturn($clients);
        $company->shouldReceive('getLocale')->andReturn('en');

        $user = Mockery::mock(User::class)->makePartial();
        $user->setRelation('account', $this->account);
        $user->shouldReceive('company')->andReturn($company);
        $user->shouldReceive('isAdmin')->andReturn(true);
        $this->actingAs($user);

        Route::post('/_test/preimport', [ImportController::class, 'preimport']);
        Route::post('/_test/import', [ImportController::class, 'import']);
    }

    #[DataProvider('quotaBoundaries')]
    public function testClientQuotaIsValidatedBeforeImportStarts(string $flow, int $existing_clients, int $import_clients, bool $allowed): void
    {
        $this->existing_clients = $existing_clients;
        $csv = "Name,Email\n";

        for ($index = 1; $index <= $import_clients; $index++) {
            $csv .= "Client {$index},client{$index}@example.com\n";
        }

        $response = $this->submitImport($flow, $csv);

        if ($allowed) {
            $response->assertOk();

            if ($flow === 'preimport') {
                $this->assertTrue(Cache::has($response->json('hash').'-client'));
                Bus::assertNotDispatched(CSVIngest::class);
            } else {
                Bus::assertDispatched(CSVIngest::class);
            }

            return;
        }

        $error_key = $flow === 'cached' ? 'column_map.client' : 'files.client';
        $response->assertUnprocessable()->assertJsonValidationErrors($error_key);
        $this->assertSame(
            [ctrans('texts.limit_clients', ['count' => 5])],
            $response->json('errors')[$error_key]
        );
        Bus::assertNotDispatched(CSVIngest::class);
    }

    public static function quotaBoundaries(): iterable
    {
        foreach (['preimport', 'upload', 'cached'] as $flow) {
            yield $flow.' exactly five' => [$flow, 0, 5, true];
            yield $flow.' six clients' => [$flow, 0, 6, false];
            yield $flow.' remaining capacity' => [$flow, 3, 2, true];
            yield $flow.' exceeds remaining capacity' => [$flow, 3, 3, false];
            yield $flow.' already at quota' => [$flow, 5, 1, false];
        }
    }

    #[DataProvider('contactRows')]
    public function testMultipleContactsCountAsOneClient(string $flow): void
    {
        $this->existing_clients = 4;
        $csv = "Name,Email\n";

        for ($index = 1; $index <= 8; $index++) {
            $csv .= "Same Client,contact{$index}@example.com\n";
        }

        $this->submitImport($flow, $csv)->assertOk();
    }

    public static function contactRows(): iterable
    {
        foreach (['preimport', 'upload', 'cached'] as $flow) {
            yield $flow => [$flow];
        }
    }

    #[DataProvider('csvRecords')]
    public function testCsvRecordsAreCountedUsingTheImportOptions(string $csv, bool $skip_header, int $expected_status): void
    {
        $this->existing_clients = 4;

        $this->submitImport('cached', $csv, ['skip_header' => $skip_header])->assertStatus($expected_status);
    }

    public static function csvRecords(): iterable
    {
        yield 'quoted newline' => ["Name,Email\n\"Multi\nLine\",one@example.com\n", true, 200];
        yield 'empty records' => ["Name,Email\n\n,\nOnly Client,one@example.com\n\n", true, 200];
        yield 'semicolon delimiter' => ["Name;Email\nOnly Client;one@example.com\n", true, 200];
        yield 'pipe delimiter' => ["Name|Email\nFirst|one@example.com\nSecond|two@example.com\n", true, 422];
        yield 'headerless at quota' => ["Only Client,one@example.com\n", false, 200];
        yield 'headerless over quota' => ["First,one@example.com\nSecond,two@example.com\n", false, 422];
        yield 'client name containing app name is not a short preamble' => ["Invoice Ninja Customer,one@example.com\nSecond,two@example.com\n", false, 422];
        yield 'unnamed clients stay separate' => ["Name,Email\n,one@example.com\n,two@example.com\n", true, 422];
        yield 'UTF-8 BOM' => ["\xEF\xBB\xBFName,Email\nOnly Client,one@example.com\n", true, 200];
        yield 'UTF-16 BOM' => ["\xFF\xFE".mb_convert_encoding("Name,Email\nFirst,one@example.com\nSecond,two@example.com\n", 'UTF-16LE', 'UTF-8'), true, 422];
        yield 'export preamble' => ["Invoice Ninja Report\n\"\"\nClients\nName,Email\nOnly Client,one@example.com\n", true, 200];
        yield 'export preamble over quota' => ["Invoice Ninja Report\n\"\"\nClients\nName,Email\nFirst,one@example.com\nSecond,two@example.com\n", true, 422];
    }

    #[DataProvider('exemptAccounts')]
    public function testPaidAndSelfHostedAccountsAreExempt(string $environment, bool $production, string $plan): void
    {
        config(['ninja.environment' => $environment, 'ninja.production' => $production]);
        $this->account->plan = $plan;
        $this->existing_clients = 5;

        $this->submitImport('cached', "Name,Email\nAnother Client,one@example.com\n")->assertOk();
        Bus::assertDispatched(CSVIngest::class);
    }

    public static function exemptAccounts(): iterable
    {
        yield 'hosted pro' => ['hosted', true, 'pro'];
        yield 'hosted enterprise' => ['hosted', true, 'enterprise'];
        yield 'self hosted' => ['selfhost', false, 'free'];
    }

    public function testNonClientImportsAreExemptEvenWithClientColumns(): void
    {
        $this->existing_clients = 5;

        $this->postJson('/_test/import', [
            'import_type' => 'csv',
            'hash' => 'invoice-import',
            'skip_header' => true,
            'column_map' => ['invoice' => ['mapping' => [0 => 'client.name', 1 => 'invoice.number']]],
        ])->assertOk();

        $this->post('/_test/preimport', [
            'import_type' => 'csv',
            'files' => ['vendor' => UploadedFile::fake()->createWithContent('vendors.csv', "Name,Email\nVendor,vendor@example.com\n")],
        ], ['Accept' => 'application/json'])->assertOk();
    }

    public function testAccountSpecificQuotaIsUsed(): void
    {
        $this->account->hosted_client_count = 10;
        $this->existing_clients = 8;

        $this->submitImport('cached', "Name,Email\nFirst,one@example.com\nSecond,two@example.com\n")->assertOk();
    }

    public function testFinalImportUsesTheSelectedNameColumn(): void
    {
        $this->existing_clients = 4;

        $this->submitImport('cached', "Email,Organization\none@example.com,Same Client\ntwo@example.com,Same Client\n", [
            'column_map' => ['client' => ['mapping' => [0 => 'contact.email', 1 => 'client.name']]],
        ])->assertOk();
    }

    #[DataProvider('providerImports')]
    public function testProviderClientCsvImportsAlsoRespectTheQuota(string $flow, string $import_type): void
    {
        $this->existing_clients = 5;

        $this->submitImport($flow, "Organization,Email\nAnother Client,one@example.com\n", [
            'import_type' => $import_type,
            'column_map' => ['client' => ['mapping' => []]],
        ])->assertUnprocessable()->assertJsonValidationErrors($flow === 'cached' ? 'column_map.client' : 'files.client');

        Bus::assertNotDispatched(CSVIngest::class);
    }

    public static function providerImports(): iterable
    {
        foreach (['preimport', 'upload', 'cached'] as $flow) {
            foreach (['freshbooks', 'waveaccounting', 'zoho', 'invoicely'] as $import_type) {
                yield $flow.' '.$import_type => [$flow, $import_type];
            }
        }
    }

    public function testFreshbooksContactRowsAreGroupedByOrganization(): void
    {
        $this->existing_clients = 4;

        $this->submitImport('cached', "Organization,Email\nSame Client,one@example.com\nSame Client,two@example.com\n", [
            'import_type' => 'freshbooks',
            'column_map' => ['client' => ['mapping' => []]],
        ])->assertOk();
    }

    public function testRejectedUploadIsNotCached(): void
    {
        $this->existing_clients = 5;
        Cache::spy();

        $this->submitImport('preimport', "Name,Email\nAnother Client,one@example.com\n")->assertUnprocessable();

        Cache::shouldNotHaveReceived('put');
        Bus::assertNotDispatched(CSVIngest::class);
    }

    public function testInvalidBasePayloadIsNotParsed(): void
    {
        $this->submitImport('cached', "Name,Email\nClient,one@example.com\n", ['skip_header' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('skip_header')->assertJsonMissingValidationErrors('column_map.client');

        Bus::assertNotDispatched(CSVIngest::class);
    }

    private function submitImport(string $flow, string $csv, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $data = array_merge([
            'import_type' => 'csv',
            'skip_header' => true,
            'column_map' => ['client' => ['mapping' => [0 => 'client.name', 1 => 'contact.email']]],
        ], $overrides);

        if ($flow === 'cached') {
            $data['hash'] = 'client-quota-test';
            Cache::put($data['hash'].'-client', base64_encode($csv), 600);

            return $this->postJson('/_test/import', $data);
        }

        $data['files'] = ['client' => UploadedFile::fake()->createWithContent('clients.csv', $csv)];

        return $this->post('/_test/'.($flow === 'preimport' ? 'preimport' : 'import'), $data, ['Accept' => 'application/json']);
    }
}
