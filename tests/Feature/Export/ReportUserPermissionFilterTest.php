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

use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Report\ClientSalesReport;
use App\Services\Report\UserSalesReport;
use App\Utils\TruthSource;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Documents intended vs accidental behaviour of BaseExport::filterByUserPermissions()
 * for company-wide summary reports (async queue has no HTTP token / TruthSource).
 */
class ReportUserPermissionFilterTest extends TestCase
{
    use DatabaseTransactions;

    private Account $account;

    private Company $company;

    private User $admin;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $settings = CompanySettings::defaults();

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->admin = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => Str::random(32).'@gmail.com',
        ]);

        $this->staff = User::factory()->create([
            'account_id' => $this->account->id,
            'email' => Str::random(32).'@gmail.com',
        ]);

        $admin_cu = CompanyUserFactory::create($this->admin->id, $this->company->id, $this->account->id);
        $admin_cu->is_owner = true;
        $admin_cu->is_admin = true;
        $admin_cu->permissions = '[]';
        $admin_cu->save();

        $staff_cu = CompanyUserFactory::create($this->staff->id, $this->company->id, $this->account->id);
        $staff_cu->is_owner = false;
        $staff_cu->is_admin = false;
        $staff_cu->permissions = '["view_invoice","view_client","view_reports"]';
        $staff_cu->save();

        $this->createToken($this->admin, $this->company);
        $this->createToken($this->staff, $this->company);
    }

    private function createToken(User $user, Company $company): CompanyToken
    {
        $token = new CompanyToken();
        $token->user_id = $user->id;
        $token->company_id = $company->id;
        $token->account_id = $this->account->id;
        $token->name = 'test token';
        $token->token = Str::random(64);
        $token->is_system = true;
        $token->save();

        return $token;
    }

    private function filterClientsForUser(int $user_id): int
    {
        $export = new UserSalesReport($this->company, ['user_id' => $user_id]);

        $query = Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $export->filterByUserPermissions($query);

        return $query->count();
    }

    /**
     * By design: view_reports alone does not grant view_all — entity exports/reports
     * stay scoped to records the user owns or is assigned to.
     */
    public function testRestrictedUserWithViewReportsStillScopesClientList(): void
    {
        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_deleted' => 0,
        ]);

        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'is_deleted' => 0,
        ]);

        $this->assertSame(1, $this->filterClientsForUser($this->staff->id));
    }

    /**
     * By design: admins should see the full company client list when their
     * company membership is resolved correctly (TruthSource set, as in HTTP).
     */
    public function testAdminSeesAllClientsWhenTruthSourceMatchesReportCompany(): void
    {
        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_deleted' => 0,
        ]);

        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'is_deleted' => 0,
        ]);

        $admin_token = CompanyToken::where('user_id', $this->admin->id)
            ->where('company_id', $this->company->id)
            ->first();

        $truth = app(TruthSource::class);
        $truth->setCompanyToken($admin_token);
        $truth->setCompanyUser($admin_token->cu);
        $truth->setUser($this->admin);
        $truth->setCompany($this->company);

        $this->assertSame(2, $this->filterClientsForUser($this->admin->id));
    }

    /**
     * Queue / PreviewReport runs without TruthSource. isAdmin() uses User::token()
     * which returns tokens()->first(), not necessarily the report company — so an
     * admin on the report company can be treated as non-admin and scoped incorrectly.
     */
    public function testAdminCanBeScopedIncorrectlyWhenFirstTokenIsForAnotherCompany(): void
    {
        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => CompanySettings::defaults(),
        ]);

        $member_cu = CompanyUserFactory::create($this->admin->id, $other_company->id, $this->account->id);
        $member_cu->is_owner = false;
        $member_cu->is_admin = false;
        // No view_client / view_all — admin must not be treated as admin via isAdmin(), and
        // hasIntersectPermissions must not bypass filtering on this token.
        $member_cu->permissions = '[]';
        $member_cu->save();

        // First token (lower id) is non-admin on another company; admin token on report company is second.
        $this->createToken($this->admin, $other_company);
        $this->createToken($this->admin, $this->company);

        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'is_deleted' => 0,
        ]);

        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_deleted' => 0,
        ]);

        app()->forgetInstance(TruthSource::class);

        // Admin on $this->company should see 2 clients; wrong token yields staff-like scoping (1).
        $this->assertSame(1, $this->filterClientsForUser($this->admin->id));
    }

    public function testUserSalesReportAttributesInvoicesToEachUser(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_deleted' => 0,
        ]);

        foreach ([$this->admin, $this->staff] as $owner) {
            $invoice = Invoice::factory()->create([
                'client_id' => $client->id,
                'user_id' => $owner->id,
                'company_id' => $this->company->id,
                'amount' => 0,
                'balance' => 0,
                'status_id' => Invoice::STATUS_SENT,
                'total_taxes' => 0,
                'date' => now()->format('Y-m-d'),
                'discount' => 0,
                'tax_rate1' => 0,
                'tax_rate2' => 0,
                'tax_rate3' => 0,
                'tax_name1' => '',
                'tax_name2' => '',
                'tax_name3' => '',
                'uses_inclusive_taxes' => false,
                'line_items' => $this->lineItems(),
            ]);
            $invoice->calc()->getInvoice()->save();
        }

        $csv = (new UserSalesReport($this->company, [
            'date_range' => 'all',
            'report_keys' => ['name', 'invoices', 'invoice_amount', 'total_taxes'],
            'user_id' => $this->admin->id,
        ]))->run();

        $admin_name = $this->admin->present()->name();
        $staff_name = $this->staff->present()->name();

        $this->assertStringContainsString($admin_name.',1,', $csv);
        $this->assertStringContainsString($staff_name.',1,', $csv);
    }

    /**
     * Client sales applies filterByUserPermissions to the client list — same mechanism
     * as other summary reports; staff-owned clients disappear for wrongly-scoped admin runs.
     */
    public function testClientSalesReportOmitsOtherUsersClientsWhenAdminIsScopedLikeStaff(): void
    {
        $staff_client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->staff->id,
            'is_deleted' => 0,
        ]);

        $admin_client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->admin->id,
            'is_deleted' => 0,
        ]);

        foreach ([$staff_client, $admin_client] as $c) {
            $invoice = Invoice::factory()->create([
                'client_id' => $c->id,
                'user_id' => $c->user_id,
                'company_id' => $this->company->id,
                'amount' => 100,
                'balance' => 100,
                'status_id' => Invoice::STATUS_SENT,
                'total_taxes' => 0,
                'date' => now()->format('Y-m-d'),
                'discount' => 0,
                'tax_rate1' => 0,
                'tax_rate2' => 0,
                'tax_rate3' => 0,
                'tax_name1' => '',
                'tax_name2' => '',
                'tax_name3' => '',
                'uses_inclusive_taxes' => false,
                'line_items' => $this->lineItems(),
            ]);
            $invoice->calc()->getInvoice()->save();
        }

        $other_company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => CompanySettings::defaults(),
        ]);

        $member_cu = CompanyUserFactory::create($this->admin->id, $other_company->id, $this->account->id);
        $member_cu->is_admin = false;
        $member_cu->permissions = '[]';
        $member_cu->save();

        $this->createToken($this->admin, $other_company);
        $this->createToken($this->admin, $this->company);

        app()->forgetInstance(TruthSource::class);

        $csv = (new ClientSalesReport($this->company, [
            'date_range' => 'all',
            'report_keys' => [],
            'user_id' => $this->admin->id,
        ]))->run();

        $this->assertStringContainsString($admin_client->present()->name(), $csv);
        $this->assertStringNotContainsString($staff_client->present()->name(), $csv);
    }

    private function lineItems(): array
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->product_key = 'test';

        return [$item];
    }
}
