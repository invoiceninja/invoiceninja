<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Factory\CompanyGatewayFactory;
use App\Factory\CompanyUserFactory;
use App\Http\Controllers\AccountController;
use App\Http\Requests\Account\CreateAccountRequest;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\TaxRate;
use App\Models\User;
use App\Transformers\AuthenticatedCompanyUserTransformer;
use App\Transformers\CompanyTransformer;
use App\Utils\TruthSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CompanyResponseScopeTest extends TestCase
{
    use DatabaseTransactions;

    private Account $account;
    private User $user;
    private User $owner;
    private array $companies = [];
    private array $tokens = [];
    private array $records = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['ninja.environment' => 'selfhost']);
        $this->withoutMiddleware(ThrottleRequests::class);
        Session::start();
        // Registered after DatabaseTransactions so this checks rollback, including on failure.
        $this->beforeApplicationDestroyed(function () {
            if (! isset($this->account)) {
                return;
            }
            $this->assertFalse(DB::table('accounts')->where('id', $this->account->id)->exists());
            foreach (['companies', 'users', 'company_user', 'company_tokens'] as $table) {
                $this->assertFalse(DB::table($table)->where('account_id', $this->account->id)->exists());
            }
            foreach ($this->records as $records) {
                foreach ($records as $record) {
                    $this->assertFalse(DB::table($record->getTable())->where('id', $record->id)->exists());
                }
            }
        });
    }

    private function createFixture(bool $admin_a = true, bool $is_large = false): void
    {
        $this->account = Account::factory()->create(['num_users' => 2]);
        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => Str::uuid().'@example.test',
            'password' => Hash::make('company-scope-test'),
            'confirmation_code' => Str::random(32),
        ]);
        $this->owner = User::factory()->create(['account_id' => $this->account->id, 'email' => Str::uuid().'@example.test']);

        foreach ([0, 1] as $index) {
            $company = Company::factory()->create([
                'account_id' => $this->account->id, 'is_large' => $is_large,
            ]);
            $this->companies[$index] = $company;
            foreach ([$this->user, $this->owner] as $user) {
                $membership = CompanyUserFactory::create($user->id, $company->id, $this->account->id);
                $membership->is_admin = $user->is($this->owner) || ($index === 0 ? $admin_a : ! $admin_a);
                $membership->is_owner = $user->is($this->owner);
                $membership->is_locked = false;
                $membership->permissions = '';
                $membership->save();
            }
            $token = new CompanyToken();
            $token->forceFill([
                'account_id' => $this->account->id, 'company_id' => $company->id,
                'user_id' => $this->user->id, 'token' => Str::random(64),
                'name' => 'Company scope test', 'is_system' => true,
            ])->save();
            $this->tokens[$index] = $token->token;
            $attributes = ['company_id' => $company->id, 'user_id' => $this->owner->id];
            $client = Client::factory()->create($attributes);
            $invoice = Invoice::factory()->create($attributes + ['client_id' => $client->id]);
            $document = $company->documents()->save(Document::factory()->make($attributes));
            $tax_rate = TaxRate::factory()->create($attributes);
            $gateway = CompanyGatewayFactory::create($company->id, $this->owner->id);
            $gateway->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
            $gateway->config = encrypt(json_encode(['apiKey' => 'company-scope-secret-'.$index]));
            $gateway->save();
            $this->records[$index] = compact('client', 'invoice', 'document', 'tax_rate', 'gateway');
        }
        $this->account->default_company_id = $this->companies[0]->id;
        $this->account->save();
    }

    private function requestBootstrap(string $endpoint, bool $is_react = false, array $parameters = [], int $token_index = 0): TestResponse
    {
        $headers = ['X-API-SECRET' => config('ninja.api_secret')];
        if ($is_react) {
            $headers['X-React'] = 'true';
        }
        $payload = [];
        if ($endpoint === 'login') {
            $payload = ['email' => $this->user->email, 'password' => 'company-scope-test'];
        } else {
            $headers['X-API-TOKEN'] = $this->tokens[$token_index];
        }

        return $this->withHeaders($headers)->postJson(
            '/api/v1/'.$endpoint.'?'.http_build_query($parameters), $payload
        );
    }

    private function assertCompanyHasNoChildren(array $company): void
    {
        $transformer = new CompanyTransformer();
        foreach (array_unique(array_merge($transformer->getDefaultIncludes(), $transformer->getAvailableIncludes())) as $include) {
            $this->assertArrayNotHasKey($include, $company);
        }
        $this->assertArrayHasKey('settings', $company);
    }

    public static function bootstrapCases(): array
    {
        $cases = [];
        foreach (['small company login' => ['login', false, false], 'mini login' => ['login', false, true],
            'react login' => ['login', true, false], 'refresh' => ['refresh', false, false],
            'react refresh' => ['refresh_react', true, false]] as $label => $arguments) {
            foreach ([true, false] as $admin_a) {
                $cases[$label.($admin_a ? ' admin A' : ' restricted A')] = [...$arguments, $admin_a];
            }
        }
        return $cases;
    }

    #[DataProvider('bootstrapCases')]
    public function testOnlyActiveCompanyChildrenAreLoadedAndReturned(string $endpoint, bool $is_react, bool $is_large, bool $admin_a): void
    {
        $this->createFixture($admin_a, $is_large);
        $retrieved = [];
        foreach ([Client::class, Invoice::class, Document::class, TaxRate::class, CompanyGateway::class] as $model_class) {
            $model_class::retrieved(function (Model $model) use (&$retrieved) {
                $retrieved[] = $model->getTable().'.'.$model->id;
            });
        }
        $response = $this->requestBootstrap($endpoint, $is_react, [
            'include' => 'user.company_users.company.invoices,user.company_user.company.clients,company.documents,account.company_users.company.company_gateways',
        ])->assertOk()->assertJsonCount(2, 'data');
        $memberships = collect($response->json('data'))->keyBy('company.id');
        foreach ($this->companies as $index => $company) {
            $this->assertSame($this->tokens[$index], $memberships[$company->hashed_id]['token']['token']);
            $this->assertArrayHasKey('company_user', $memberships[$company->hashed_id]['user']);
            $this->assertArrayNotHasKey('company_users', $memberships[$company->hashed_id]['user']);
            $this->assertArrayNotHasKey('company', $memberships[$company->hashed_id]['user']['company_user']);
            $this->assertSame($index === 0 ? $admin_a : ! $admin_a, $memberships[$company->hashed_id]['is_admin']);
        }
        $active_company = $memberships[$this->companies[0]->hashed_id]['company'];
        $this->assertContains($this->records[0]['tax_rate']->hashed_id, array_column($active_company['tax_rates'], 'id'));
        $this->assertContains($this->records[0]['document']->hashed_id, array_column($active_company['documents'], 'id'));
        $this->assertArrayNotHasKey('clients', $active_company);
        $this->assertArrayNotHasKey('invoices', $active_company);
        $this->assertTrue($active_company['is_large']);
        $this->assertTrue($memberships[$this->companies[1]->hashed_id]['company']['is_large']);
        $this->assertSame($admin_a ? [$this->records[0]['gateway']->hashed_id] : [], array_column($active_company['company_gateways'], 'id'));
        foreach (['client', 'invoice'] as $entity) {
            $record = $this->records[0][$entity];
            $this->assertNotContains($record->getTable().'.'.$record->id, $retrieved);
        }
        $this->assertCompanyHasNoChildren($memberships[$this->companies[1]->hashed_id]['company']);
        foreach ($this->records[1] as $record) {
            $this->assertNotContains($record->getTable().'.'.$record->id, $retrieved);
        }
        $this->assertStringNotContainsString('company-scope-secret-1', $response->getContent());
    }

    public static function frontends(): array
    {
        return ['flutter' => [false], 'react' => [true]];
    }

    #[DataProvider('frontends')]
    public function testLoginSelectsDefaultCompanyAndMatchingPermissions(bool $is_react): void
    {
        $this->createFixture();
        $this->account->default_company_id = $this->companies[1]->id;
        $this->account->save();
        $response = $this->requestBootstrap('login', $is_react)->assertOk();
        $memberships = collect($response->json('data'))->keyBy('company.id');
        $this->assertCompanyHasNoChildren($memberships[$this->companies[0]->hashed_id]['company']);
        $this->assertArrayHasKey('tax_rates', $memberships[$this->companies[1]->hashed_id]['company']);
        $this->assertArrayNotHasKey('invoices', $memberships[$this->companies[1]->hashed_id]['company']);
        $truth = app(TruthSource::class);
        $this->assertSame($this->companies[1]->id, $truth->getCompany()->id);
        $this->assertSame($this->companies[1]->id, $truth->getCompanyUser()->company_id);
        $this->assertFalse((bool) $truth->getCompanyUser()->is_admin);
        $this->assertSame($this->companies[1]->id, $truth->getCompanyToken()->company_id);
    }

    #[DataProvider('frontends')]
    public function testLoginFallsBackWhenDefaultMembershipIsDeleted(bool $is_react): void
    {
        $this->createFixture();
        $this->account->default_company_id = $this->companies[1]->id;
        $this->account->save();
        CompanyUser::where('user_id', $this->user->id)->where('company_id', $this->companies[1]->id)->delete();
        $response = $this->requestBootstrap('login', $is_react)->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.company.id', $this->companies[0]->hashed_id);
        $this->assertArrayHasKey('tax_rates', $response->json('data.0.company'));
        $this->assertSame($this->companies[0]->id, app(TruthSource::class)->getCompanyUser()->company_id);
    }

    #[DataProvider('frontends')]
    public function testSwitchingTokenMovesChildAccess(bool $is_react): void
    {
        $this->createFixture();
        $endpoint = $is_react ? 'refresh_react' : 'refresh';
        foreach ([0, 1] as $index) {
            $response = $this->requestBootstrap($endpoint, $is_react, token_index: $index)->assertOk();
            $memberships = collect($response->json('data'))->keyBy('company.id');
            $this->assertCompanyHasNoChildren($memberships[$this->companies[1 - $index]->hashed_id]['company']);
            $this->assertContains($this->records[$index]['tax_rate']->hashed_id,
                array_column($memberships[$this->companies[$index]->hashed_id]['company']['tax_rates'], 'id'));
            $this->assertSame($this->tokens[$index], $memberships[$this->companies[$index]->hashed_id]['token']['token']);
        }
    }

    #[DataProvider('frontends')]
    public function testCurrentCompanyOnlyStillReturnsChildrenAndToken(bool $is_react): void
    {
        $this->createFixture();
        $response = $this->requestBootstrap($is_react ? 'refresh_react' : 'refresh', $is_react, [
            'current_company' => 'true',
        ], 1)->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.company.id', $this->companies[1]->hashed_id)
            ->assertJsonPath('data.0.token.token', $this->tokens[1]);
        $this->assertArrayHasKey('tax_rates', $response->json('data.0.company'));
    }

    public function testPaginationDoesNotExpandAnInactiveCompany(): void
    {
        $this->createFixture();
        $response = $this->requestBootstrap('refresh', parameters: ['per_page' => 1, 'page' => 2])
            ->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.company.id', $this->companies[1]->hashed_id);
        $this->assertCompanyHasNoChildren($response->json('data.0.company'));
    }

    public static function companyListRequests(): array
    {
        $transformer = new CompanyTransformer();
        $all_includes = implode(',', array_unique(array_merge(
            $transformer->getDefaultIncludes(), $transformer->getAvailableIncludes()
        )));
        $cases = [];
        foreach ([true, false] as $is_admin) {
            foreach (['array', 'json'] as $serializer) {
                foreach ([
                    'defaults' => [],
                    'all includes' => ['include' => $all_includes],
                    'nested includes' => ['include' => 'users.company_users.company.invoices,account.default_company.invoices,company_user.company.clients'],
                    'first load' => ['first_load' => 'true', 'include' => $all_includes],
                    'mini first load' => ['first_load' => 'true', 'is_large' => true],
                    'pagination' => ['per_page' => 1, 'page' => 2],
                ] as $label => $parameters) {
                    $cases[($is_admin ? 'admin ' : 'restricted ').$serializer.' '.$label] = [$is_admin, $serializer, $parameters];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('companyListRequests')]
    public function testCompanyListDiscardsEveryInclude(bool $is_admin, string $serializer, array $parameters): void
    {
        $this->createFixture($is_admin, $parameters['is_large'] ?? false);
        unset($parameters['is_large']);
        $retrieved = [];
        foreach ([Client::class, Invoice::class, Document::class, TaxRate::class, CompanyGateway::class] as $model_class) {
            $model_class::retrieved(function (Model $model) use (&$retrieved) {
                $retrieved[] = $model->getTable().'.'.$model->id;
            });
        }
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->tokens[0],
        ])->getJson('/api/v1/companies?'.http_build_query($parameters + ['serializer' => $serializer]))->assertOk();
        $companies = $response->json($serializer === 'json' ? 'data.data' : 'data');
        $this->assertCount(isset($parameters['per_page']) ? 1 : 2, $companies);
        foreach ($companies as $company) {
            $this->assertArrayHasKey('id', $company);
            $this->assertCompanyHasNoChildren($serializer === 'json' ? $company['attributes'] : $company);
            $this->assertArrayNotHasKey('relationships', $company);
        }
        if ($serializer === 'json') {
            $this->assertArrayNotHasKey('included', $response->json('data'));
        }
        $this->assertSame([], $retrieved, 'Company list must not hydrate child entities.');
    }

    public static function inactiveCompanyMemberships(): array
    {
        return [
            'admin missing membership' => [true, false],
            'admin deleted membership' => [true, true],
            'restricted missing membership' => [false, false],
            'restricted deleted membership' => [false, true],
        ];
    }

    #[DataProvider('inactiveCompanyMemberships')]
    public function testCompanyListRequiresActiveMembership(bool $is_admin, bool $soft_deleted): void
    {
        $this->createFixture($is_admin);
        $membership = CompanyUser::where('user_id', $this->user->id)
            ->where('company_id', $this->companies[1]->id)->firstOrFail();

        if ($soft_deleted) {
            $membership->delete();
        } else {
            $membership->forceDelete();
        }

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->tokens[0],
        ])->getJson('/api/v1/companies?include=clients,invoices,documents')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->companies[0]->hashed_id);
        $this->assertCompanyHasNoChildren($response->json('data.0'));
    }

    public function testRefreshPreservesDateAndAssignmentConstraints(): void
    {
        $this->createFixture(false);
        $invoice = $this->records[0]['invoice'];
        $invoice->forceFill(['assigned_user_id' => $this->user->id, 'updated_at' => now()->subDays(2)])->save();
        $response = $this->requestBootstrap('refresh', parameters: ['updated_at' => now()->subDay()->timestamp])->assertOk();
        $this->assertSame([], $response->json('data.0.company.invoices'));
        $invoice->forceFill(['updated_at' => now()])->save();
        $response = $this->requestBootstrap('refresh', parameters: ['updated_at' => now()->subDay()->timestamp])->assertOk();
        $response->assertJsonPath('data.0.company.invoices.0.id', $invoice->hashed_id);
    }

    #[DataProvider('frontends')]
    public function testLoginAndRefreshShareMiniLoadRegardlessOfLegacyFlags(bool $is_react): void
    {
        $this->createFixture();
        // Even small companies with administrators everywhere require paginated loading.
        CompanyUser::where('user_id', $this->user->id)->update(['is_admin' => true]);
        $login = $this->requestBootstrap('login', $is_react, [
            'updated_at' => 0, 'first_load' => 'true', 'created_at' => now()->addDay()->timestamp,
            'include' => 'company.invoices',
        ])->assertOk();
        $refresh = $this->requestBootstrap($is_react ? 'refresh_react' : 'refresh', $is_react, [
            'first_load' => 'true', 'include' => 'company.invoices',
        ])->assertOk();

        $this->assertSame($login->json('data.0.company'), $refresh->json('data.0.company'));
        $this->assertTrue($login->json('data.0.company.is_large'));
        $this->assertArrayNotHasKey('invoices', $login->json('data.0.company'));
        $this->assertNotEmpty($login->json('data.0.company.tax_rates'));
        $this->assertNotEmpty($login->json('data.0.token.token'));
    }

    public static function deltaCases(): array
    {
        $cases = [];
        foreach (['refresh', 'refresh_react'] as $endpoint) {
            foreach ([false, true] as $is_large) {
                foreach ([false, true] as $is_admin) {
                    $cases[$endpoint.' large='.(int) $is_large.' admin='.(int) $is_admin] = [$endpoint, $is_large, $is_admin];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('deltaCases')]
    public function testDeltaIncludesOnlyPermittedCurrentCompanyRecords(string $endpoint, bool $is_large, bool $is_admin): void
    {
        $this->createFixture($is_admin, $is_large);
        $old_invoice = $this->records[0]['invoice'];
        $old_invoice->forceFill(['updated_at' => now()->subDays(2)])->save();
        $owned_invoice = Invoice::factory()->create([
            'company_id' => $this->companies[0]->id, 'client_id' => $this->records[0]['client']->id,
            'user_id' => $this->user->id, 'created_at' => now()->subDays(3),
        ]);
        $assigned_invoice = Invoice::factory()->create([
            'company_id' => $this->companies[0]->id, 'client_id' => $this->records[0]['client']->id,
            'user_id' => $this->owner->id, 'assigned_user_id' => $this->user->id,
        ]);
        $this->records[0]['owned_invoice'] = $owned_invoice;
        $this->records[0]['assigned_invoice'] = $assigned_invoice;
        $retrieved = [];
        Invoice::retrieved(function (Invoice $invoice) use (&$retrieved) {
            $retrieved[] = $invoice->id;
        });

        foreach ([now()->subDay()->timestamp, 0] as $updated_at) {
            $response = $this->requestBootstrap($endpoint, $endpoint === 'refresh_react', [
                'updated_at' => $updated_at,
            ])->assertOk()->assertJsonCount(2, 'data');
            $memberships = collect($response->json('data'))->keyBy('company.id');
            $expected_ids = [$owned_invoice->hashed_id, $assigned_invoice->hashed_id];
            if ($updated_at === 0 && $is_admin) {
                $expected_ids[] = $old_invoice->hashed_id;
            }
            $this->assertEqualsCanonicalizing($expected_ids,
                array_column($memberships[$this->companies[0]->hashed_id]['company']['invoices'], 'id'));
            $this->assertCompanyHasNoChildren($memberships[$this->companies[1]->hashed_id]['company']);
            $this->assertSame($this->tokens[1], $memberships[$this->companies[1]->hashed_id]['token']['token']);
            $this->assertNotContains($this->records[1]['invoice']->id, $retrieved);
        }
    }

    public function testFirstLoadDoesNotOverrideOrdinaryIncludes(): void
    {
        $this->createFixture();
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->tokens[0],
            'X-API-PASSWORD' => 'company-scope-test',
        ])->getJson('/api/v1/users/'.$this->user->hashed_id.'?first_load=true&include=company_user')->assertOk();
        $this->assertArrayHasKey('company_user', $response->json('data'));
        $this->assertArrayNotHasKey('company', $response->json('data'));
        $this->assertArrayNotHasKey('account', $response->json('data'));
    }

    public function testSignupReturnsMiniLoadWithoutFirstLoad(): void
    {
        $this->createFixture();
        $this->actingAs($this->user);
        // Isolate response construction from signup validation and external provisioning.
        \Mockery::mock('overload:App\Jobs\Account\CreateAccount')
            ->shouldReceive('handle')->once()->andReturn($this->account);
        $request = CreateAccountRequest::create('/api/v1/signup', 'POST');
        $this->app->instance('request', $request);

        $response = TestResponse::fromBaseResponse(app(AccountController::class)->store($request));
        $response->assertOk()->assertJsonPath('data.0.token.token', $this->tokens[0])
            ->assertJsonPath('data.0.company.is_large', true);
        $this->assertArrayHasKey('tax_rates', $response->json('data.0.company'));
        $this->assertArrayHasKey('account', $response->json('data.0'));
        $this->assertArrayNotHasKey('invoices', $response->json('data.0.company'));
        $this->assertCompanyHasNoChildren($response->json('data.1.company'));
    }

    public function testApiResponsesPassTokenUserToTransformers(): void
    {
        $this->withoutExceptionHandling();
        $this->createFixture(false);
        $client = $this->records[0]['client'];
        $client->user_id = $this->user->id;
        $client->save();

        $this->app->bind(\App\Http\Controllers\ClientController::class, function () {
            $controller = $this->app->build(\App\Http\Controllers\ClientController::class);
            $property = new \ReflectionProperty($controller, 'entity_transformer');
            $property->setValue($controller, TokenUserClientTransformer::class);

            return $controller;
        });

        foreach (['array', 'json'] as $serializer) {
            foreach (['', '/'.$client->hashed_id] as $suffix) {
                TokenUserClientTransformer::$users = [];
                $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->tokens[0],
                ])->getJson('/api/v1/clients'.$suffix.'?include=contacts&serializer='.$serializer)->assertOk();

                $this->assertNotEmpty(TokenUserClientTransformer::$users);
                foreach (TokenUserClientTransformer::$users as $user) {
                    $this->assertInstanceOf(User::class, $user);
                    $this->assertSame($this->user->id, $user->id);
                    $this->assertSame($this->companies[0]->id, $user->companyId());
                    $this->assertFalse($user->isAdmin());
                }
            }
        }
    }

    public function testCompanyTransformerRestrictionsDoNotCarryBetweenMemberships(): void
    {
        $this->createFixture();
        $transformer = new AuthenticatedCompanyUserTransformer('array', $this->user, $this->companies[0]->id);
        foreach ([1, 0, 1] as $index) {
            $membership = (new CompanyUser())->forceFill(['company_id' => $this->companies[$index]->id]);
            $membership->setRelation('company', $this->companies[$index]);
            $child = $transformer->includeCompany($membership)->getTransformer();
            $this->assertSame($index === 0 ? ['documents'] : [], $child->getDefaultIncludes());
            $this->assertSame($index === 0, in_array('invoices', $child->getAvailableIncludes(), true));
        }
        $unscoped = new AuthenticatedCompanyUserTransformer('array');
        $this->assertContains('documents', $unscoped->includeCompany($membership)->getTransformer()->getDefaultIncludes());
    }

    public function testUserItemDoesNotExposePluralMembershipTraversal(): void
    {
        $this->createFixture();
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->tokens[0],
            'X-API-PASSWORD' => 'company-scope-test',
        ])->getJson('/api/v1/users/'.$this->user->hashed_id.'?include=company_users.company.invoices')->assertOk();
        $this->assertArrayNotHasKey('company_users', $response->json('data'));
    }

    public static function userMembershipCases(): array
    {
        $cases = [];
        foreach (['restricted list' => [false, false], 'admin list' => [true, false], 'admin item' => [true, true]] as $label => $arguments) {
            foreach ([
                'plain' => 'company_user',
                'nested' => 'company_user.company.invoices',
                'multiple' => 'company_user.account,company_user.user.company_user.company.invoices,company_user.token,company_user',
            ] as $include_label => $include) {
                $cases[$label.' '.$include_label] = [...$arguments, $include];
            }
        }

        return $cases;
    }

    #[DataProvider('userMembershipCases')]
    public function testUserResponsesDoNotExpandMembershipChildren(bool $is_admin, bool $is_item, string $include): void
    {
        $this->createFixture($is_admin);
        $invoice_queries = [];
        DB::listen(function ($query) use (&$invoice_queries) {
            if (str_contains($query->sql, '`invoices`')) {
                $invoice_queries[] = $query->sql;
            }
        });

        $url = '/api/v1/users'.($is_item ? '/'.$this->user->hashed_id : '');
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->tokens[0],
            'X-API-PASSWORD' => 'company-scope-test',
        ])->getJson($url.'?'.http_build_query(['include' => $include]))->assertOk();
        $user_data = $is_item ? $response->json('data') : collect($response->json('data'))->firstWhere('id', $this->user->hashed_id);
        $membership = $user_data['company_user'];

        $this->assertSame($is_admin, $membership['is_admin']);
        $this->assertFalse($membership['is_owner']);
        $this->assertSame('', $membership['permissions']);
        $this->assertArrayHasKey('settings', $membership);
        foreach (['company', 'account', 'user', 'token'] as $child) {
            $this->assertArrayNotHasKey($child, $membership);
        }
        $this->assertSame([], $invoice_queries, 'User includes must not load nested company invoices.');
    }

    public static function ordinaryMembershipResponses(): array
    {
        $cases = [];
        foreach (['company item', 'company users', 'current company', 'membership update',
            'preferences', 'disable two factor', 'connected account', 'gmail'] as $endpoint) {
            foreach ([false, true] as $is_admin) {
                $cases[$endpoint.' '.($is_admin ? 'admin' : 'restricted')] = [$endpoint, $is_admin];
            }
        }

        return $cases;
    }

    #[DataProvider('ordinaryMembershipResponses')]
    public function testOrdinaryMembershipResponsesDoNotExpandChildren(string $endpoint, bool $is_admin): void
    {
        $this->withoutExceptionHandling();
        $this->createFixture($is_admin);
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->tokens[0],
        ]);

        if (! $is_admin) {
            $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(0, 'data');
            $this->assertFalse(auth()->user()->can('view', $this->records[0]['invoice']));
            $this->getJson('/api/v1/companies/'.$this->companies[0]->hashed_id.'?include=invoices')
                ->assertOk()->assertJsonCount(0, 'data.invoices');
        }

        if (in_array($endpoint, ['connected account', 'gmail'], true)) {
            // Exercise successful OAuth responses without contacting Google.
            $google = \Mockery::mock('overload:App\Libraries\OAuth\Providers\Google');
            $google->shouldReceive('getTokenResponse')->once()->with('membership-test-id-token')->andReturn([
                'email' => $this->user->email,
                'sub' => 'membership-test-google-user',
            ]);
            $google->shouldReceive('harvestEmail')->andReturn($this->user->email);
            $google->shouldReceive('harvestSubField')->andReturn('membership-test-google-user');
            if ($endpoint === 'gmail') {
                \Mockery::mock('alias:Google\Auth\HttpHandler\HttpHandlerFactory')
                    ->shouldReceive('build')->once()->andReturn(static fn () => new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                        'access_token' => 'membership-test-access-token', 'refresh_token' => 'membership-test-refresh-token',
                    ])));
            }
        }

        $prefix = match ($endpoint) {
            'membership update' => '',
            'company users' => 'users.company_user.',
            default => 'company_user.',
        };
        $include = implode(',', array_map(
            static fn (string $path): string => $prefix.$path,
            ['company.invoices', 'company.company_gateways', 'account', 'token', 'user.company_user.company.invoices']
        ));
        $parameters = '?'.http_build_query(['include' => $include]);
        $invoice_queries = [];
        DB::listen(function ($query) use (&$invoice_queries) {
            if (str_contains(strtolower($query->sql), 'invoices')) {
                $invoice_queries[] = $query->sql;
            }
        });

        $response = match ($endpoint) {
            'company item', 'company users' => $this->getJson('/api/v1/companies/'.$this->companies[0]->hashed_id.$parameters),
            'current company' => $this->postJson('/api/v1/companies/current'.$parameters),
            'membership update' => $this->putJson('/api/v1/company_users/'.$this->user->hashed_id.$parameters, [
                'company_user' => ['settings' => new \stdClass(), 'notifications' => new \stdClass()],
            ]),
            'preferences' => $this->putJson('/api/v1/company_users/'.$this->user->hashed_id.'/preferences'.$parameters, [
                'react_settings' => ['theme' => 'dark'],
            ]),
            'disable two factor' => $this->postJson('/api/v1/settings/disable_two_factor'.$parameters),
            'connected account', 'gmail' => $this->postJson('/api/v1/connected_account'.($endpoint === 'gmail' ? '/gmail' : '').$parameters, [
                'provider' => 'google', 'id_token' => 'membership-test-id-token', 'server_auth_code' => 'membership-test-auth-code',
            ]),
        };
        $response->assertOk();

        // Check every membership, including those nested under company users.
        $memberships = [];
        $inspect = function (array $data) use (&$inspect, &$memberships): void {
            if (array_key_exists('permissions', $data) && array_key_exists('permissions_updated_at', $data)) {
                $memberships[] = $data;
            }
            foreach ($data as $child) {
                if (is_array($child)) {
                    $inspect($child);
                }
            }
        };
        $inspect($response->json());
        $this->assertNotEmpty($memberships, 'Membership fields must remain available.');
        foreach ($memberships as $membership) {
            $this->assertArrayHasKey('is_admin', $membership);
            $this->assertArrayHasKey('settings', $membership);
            foreach (['company', 'user', 'account', 'token'] as $child) {
                $this->assertArrayNotHasKey($child, $membership);
            }
        }
        $this->assertSame([], $invoice_queries, 'Membership traversal must not query company invoices.');
        $this->assertStringNotContainsString('company-scope-secret-0', $response->getContent());
    }

    public function testNewCompanyRetainsItsTokenAndBecomesDiscoverable(): void
    {
        $this->createFixture();
        CompanyUser::where('user_id', $this->user->id)->where('company_id', $this->companies[0]->id)
            ->update(['is_owner' => true]);
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'), 'X-API-TOKEN' => $this->tokens[0],
        ])->postJson('/api/v1/companies?include=company,token,account', [
            'settings' => ['name' => 'Company scope creation test'],
        ])->assertSuccessful()->assertJsonCount(1, 'data');
        $new_company_id = $response->json('data.0.company.id');
        $new_token = $response->json('data.0.token.token');
        $this->assertNotEmpty($new_company_id);
        $this->assertNotEmpty($new_token);
        $this->assertArrayHasKey('documents', $response->json('data.0.company'));

        $discovery = $this->requestBootstrap('refresh')->assertOk()->assertJsonCount(3, 'data');
        $memberships = collect($discovery->json('data'))->keyBy('company.id');
        $this->assertSame($new_token, $memberships[$new_company_id]['token']['token']);
        $this->assertCompanyHasNoChildren($memberships[$new_company_id]['company']);

        $this->withHeaders(['X-API-TOKEN' => $new_token])->postJson('/api/v1/refresh?current_company=true')
            ->assertOk()->assertJsonPath('data.0.company.id', $new_company_id)
            ->assertJsonPath('data.0.token.token', $new_token);
    }
}

class TokenUserClientTransformer extends \App\Transformers\ClientTransformer
{
    public static array $users = [];

    public function __construct($serializer = null, ?User $user = null)
    {
        parent::__construct($serializer, $user);
        self::$users[] = $user;
    }
}
