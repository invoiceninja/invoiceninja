<?php

namespace Tests\Feature\PaymentDrivers\GoCardless;

use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\PaymentDrivers\GoCardless\Jobs\ImportGoCardlessCustomers;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use GoCardlessPro\Client as GoCardlessClient;
use GoCardlessPro\Core\ApiClient;
use GoCardlessPro\Services\CustomerBankAccountsService;
use GoCardlessPro\Services\CustomersService;
use GoCardlessPro\Services\MandatesService;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

class ImportCustomersTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $company_gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->company_gateway = new CompanyGateway();
        $this->company_gateway->company_id = $this->company->id;
        $this->company_gateway->user_id = $this->user->id;
        $this->company_gateway->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
        $this->company_gateway->config = encrypt(json_encode(['accessToken' => 'fake_token', 'testMode' => true]));
        $this->company_gateway->fees_and_limits = [];
        $this->company_gateway->save();
    }

    public function testDriverQueuesImportWithoutCallingGoCardless(): void
    {
        Queue::fake();

        $driver = Mockery::mock(GoCardlessPaymentDriver::class, [$this->company_gateway])->makePartial();
        $driver->shouldNotReceive('init');
        $driver->importCustomers();

        Queue::assertPushed(ImportGoCardlessCustomers::class, function ($job) {
            return $job->company_key === $this->company->company_key
                && $job->company_gateway_id === $this->company_gateway->id;
        });
        Queue::assertPushed(ImportGoCardlessCustomers::class, 1);
    }

    #[DataProvider('unavailableGateways')]
    public function testJobSkipsUnavailableGateways(string $state): void
    {
        $gateway = Mockery::mock('overload:' . GoCardlessClient::class);
        $gateway->shouldNotReceive('__construct');

        $company_key = $this->company->company_key;
        $company_gateway_id = $this->company_gateway->id;

        switch ($state) {
            case 'missing':
                $company_gateway_id = PHP_INT_MAX;
                break;
            case 'deleted':
                $this->company_gateway->is_deleted = true;
                $this->company_gateway->saveQuietly();
                break;
            case 'archived':
                $this->company_gateway->delete();
                break;
            case 'other_company':
                $other_company = $this->company->replicate();
                $other_company->company_key = 'other-import-company';
                $other_company->saveQuietly();
                $company_key = $other_company->company_key;
                break;
            case 'other_gateway':
                $this->company_gateway->gateway_key = 'd14dd26a47cecc30fdd65700bfb67b34';
                $this->company_gateway->saveQuietly();
                break;
            case 'missing_company':
                $company_key = 'missing-import-company';
                break;
        }

        (new ImportGoCardlessCustomers($company_key, $company_gateway_id))->handle();

        $this->assertSame(0, $this->company_gateway->client_gateway_tokens()->count());
    }

    public static function unavailableGateways(): array
    {
        return array_map(fn ($state) => [$state], ['missing', 'deleted', 'archived', 'other_company', 'other_gateway', 'missing_company']);
    }

    public function testImportCreatesClientAndMandateWithoutDuplicatingThemOnRepeat(): void
    {
        $customer = $this->customer('CU_NEW', 'new-gocardless@example.test');
        $responses = [
            $this->response('customers', [$customer]),
            $this->response('mandates', [$this->mandate('MD_NEW')]),
            $this->bankAccountResponse(),
        ];

        $this->importJob(array_merge($responses, array_slice($responses, 0, 2)))->handle();

        $contact = ClientContact::query()->where('company_id', $this->company->id)->where('email', $customer['email'])->firstOrFail();
        $token = $this->company_gateway->client_gateway_tokens()->sole();

        $this->assertSame('Import', $contact->first_name);
        $this->assertSame('Customer', $contact->last_name);
        $this->assertSame('1 Main Street', $contact->client->address1);
        $this->assertNotEmpty($contact->client->number);
        $this->assertSame($contact->client_id, $token->client_id);
        $this->assertSame('CU_NEW', $token->gateway_customer_reference);
        $this->assertSame('MD_NEW', $token->token);
        $this->assertSame(GatewayType::DIRECT_DEBIT, $token->gateway_type_id);
        $this->assertSame('authorized', $token->meta->state);
        $this->assertSame('Import Customer - 1234', $token->meta->last4);

        (new ImportGoCardlessCustomers($this->company->company_key, $this->company_gateway->id))->handle();

        $this->assertSame(1, ClientContact::query()->where('company_id', $this->company->id)->where('email', $customer['email'])->count());
        $this->assertSame(1, $this->company_gateway->client_gateway_tokens()->count());
    }

    public function testExistingCustomerReferenceTakesPrecedenceOverEmail(): void
    {
        $this->storeExistingToken();
        $client_count = $this->company->clients()->count();

        $this->importJob([
            $this->response('customers', [$this->customer('CU_NEW', 'different-gocardless@example.test')]),
            $this->response('mandates', [$this->mandate('MD_NEW')]),
            $this->bankAccountResponse(),
        ])->handle();

        $token = $this->company_gateway->client_gateway_tokens()->where('token', 'MD_NEW')->sole();
        $this->assertSame($this->client->id, $token->client_id);
        $this->assertSame($client_count, $this->company->clients()->count());
    }

    public function testMatchingContactEmailReusesClient(): void
    {
        $client_count = $this->company->clients()->count();

        $this->importJob([
            $this->response('customers', [$this->customer('CU_NEW', $this->client->contacts()->first()->email)]),
            $this->response('mandates', [$this->mandate('MD_NEW')]),
            $this->bankAccountResponse(),
        ])->handle();

        $this->assertSame($this->client->id, $this->company_gateway->client_gateway_tokens()->sole()->client_id);
        $this->assertSame($client_count, $this->company->clients()->count());
    }

    public function testImportPreservesMandateFilteringAndSchemeMapping(): void
    {
        $this->storeExistingToken();

        $this->importJob([
            $this->response('customers', [$this->customer('CU_NEW', 'import@example.test')]),
            $this->response('mandates', [
                $this->mandate('MD_EXISTING'),
                $this->mandate('MD_CANCELLED', 'cancelled'),
                $this->mandate('MD_UNSUPPORTED', 'active', 'becs'),
                $this->mandate('MD_BANK_ERROR'),
                $this->mandate('MD_BACS'),
                $this->mandate('MD_SEPA', 'pending_submission', 'sepa_core'),
                $this->mandate('MD_ACH', 'active', 'ach'),
            ]),
            new RuntimeException('Bank account unavailable'),
            $this->bankAccountResponse(),
            $this->bankAccountResponse(),
            $this->bankAccountResponse(),
        ])->handle();

        $this->assertSame([
            'MD_ACH' => GatewayType::BANK_TRANSFER,
            'MD_BACS' => GatewayType::DIRECT_DEBIT,
            'MD_EXISTING' => GatewayType::DIRECT_DEBIT,
            'MD_SEPA' => GatewayType::SEPA,
        ], $this->company_gateway->client_gateway_tokens()->orderBy('token')->pluck('gateway_type_id', 'token')->all());
    }

    public function testImportProcessesMultipleCustomerPages(): void
    {
        $this->importJob([
            $this->response('customers', [$this->customer('CU_PAGE_1', 'gocardless-page-1@example.test')], 'CU_PAGE_1'),
            $this->response('mandates', []),
            $this->response('customers', [$this->customer('CU_PAGE_2', 'gocardless-page-2@example.test')]),
            $this->response('mandates', []),
        ])->handle();

        $this->assertSame(2, ClientContact::query()->where('company_id', $this->company->id)
            ->whereIn('email', ['gocardless-page-1@example.test', 'gocardless-page-2@example.test'])->count());
    }

    public function testImportFailurePropagates(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Customer list unavailable');

        $this->importJob([new RuntimeException('Customer list unavailable')])->handle();
    }

    public function testOverlappingImportsAreSkippedAndLockIsReleased(): void
    {
        Cache::setDefaultDriver('array');
        $job = new ImportGoCardlessCustomers($this->company->company_key, $this->company_gateway->id);
        $middleware = $job->middleware()[0];
        $lock = Cache::lock($middleware->getLockKey($job), $job->timeout + 60);
        $this->assertTrue($lock->get());

        $middleware->handle($job, fn () => $this->fail('Overlapping import should be skipped'));
        $lock->release();

        $runs = 0;
        $next = function () use (&$runs) {
            $runs++;
        };
        $middleware->handle($job, $next);
        $middleware->handle($job, $next);

        $this->assertSame(2, $runs);
    }

    private function importJob(array $responses): ImportGoCardlessCustomers
    {
        $api_client = new ApiClient(new HttpClient([
            'base_uri' => 'https://api-sandbox.gocardless.com',
            'handler' => new MockHandler($responses),
        ]), ['error_on_idempotency_conflict' => true]);

        $gateway = Mockery::mock('overload:' . GoCardlessClient::class);
        $gateway->shouldReceive('__construct')->once()->with(Mockery::on(fn ($config) => $config === [
            'access_token' => 'fake_token',
            'environment' => 'sandbox',
        ]));
        $gateway->shouldReceive('customers')->once()->andReturn(new CustomersService($api_client));
        $gateway->shouldReceive('mandates')->andReturn(new MandatesService($api_client));
        $gateway->shouldReceive('customerBankAccounts')->andReturn(new CustomerBankAccountsService($api_client));

        return new ImportGoCardlessCustomers($this->company->company_key, $this->company_gateway->id);
    }

    private function response(string $resource, array $records, ?string $after = null): Response
    {
        return new Response(200, [], json_encode([
            $resource => $records,
            'meta' => ['cursors' => ['before' => null, 'after' => $after]],
        ]));
    }

    private function customer(string $id, string $email): array
    {
        return ['id' => $id, 'email' => $email, 'given_name' => 'Import', 'family_name' => 'Customer',
            'company_name' => 'Import Company', 'address_line1' => '1 Main Street'];
    }

    private function mandate(string $id, string $status = 'active', string $scheme = 'bacs'): array
    {
        return ['id' => $id, 'status' => $status, 'scheme' => $scheme,
            'links' => ['customer' => 'CU_NEW', 'customer_bank_account' => 'BA_IMPORT']];
    }

    private function bankAccountResponse(): Response
    {
        return new Response(200, [], json_encode(['customer_bank_accounts' => [
            'id' => 'BA_IMPORT', 'account_holder_name' => 'Import Customer',
            'account_number_ending' => '1234', 'bank_name' => 'Test Bank',
        ]]));
    }

    private function storeExistingToken(): void
    {
        $this->company_gateway->driver($this->client)->storeGatewayToken([
            'token' => 'MD_EXISTING',
            'payment_method_id' => GatewayType::DIRECT_DEBIT,
            'payment_meta' => (object) ['state' => 'authorized'],
        ], ['gateway_customer_reference' => 'CU_NEW']);
    }
}
