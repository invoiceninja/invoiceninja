<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Fixtures\Mcp;

use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

/** Synthetic business data. Credentials are random and only written to ignored local files. */
class Fixture
{
    use MakesHash;

    public function create(): array
    {
        fake()->seed(3205);
        $account = Account::factory()->create(['plan' => 'enterprise', 'plan_expires' => now()->addYear(), 'account_sms_verified' => true, 'num_users' => 10]);
        $companies = [];
        $roles = [];
        $entities = [];
        foreach (['alpha', 'beta'] as $tenant) {
            $settings = CompanySettings::defaults();
            $settings->name = 'MCP Demo ' . ucfirst($tenant);
            $settings->email = $tenant . '@example.invalid';
            $settings->country_id = '840';
            $settings->currency_id = '1';
            $company = Company::factory()->create(['account_id' => $account->id, 'settings' => $settings, 'company_key' => hash('sha256', 'mcp-demo-' . $tenant . '-' . $account->id)]);
            $companies[$tenant] = $company;
            foreach (['admin', 'viewer', 'member', 'locked'] as $role) {
                $user = User::factory()->create(['account_id' => $account->id, 'email' => $tenant . '-' . $role . '-' . Str::random(8) . '@example.invalid', 'first_name' => 'Demo', 'last_name' => $role, 'password' => bcrypt(Str::random(32))]);
                $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
                $cu->is_admin = $role === 'admin';
                $cu->is_owner = $role === 'admin';
                $cu->is_locked = $role === 'locked';
                $cu->permissions = $role === 'viewer' ? '["view_all"]' : '[]';
                $cu->save();
                $token = new CompanyToken();
                $token->forceFill(['user_id' => $user->id, 'company_id' => $company->id, 'account_id' => $account->id, 'token' => Str::random(64), 'name' => 'MCP synthetic fixture', 'is_system' => true])->save();
                $roles[$tenant][$role] = compact('user', 'cu', 'token');
            }
            $user = $roles[$tenant]['admin']['user'];
            CreateCompanyTaskStatuses::dispatchSync($company, $user);
            $base = ['company_id' => $company->id, 'user_id' => $user->id];
            $client = \App\Models\Client::factory()->create($base + ['name' => 'Demo Client ' . $tenant, 'settings' => \App\DataMapper\ClientSettings::defaults()]);
            \App\Models\ClientContact::factory()->create($base + ['client_id' => $client->id, 'email' => $tenant . '-client@example.invalid', 'first_name' => 'Demo', 'last_name' => 'Contact', 'is_primary' => true, 'send_email' => true]);
            $vendor = \App\Models\Vendor::factory()->create($base + ['name' => 'Demo Vendor ' . $tenant]);
            \App\Models\VendorContact::factory()->create($base + ['vendor_id' => $vendor->id, 'email' => $tenant . '-vendor@example.invalid', 'is_primary' => true]);
            $entities[$tenant]['clients'] = $client;
            $entities[$tenant]['vendors'] = $vendor;
            $project = \App\Models\Project::factory()->create($base + ['client_id' => $client->id, 'name' => 'Demo Project ' . $tenant]);
            $entities[$tenant]['projects'] = $project;
            $bank = \App\Models\BankIntegration::factory()->create($base + ['account_id' => $account->id]);
            $entities[$tenant]['bank_integrations'] = $bank;
            foreach (['invoices' => \App\Models\Invoice::class, 'quotes' => \App\Models\Quote::class, 'credits' => \App\Models\Credit::class, 'recurring_invoices' => \App\Models\RecurringInvoice::class, 'recurring_quotes' => \App\Models\RecurringQuote::class, 'purchase_orders' => \App\Models\PurchaseOrder::class] as $domain => $class) {
                $values = ['date' => '2026-01-15', 'due_date' => '2026-02-15', 'status_id' => 1, 'number' => 'MCP-' . $tenant . '-' . $domain, 'line_items' => [InvoiceItemFactory::create()], 'discount' => 0, 'tax_rate1' => 0, 'tax_rate2' => 0, 'tax_rate3' => 0, 'amount' => 100, 'balance' => 100];
                $values[$domain === 'purchase_orders' ? 'vendor_id' : 'client_id'] = $domain === 'purchase_orders' ? $vendor->id : $client->id;
                $entities[$tenant][$domain] = $class::factory()->create($base + $values);
            }
            foreach (['expenses' => \App\Models\Expense::class, 'recurring_expenses' => \App\Models\RecurringExpense::class, 'payments' => \App\Models\Payment::class, 'products' => \App\Models\Product::class, 'task_statuses' => \App\Models\TaskStatus::class, 'tax_rates' => \App\Models\TaxRate::class, 'expense_categories' => \App\Models\ExpenseCategory::class, 'bank_transactions' => \App\Models\BankTransaction::class, 'bank_transaction_rules' => \App\Models\BankTransactionRule::class, 'tags' => \App\Models\Tag::class, 'subscriptions' => \App\Models\Subscription::class, 'locations' => \App\Models\Location::class] as $domain => $class) {
                $extra = match ($domain) {
                    'bank_transactions' => ['bank_integration_id' => $bank->id],
                    'payments' => ['client_id' => $client->id, 'amount' => 10],
                    'expenses', 'recurring_expenses' => ['client_id' => $client->id, 'vendor_id' => $vendor->id, 'amount' => 25, 'date' => '2026-01-15'],
                    'locations' => ['client_id' => $client->id, 'custom_value1' => 'demo', 'custom_value4' => 'demo@example.invalid'],
                    'tags' => ['name' => 'Demo ' . $tenant, 'is_deleted' => false],
                    default => [],
                };
                $entities[$tenant][$domain] = $class::factory()->create($base + $extra);
            }
            $entities[$tenant]['tasks'] = \App\Models\Task::factory()->create($base + ['client_id' => $client->id, 'project_id' => $project->id, 'status_id' => $entities[$tenant]['task_statuses']->id, 'description' => 'Demo time tracking', 'time_log' => json_encode([[1768471200,1768474800]]), 'duration' => 3600]);
            $entities[$tenant]['documents'] = \App\Models\Document::factory()->create($base + ['documentable_id' => $client->id, 'documentable_type' => \App\Models\Client::class, 'disk' => 'local', 'url' => 'demo.txt', 'name' => 'demo.txt', 'type' => 'txt', 'size' => 4, 'is_public' => false]);
            $entities[$tenant]['payment_terms'] = \App\Models\PaymentTerm::forceCreate($base + ['name' => 'Demo Net 30 ' . $tenant, 'num_days' => 30]);
            $entities[$tenant]['group_settings'] = \App\Models\GroupSetting::forceCreate($base + ['name' => 'Demo Group ' . $tenant, 'settings' => \App\DataMapper\ClientSettings::defaults()]);
            $design = \App\Models\Design::first()->replicate();
            $design->forceFill($base + ['name' => 'Demo Design ' . $tenant, 'is_custom' => true])->save();
            $entities[$tenant]['designs'] = $design;
            $entities[$tenant]['webhooks'] = \App\Models\Webhook::forceCreate($base + ['target_url' => 'https://mcp-webhook.example.invalid/demo', 'event_id' => 21, 'rest_method' => 'post']);
        }
        $account->default_company_id = $companies['alpha']->id;
        $account->save();
        return compact('account', 'companies', 'roles', 'entities');
    }

    public function export(array $fixture): array
    {
        $data = ['tokens' => [], 'entities' => [], 'users' => [], 'companies' => []];
        foreach ($fixture['roles'] as $tenant => $roles) {
            foreach ($roles as $role => $models) {
                $data['tokens'][$tenant][$role] = $models['token']->token;
                $data['users'][$tenant][$role] = $models['user']->id;
            }
            $data['companies'][$tenant] = $fixture['companies'][$tenant]->id;
            foreach ($fixture['entities'][$tenant] as $domain => $entity) {
                $data['entities'][$tenant][$domain] = $entity->hashed_id;
            }
        }
        return $data;
    }
}
