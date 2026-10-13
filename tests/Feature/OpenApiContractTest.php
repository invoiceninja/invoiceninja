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

namespace Tests\Feature;

use App\Http\Middleware\PasswordProtection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use League\OpenAPIValidation\Schema\Exception\SchemaMismatch;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Validates real API responses against openapi/api-docs.yaml, so the
 * published spec can't silently drift from what the API returns.
 */
class OpenApiContractTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const LINE_ITEMS = [['product_key' => 'Contract', 'notes' => 'Contract test item', 'cost' => 10, 'quantity' => 2]];

    /**
     * name => [collection path, store/update body, operations to skip].
     * A '{name}' body value is replaced by the hashed id of that entity, which is created on demand.
     *
     * Skipped operations are ones the API does not offer, or known API deviations the spec can't express:
     * - GET /clients/create returns settings without the required currency_id
     * - GET /{invoices,quotes,credits,...}/create returns line_items as the string "[]" instead of an array
     * - GET /tags/create returns an empty name and entity_type
     * - GET /task_schedulers/{id}/edit is routed but not implemented (HTTP 500)
     * - users have no edit route
     * - POST /companies returns the new company users instead of the company, so the
     *   company from MockAccountData is used and the store response has a test of its own
     */
    private const ENTITIES = [
        'client' => ['/api/v1/clients', ['name' => 'Contract Test Client'], ['create']],
        'invoice' => ['/api/v1/invoices', ['client_id' => '{client}', 'line_items' => self::LINE_ITEMS], ['create']],
        'quote' => ['/api/v1/quotes', ['client_id' => '{client}', 'line_items' => self::LINE_ITEMS], ['create']],
        'credit' => ['/api/v1/credits', ['client_id' => '{client}', 'line_items' => self::LINE_ITEMS], ['create']],
        'recurring_invoice' => ['/api/v1/recurring_invoices', ['client_id' => '{client}', 'frequency_id' => '5', 'line_items' => self::LINE_ITEMS], ['create']],
        'recurring_quote' => ['/api/v1/recurring_quotes', ['client_id' => '{client}', 'frequency_id' => '5', 'line_items' => self::LINE_ITEMS], ['create']],
        'purchase_order' => ['/api/v1/purchase_orders', ['vendor_id' => '{vendor}', 'line_items' => self::LINE_ITEMS], ['create']],
        'payment' => ['/api/v1/payments', ['client_id' => '{client}', 'amount' => 10, 'date' => '2026-01-01']],
        'product' => ['/api/v1/products', ['product_key' => 'Contract Test Product', 'price' => 10]],
        'vendor' => ['/api/v1/vendors', ['name' => 'Contract Test Vendor']],
        'expense' => ['/api/v1/expenses', ['amount' => 10, 'vendor_id' => '{vendor}']],
        'recurring_expense' => ['/api/v1/recurring_expenses', ['amount' => 10, 'frequency_id' => '5']],
        'project' => ['/api/v1/projects', ['name' => 'Contract Test Project', 'client_id' => '{client}']],
        'task' => ['/api/v1/tasks', ['description' => 'Contract test task', 'client_id' => '{client}']],
        'expense_category' => ['/api/v1/expense_categories', ['name' => 'Contract Test Category']],
        'tax_rate' => ['/api/v1/tax_rates', ['name' => 'Contract Test Tax', 'rate' => 19]],
        'payment_term' => ['/api/v1/payment_terms', ['name' => 'Contract Test Term', 'num_days' => 45]],
        'task_status' => ['/api/v1/task_statuses', ['name' => 'Contract Test Status']],
        'design' => ['/api/v1/designs', ['name' => 'Contract Test Design', 'design' => ['body' => '<div></div>', 'includes' => '', 'product' => '', 'task' => '', 'footer' => '', 'header' => '']]],
        'group_setting' => ['/api/v1/group_settings', ['name' => 'Contract Test Group', 'settings' => ['currency_id' => '1']]],
        'location' => ['/api/v1/locations', ['name' => 'Contract Test Location', 'client_id' => '{client}']],
        'tag' => ['/api/v1/tags', ['name' => 'Contract Test Tag', 'entity_type' => 'invoice'], ['create']],
        'token' => ['/api/v1/tokens', ['name' => 'Contract Test Token']],
        'user' => ['/api/v1/users', ['first_name' => 'Contract', 'last_name' => 'Test', 'email' => 'contract-test@example.com'], ['edit']],
        'webhook' => ['/api/v1/webhooks', ['target_url' => 'https://example.com/hook', 'event_id' => '1', 'rest_method' => 'post', 'format' => 'JSON']],
        'subscription' => ['/api/v1/subscriptions', ['name' => 'Contract Test Subscription', 'product_ids' => '{product}', 'steps' => 'cart,auth.login-or-register']],
        'bank_integration' => ['/api/v1/bank_integrations', ['bank_account_name' => 'Contract Test Bank']],
        'bank_transaction' => ['/api/v1/bank_transactions', ['bank_integration_id' => '{bank_integration}', 'amount' => 10, 'description' => 'Contract test transaction', 'base_type' => 'CREDIT', 'date' => '2026-01-01']],
        'bank_transaction_rule' => ['/api/v1/bank_transaction_rules', ['name' => 'Contract Test Rule', 'rules' => [['search_key' => 'description', 'operator' => 'contains', 'value' => 'contract']], 'applies_to' => 'CREDIT']],
        'task_scheduler' => ['/api/v1/task_schedulers', ['name' => 'Contract Test Schedule', 'template' => 'client_statement', 'frequency_id' => 5, 'next_run' => '2030-01-01', 'parameters' => ['date_range' => 'last_month', 'show_payments_table' => true, 'show_aging_table' => true, 'status' => 'paid', 'clients' => []]], ['edit']],
        'company' => ['/api/v1/companies', ['name' => 'Contract Test Company'], ['store']],
        'company_gateway' => ['/api/v1/company_gateways', ['gateway_key' => '3b6621f970ab18887c4f6dca78d3f8bb', 'config' => '{"publishableKey":"pk_test","apiKey":"sk_test"}']],
        'client_gateway_token' => ['/api/v1/client_gateway_tokens', ['client_id' => '{client}', 'company_gateway_id' => '{company_gateway}', 'gateway_type_id' => '1', 'token' => 'tok_contract', 'meta' => ['brand' => 'visa', 'last4' => '4242']]],
    ];

    /** Collections that can only be listed, validated after an invoice and a payment were created through the API. */
    private const READ_ONLY = ['/api/v1/activities', '/api/v1/company_ledger'];

    /** [operation => [method, path suffix]], in the order they run */
    private const OPERATIONS = [
        'store' => ['post', ''],
        'list' => ['get', ''],
        'show' => ['get', '/{id}'],
        'edit' => ['get', '/{id}/edit'],
        'update' => ['put', '/{id}'],
        'create' => ['get', '/create'],
    ];

    /** hashed ids of the entities created through the API, keyed by name */
    private array $created = [];

    private ResponseValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Model::reguard();

        $this->withoutMiddleware(PasswordProtection::class);

        $this->created['company'] = $this->company->hashed_id;

        $this->validator = (new ValidatorBuilder())->fromYamlFile(base_path('openapi/api-docs.yaml'))->getResponseValidator();
    }

    /** Plain data only: phpunit.xml runs tests in separate processes, so rows get serialized. */
    public static function entities(): array
    {
        return array_combine(array_keys(self::ENTITIES), array_map(fn ($name) => [$name], array_keys(self::ENTITIES)));
    }

    #[DataProvider('entities')]
    public function testResponsesMatchSpec(string $entity): void
    {
        [$collection, $body] = self::ENTITIES[$entity];
        $mismatches = [];

        foreach (array_diff_key(self::OPERATIONS, array_flip(self::ENTITIES[$entity][2] ?? [])) as $operation => [$method, $suffix]) {
            $path = $collection.$suffix;
            $response = $this->apiRequest($method, str_replace('{id}', $this->created[$entity] ?? '', $path), $method === 'get' ? [] : $body);

            if ($operation === 'store') {
                $this->created[$entity] = $response->json('data.id');
            }

            if ($mismatch = $this->specMismatch($method, $path, $response, $this->created[$entity] ?? null)) {
                $mismatches[] = strtoupper($method)." {$path}:\n  - ".implode("\n  - ", $mismatch);
            }

            // Without a stored entity the remaining operations have nothing to run against.
            if (empty($this->created[$entity])) {
                break;
            }
        }

        $this->assertEmpty($mismatches, "Responses do not match the spec:\n".implode("\n", $mismatches));
    }

    public static function readOnlyCollections(): array
    {
        return array_combine(self::READ_ONLY, array_map(fn ($path) => [$path], self::READ_ONLY));
    }

    #[DataProvider('readOnlyCollections')]
    public function testReadOnlyCollectionMatchesSpec(string $path): void
    {
        $this->created('invoice');
        $this->created('payment');

        $mismatch = $this->specMismatch('get', $path, $this->apiRequest('get', $path), null);

        $this->assertEmpty($mismatch, "GET {$path} does not match the spec:\n  - ".implode("\n  - ", $mismatch));
    }

    public function testDocumentedOperationsAreRouted(): void
    {
        $unrouted = [];

        foreach ($this->validator->getSchema()->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $method) {
                // Uploads are documented as POST with a _method=PUT field, as PHP only parses multipart bodies on POST.
                $verb = str_ends_with($path, '/upload') ? 'PUT' : strtoupper($method);

                try {
                    $routed = ! Route::getRoutes()->match(Request::create(preg_replace('/\{\w+\}/', 'x', $path), $verb))->isFallback;
                } catch (HttpExceptionInterface) {
                    $routed = false;
                }

                if (! $routed) {
                    $unrouted[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertEmpty($unrouted, "The spec documents operations the API does not route:\n".implode("\n", $unrouted));
    }

    public function testCompanyStoreMatchesSpec(): void
    {
        $response = $this->apiRequest('post', '/api/v1/companies', self::ENTITIES['company'][1]);
        $mismatch = $this->specMismatch('post', '/api/v1/companies', $response, null);

        $this->assertEmpty($mismatch, "POST /api/v1/companies does not match the spec:\n  - ".implode("\n  - ", $mismatch));
    }

    private function created(string $entity): string
    {
        [$collection, $body] = self::ENTITIES[$entity];

        return $this->created[$entity] ??= $this->apiRequest('post', $collection, $body)->assertSuccessful()->json('data.id');
    }

    private function apiRequest(string $method, string $url, array $body = []): TestResponse
    {
        array_walk_recursive($body, function (&$value) {
            if (is_string($value) && preg_match('/^\{(\w+)\}$/', $value, $match)) {
                $value = $this->created($match[1]);
            }
        });

        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->json(strtoupper($method), $url, $body);
    }

    /** The reasons the response does not match the spec, empty when it does. */
    private function specMismatch(string $method, string $path, TestResponse $response, ?string $id): array
    {
        if (! $response->isSuccessful()) {
            return ["returned HTTP {$response->getStatusCode()}: ".substr($response->getContent(), 0, 300)];
        }

        $content = json_decode($response->getContent());

        // MockAccountData rows skip the API's defaults (e.g. a client's settings.currency_id),
        // so a list is narrowed to the entity created through the API.
        if ($id && is_array($content->data ?? null)) {
            $content->data = array_values(array_filter($content->data, fn ($row) => $row->id === $id));
            $response->baseResponse->setContent(json_encode($content));
        }

        // Empty data validates against any schema, so it would pass without checking anything.
        if (empty($content->data)) {
            return ['returned no data to validate'];
        }

        if (empty($this->validator->getSchema()->paths->getPath($path)?->{$method}?->responses?->getResponse('200')?->content)) {
            return ['the spec documents no response body'];
        }

        $factory = new Psr17Factory();
        $psr = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createResponse($response->baseResponse);

        try {
            $this->validator->validate(new OperationAddress($path, $method), $psr);
        } catch (ValidationFailed $e) {
            $reasons = [];
            for ($ex = $e; $ex; $ex = $ex->getPrevious()) {
                $field = $ex instanceof SchemaMismatch && $ex->dataBreadCrumb() ? ' (at '.implode('.', $ex->dataBreadCrumb()->buildChain()).')' : '';
                $reasons[] = $ex->getMessage().$field;
            }

            return $reasons;
        }

        return [];
    }
}
