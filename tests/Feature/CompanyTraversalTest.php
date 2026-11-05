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
use App\Models\Account;
use App\Models\Activity;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Characterizes actual company traversal; exposed cases are defects, not an intended access contract. */
class CompanyTraversalTest extends TestCase
{
    use DatabaseTransactions;

    public static function routes(): array
    {
        $cases = [];
        foreach (['', 'clients', 'invoices', 'company_gateways', 'tax_rates', 'users.company_users.company.invoices', 'account.default_company.invoices', 'company_user.company.invoices'] as $include) {
            $cases['companies '.$include] = ['GET', 'companies', $include, []];
        }
        foreach (['companies/{company_a}', 'companies/{company_b}', 'companies/{company_c}', 'companies/{company_d}'] as $route) {
            $cases[$route] = ['GET', $route, 'clients,invoices,company_gateways', []];
        }
        $cases['current company'] = ['POST', 'companies/current', 'clients,invoices,users.company_users.company.invoices', []];
        foreach (['users', 'users/{user}'] as $route) {
            foreach (['company_users.company.invoices', 'companies.invoices', 'company_user.company.invoices', 'company_user.account.default_company.invoices', 'company_user.account.company_users.company.invoices'] as $include) {
                $cases[$route.' '.$include] = ['GET', $route, $include, []];
            }
        }
        foreach (['products', 'products/{product}', 'bank_transactions', 'bank_transactions/{bank_transaction}', 'bank_transaction_rules', 'bank_transaction_rules/{bank_transaction_rule}'] as $route) {
            foreach (['company.invoices', 'company.users.company_users.company.invoices', 'company.account.default_company.invoices', 'company.company_user.company.invoices'] as $include) {
                $cases[$route.' '.$include] = ['GET', $route, $include, []];
            }
        }
        foreach (['products', 'products/{product}', 'tasks', 'tasks/{task}', 'projects', 'projects/{project}'] as $route) {
            foreach (['user.company_users.company.invoices', 'user.company_user.company.invoices'] as $include) {
                $cases[$route.' '.$include] = ['GET', $route, $include, []];
            }
        }
        foreach (['tasks', 'tasks/{task}', 'projects', 'projects/{project}'] as $route) {
            $cases[$route.' assigned user'] = ['GET', $route, 'assigned_user.company_users.company.invoices', []];
        }
        $cases['activity user'] = ['GET', 'activities', 'user.company_users.company.invoices', []];
        foreach (['bank_integrations', 'bank_integrations/{bank_integration}'] as $route) {
            foreach (['account.default_company.invoices', 'account.company_users.company.invoices', 'bank_transactions.company.users.company_users.company.invoices'] as $include) {
                $cases[$route.' '.$include] = ['GET', $route, $include, []];
            }
        }
        foreach (['company.invoices', 'user.company_users.company.invoices', 'user.company_user.company.invoices', 'account.default_company.invoices'] as $include) {
            $cases['membership '.$include] = ['PUT', 'company_users/{user}', $include, ['company_user' => ['permissions' => '']]];
        }
        $cases['preferences'] = ['PUT', 'company_users/{user}/preferences', 'company_user.company.invoices', ['react_settings' => ['theme' => 'light']]];
        $cases['account'] = ['PUT', 'accounts/{account}', 'default_company.invoices,company_users.company.invoices', ['set_react_as_default_ap' => false]];
        foreach (['company_a', 'company_b', 'company_c'] as $company) {
            $cases['default '.$company] = ['POST', 'companies/{'.$company.'}/default', 'invoices', []];
            $cases['update '.$company] = ['PUT', 'companies/{'.$company.'}', 'invoices', []];
            $cases['edit '.$company] = ['GET', 'companies/{'.$company.'}/edit', 'invoices', []];
        }
        foreach (['refresh', 'refresh_react', 'login'] as $route) {
            $cases[$route] = ['POST', $route, 'user.company_users.company.invoices,user.company_user.company.invoices,company.invoices,account.default_company.invoices', []];
        }
        foreach ($cases as &$case) {
            [$method, $route, $include] = $case;
            $status = 200;
            $returned = [];
            $loaded = [];
            if ($route === 'companies') {
                // Company discovery must never load or return child relations.
            } elseif (str_contains($route, '{company_b}') || str_contains($route, '{company_c}')) {
                $status = 401;
            } elseif (str_contains($route, '{company_d}')) {
                $status = 400;
            } elseif (in_array($route, ['products', 'tasks', 'projects', 'activities', 'bank_transactions', 'bank_transaction_rules', 'bank_integrations'], true)) {
                if (str_contains($include, 'company_users.company.invoices') && !str_starts_with($include, 'account.')) {
                    $loaded = ['b' => ['invoices'], 'c' => ['invoices']];
                } elseif ($include === 'company.account.default_company.invoices') {
                    $loaded = ['b' => ['invoices']];
                } elseif ($include === 'company.company_user.company.invoices') {
                    $status = 400;
                }
            }
            array_push($case, $status, $returned, $loaded);
        }
        unset($case);
        return $cases;
    }

    #[DataProvider('routes')]
    public function testRouteTraversal(string $method, string $route, string $include, array $payload, int $status, array $expected_returned, array $expected_loaded): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Session::start();
        $account = Account::factory()->create(['num_users' => 3]);
        $other_account = Account::factory()->create();
        $user = User::factory()->create(['account_id' => $account->id, 'email' => Str::uuid().'@example.test',
            'password' => Hash::make('traversal-test'), 'confirmation_code' => Str::random(32)]);
        $owner = User::factory()->create(['account_id' => $account->id, 'email' => Str::uuid().'@example.test']);
        $other_owner = User::factory()->create(['account_id' => $other_account->id, 'email' => Str::uuid().'@example.test']);
        $companies = [];
        $company_labels = [];
        $ids = ['{user}' => $user->hashed_id, '{account}' => $account->hashed_id];
        $records = [];
        foreach (['a', 'b', 'c', 'd'] as $label) {
            $record_owner = $label === 'd' ? $other_owner : $owner;
            $company = Company::factory()->create(['account_id' => $label === 'd' ? $other_account->id : $account->id]);
            $companies[$label] = $company;
            $company_labels[$company->id] = $label;
            $ids['{company_'.$label.'}'] = $company->hashed_id;
            $membership = CompanyUserFactory::create($record_owner->id, $company->id, $company->account_id);
            $membership->forceFill(['is_owner' => true, 'is_admin' => true, 'permissions' => ''])->save();
            if (in_array($label, ['a', 'b'], true)) {
                $membership = CompanyUserFactory::create($user->id, $company->id, $account->id);
                $membership->forceFill(['is_owner' => false, 'is_admin' => $label === 'a', 'permissions' => ''])->save();
            }
            $attributes = ['company_id' => $company->id, 'user_id' => $record_owner->id];
            $client = Client::factory()->create($attributes + ['name' => 'traversal-'.$label.'-client']);
            $invoice = Invoice::factory()->create($attributes + ['client_id' => $client->id, 'number' => 'traversal-'.$label.'-invoice']);
            $document = $company->documents()->save(Document::factory()->make($attributes + ['name' => 'traversal-'.$label.'-document']));
            $tax_rate = TaxRate::factory()->create($attributes + ['name' => 'traversal-'.$label.'-tax']);
            $gateway = CompanyGatewayFactory::create($company->id, $record_owner->id);
            $gateway->gateway_key = '3b6621f970ab18887c4f6dca78d3f8bb';
            $gateway->config = encrypt(json_encode(['apiKey' => 'traversal-'.$label.'-secret']));
            $gateway->save();
            $records = array_merge($records, [$client, $invoice, $document, $tax_rate, $gateway]);
            if ($label === 'a') {
                $entities = [];
                foreach (['product' => Product::class, 'project' => Project::class, 'task' => Task::class,
                    'bank_integration' => BankIntegration::class, 'bank_transaction' => BankTransaction::class,
                    'bank_transaction_rule' => BankTransactionRule::class] as $entity => $model_class) {
                    $entity_attributes = $attributes;
                    if (in_array($entity, ['project', 'task', 'bank_transaction_rule'], true)) {
                        $entity_attributes['client_id'] = $client->id;
                    }
                    if (in_array($entity, ['project', 'task'], true)) {
                        $entity_attributes['assigned_user_id'] = $owner->id;
                    }
                    if ($entity === 'bank_integration') {
                        $entity_attributes['account_id'] = $account->id;
                    }
                    if ($entity === 'bank_transaction') {
                        $entity_attributes['bank_integration_id'] = $entities['bank_integration']->id;
                    }
                    if ($entity === 'task') {
                        $entity_attributes['project_id'] = $entities['project']->id;
                    }
                    $record = $model_class::factory()->create($entity_attributes);
                    $entities[$entity] = $record;
                    $records[] = $record;
                    $ids['{'.$entity.'}'] = $record->hashed_id;
                }
                $activity = new Activity();
                $activity->forceFill($attributes + ['account_id' => $account->id, 'client_id' => $client->id, 'activity_type_id' => 1])->save();
                $records[] = $activity;
            }
        }
        $account->default_company_id = $companies['b']->id;
        $account->save();
        $token = new CompanyToken();
        $token->forceFill(['account_id' => $account->id, 'company_id' => $companies['a']->id, 'user_id' => $user->id,
            'is_system' => true, 'name' => 'Traversal test', 'token' => Str::random(64)])->save();
        $this->beforeApplicationDestroyed(function () use ($account, $other_account, $records) {
            foreach ([$account, $other_account] as $record) {
                $this->assertFalse(DB::table('accounts')->where('id', $record->id)->exists());
                $this->assertFalse(DB::table('companies')->where('account_id', $record->id)->exists());
            }
            foreach ($records as $record) {
                $this->assertFalse(DB::table($record->getTable())->where('id', $record->id)->exists());
            }
        });
        $retrieved = [];
        foreach ([Client::class, Invoice::class, Document::class, TaxRate::class, CompanyGateway::class] as $model_class) {
            $model_class::retrieved(function (Model $model) use (&$retrieved, $company_labels) {
                $retrieved[$company_labels[$model->company_id] ?? '?'][] = $model->getTable();
            });
        }
        $headers = ['X-API-SECRET' => config('ninja.api_secret'), 'X-API-PASSWORD' => 'traversal-test'];
        if ($route === 'login') {
            $payload += ['email' => $user->email, 'password' => 'traversal-test'];
        } else {
            $headers['X-API-TOKEN'] = $token->token;
        }
        $response = $this->withHeaders($headers)->json($method, '/api/v1/'.strtr($route, $ids).'?'.http_build_query(['include' => $include]), $payload);
        $body = $response->getContent();
        $returned = [];
        foreach (['a', 'b', 'c', 'd'] as $label) {
            foreach (['client', 'invoice', 'document', 'tax', 'secret'] as $entity) {
                if (str_contains($body, 'traversal-'.$label.'-'.$entity)) {
                    $returned[$label][] = $entity;
                }
            }
        }
        $response->assertStatus($status);
        // Login selects default B; every token-authenticated request selects A.
        $active_company = $route === 'login' ? 'b' : 'a';
        unset($returned[$active_company], $retrieved[$active_company]);
        foreach ($retrieved as &$tables) {
            $tables = array_values(array_unique($tables));
            sort($tables);
        }
        unset($tables);
        ksort($retrieved);
        $this->assertSame($expected_returned, $returned, 'Foreign-company fields returned in JSON.');
        $this->assertSame($expected_loaded, $retrieved, 'Foreign-company records retrieved during the request.');
    }
}
