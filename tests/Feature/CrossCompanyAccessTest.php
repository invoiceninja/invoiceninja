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

use App\Factory\CompanyGatewayFactory;
use App\Factory\CompanyUserFactory;
use App\Http\Middleware\PasswordProtection;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Design;
use App\Models\PaymentTerm;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Webhook;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Verifies that an admin from Company B cannot access
 * entities belonging to Company A via the API.
 */
class CrossCompanyAccessTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    private string $other_token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();

        // Create a completely separate account/company/user/token
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => '123',
            'email' => $this->faker->safeEmail(),
        ]);

        $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        $this->other_token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->other_token;
        $company_token->is_system = true;
        $company_token->save();
    }

    public function testCrossCompanyShowDesignDenied(): void
    {
        $design = new Design();
        $design->company_id = $this->company->id;
        $design->user_id = $this->user->id;
        $design->is_custom = true;
        $design->name = 'Test Design';
        $design->design = '{}';
        $design->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/designs/' . $this->encodePrimaryKey($design->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyShowTaxRateDenied(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/tax_rates/' . $this->encodePrimaryKey($this->tax_rate->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyShowTaskStatusDenied(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/task_statuses/' . $this->encodePrimaryKey($this->task_status->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyShowCompanyGatewayDenied(): void
    {
        $cg = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/company_gateways/' . $this->encodePrimaryKey($cg->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyEditCompanyGatewayDenied(): void
    {
        $cg = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/company_gateways/' . $this->encodePrimaryKey($cg->id) . '/edit');

        $response->assertStatus(403);
    }

    public function testCrossCompanyShowWebhookDenied(): void
    {
        $webhook = new Webhook();
        $webhook->company_id = $this->company->id;
        $webhook->user_id = $this->user->id;
        $webhook->event_id = 1;
        $webhook->target_url = 'https://example.com/hook';
        $webhook->format = 'JSON';
        $webhook->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/webhooks/' . $this->encodePrimaryKey($webhook->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyShowPaymentTermDenied(): void
    {
        $payment_term = new PaymentTerm();
        $payment_term->company_id = $this->company->id;
        $payment_term->user_id = $this->user->id;
        $payment_term->num_days = 30;
        $payment_term->name = 'Net 30';
        $payment_term->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/payment_terms/' . $this->encodePrimaryKey($payment_term->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyShowTokenDenied(): void
    {
        // Use the existing token from Company A
        $token = CompanyToken::where('company_id', $this->company->id)->first();

        $this->assertNotNull($token);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->get('/api/v1/tokens/' . $this->encodePrimaryKey($token->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyUpdateTaxRateDenied(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->put('/api/v1/tax_rates/' . $this->encodePrimaryKey($this->tax_rate->id), [
            'name' => 'Hacked',
            'rate' => 99,
        ]);

        $response->assertStatus(403);
    }

    public function testCrossCompanyDestroyTaskStatusDenied(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->delete('/api/v1/task_statuses/' . $this->encodePrimaryKey($this->task_status->id));

        $response->assertStatus(403);
    }

    public function testCrossCompanyUpdatePaymentTermDenied(): void
    {
        $payment_term = new PaymentTerm();
        $payment_term->company_id = $this->company->id;
        $payment_term->user_id = $this->user->id;
        $payment_term->num_days = 30;
        $payment_term->name = 'Net 30';
        $payment_term->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->put('/api/v1/payment_terms/' . $this->encodePrimaryKey($payment_term->id), [
            'num_days' => 999,
        ]);

        $response->assertStatus(403);
    }

    public function testCrossCompanyDestroyWebhookDenied(): void
    {
        $webhook = new Webhook();
        $webhook->company_id = $this->company->id;
        $webhook->user_id = $this->user->id;
        $webhook->event_id = 1;
        $webhook->target_url = 'https://example.com/hook';
        $webhook->format = 'JSON';
        $webhook->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->delete('/api/v1/webhooks/' . $this->encodePrimaryKey($webhook->id));

        $response->assertStatus(403);
    }

    /**
     * Verify that the owning company's admin CAN still access their own entities (no regression).
     */
    public function testSameCompanyShowTaxRateAllowed(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tax_rates/' . $this->encodePrimaryKey($this->tax_rate->id));

        $response->assertStatus(200);
    }

    public function testSameCompanyShowTaskStatusAllowed(): void
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/task_statuses/' . $this->encodePrimaryKey($this->task_status->id));

        $response->assertStatus(200);
    }

    public function testSameCompanyShowDesignAllowed(): void
    {
        $design = new Design();
        $design->company_id = $this->company->id;
        $design->user_id = $this->user->id;
        $design->is_custom = true;
        $design->name = 'Test Design';
        $design->design = '{}';
        $design->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/designs/' . $this->encodePrimaryKey($design->id));

        $response->assertStatus(200);
    }

    public function testSameCompanyShowWebhookAllowed(): void
    {
        $webhook = new Webhook();
        $webhook->company_id = $this->company->id;
        $webhook->user_id = $this->user->id;
        $webhook->event_id = 1;
        $webhook->target_url = 'https://example.com/hook';
        $webhook->format = 'JSON';
        $webhook->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/webhooks/' . $this->encodePrimaryKey($webhook->id));

        $response->assertStatus(200);
    }

    public function testSameCompanyShowPaymentTermAllowed(): void
    {
        $payment_term = new PaymentTerm();
        $payment_term->company_id = $this->company->id;
        $payment_term->user_id = $this->user->id;
        $payment_term->num_days = 30;
        $payment_term->name = 'Net 30';
        $payment_term->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payment_terms/' . $this->encodePrimaryKey($payment_term->id));

        $response->assertStatus(200);
    }

    public function testSameCompanyShowTokenAllowed(): void
    {
        $token = CompanyToken::where('company_id', $this->company->id)->first();

        $this->assertNotNull($token);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/' . $this->encodePrimaryKey($token->id));

        $response->assertStatus(200);
    }

    /**
     * Cross-company bulk action tests.
     * Verifies that Company B's admin cannot bulk-archive/delete entities from Company A.
     */

    public function testCrossCompanyBulkTokenDenied(): void
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $token = CompanyToken::where('company_id', $this->company->id)->first();
        $this->assertNotNull($token);

        $data = [
            'ids' => [$this->encodePrimaryKey($token->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/tokens/bulk', $data);

        $arr = $response->json();

        // The response should return empty data (entity filtered out by company scope)
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkTaxRateDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->tax_rate->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/tax_rates/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkTaskStatusDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task_status->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/task_statuses/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkWebhookDenied(): void
    {
        $webhook = new Webhook();
        $webhook->company_id = $this->company->id;
        $webhook->user_id = $this->user->id;
        $webhook->event_id = 1;
        $webhook->target_url = 'https://example.com/hook';
        $webhook->format = 'JSON';
        $webhook->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($webhook->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/webhooks/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkPaymentTermDenied(): void
    {
        $payment_term = new PaymentTerm();
        $payment_term->company_id = $this->company->id;
        $payment_term->user_id = $this->user->id;
        $payment_term->num_days = 30;
        $payment_term->name = 'Net 30';
        $payment_term->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($payment_term->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/payment_terms/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkVendorDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->vendor->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/vendors/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkExpenseDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        // BulkExpenseRequest validates ids belong to user's company via Rule::exists
        $response->assertStatus(422);
    }

    public function testCrossCompanyBulkTaskDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->task->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/tasks/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkProjectDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->project->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/projects/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkProductDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->product->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/products/bulk', $data);

        // BulkProductRequest validates ids belong to user's company via Rule::exists
        $response->assertStatus(422);
    }

    public function testCrossCompanyBulkSchedulerDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->scheduler->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/task_schedulers/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkRecurringExpenseDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_expense->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/recurring_expenses/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testCrossCompanyBulkPaymentDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->payment->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/payments/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    /**
     * Same-company bulk action tests (no regression).
     */

    public function testSameCompanyBulkTokenAllowed(): void
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $token = CompanyToken::where('company_id', $this->company->id)->first();

        $data = [
            'ids' => [$this->encodePrimaryKey($token->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tokens/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testSameCompanyBulkTaxRateAllowed(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->tax_rate->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tax_rates/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testSameCompanyBulkWebhookAllowed(): void
    {
        $webhook = new Webhook();
        $webhook->company_id = $this->company->id;
        $webhook->user_id = $this->user->id;
        $webhook->event_id = 1;
        $webhook->target_url = 'https://example.com/hook';
        $webhook->format = 'JSON';
        $webhook->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($webhook->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/webhooks/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testSameCompanyBulkVendorAllowed(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->vendor->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/vendors/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testCrossCompanyBulkRecurringQuoteDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_quote->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/recurring_quotes/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testSameCompanyBulkRecurringQuoteAllowed(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_quote->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_quotes/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testCrossCompanyBulkRecurringInvoiceDenied(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_invoice->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        // BulkRecurringInvoiceRequest validates ids belong to user's company via Rule::exists
        $response->assertStatus(422);
    }

    public function testSameCompanyBulkRecurringInvoiceAllowed(): void
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->recurring_invoice->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testCrossCompanyBulkDesignDenied(): void
    {
        $design = new Design();
        $design->company_id = $this->company->id;
        $design->user_id = $this->user->id;
        $design->is_custom = true;
        $design->name = 'Bulk Test Design';
        $design->design = '{}';
        $design->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($design->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/designs/bulk', $data);

        $arr = $response->json();
        $this->assertCount(0, $arr['data']);
    }

    public function testSameCompanyBulkDesignAllowed(): void
    {
        $design = new Design();
        $design->company_id = $this->company->id;
        $design->user_id = $this->user->id;
        $design->is_custom = true;
        $design->name = 'Bulk Test Design';
        $design->design = '{}';
        $design->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($design->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/designs/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testCrossCompanyBulkSubscriptionDenied(): void
    {
        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Cross Test Subscription',
        ]);

        $data = [
            'ids' => [$this->encodePrimaryKey($subscription->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/subscriptions/bulk', $data);

        // BulkSubscriptionRequest validates ids belong to user's company via Rule::exists
        $response->assertStatus(422);
    }

    public function testSameCompanyBulkSubscriptionAllowed(): void
    {
        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Same Test Subscription',
        ]);

        $data = [
            'ids' => [$this->encodePrimaryKey($subscription->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/subscriptions/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testCrossCompanyBulkCompanyGatewayDenied(): void
    {
        $cg = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($cg->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->other_token,
        ])->postJson('/api/v1/company_gateways/bulk', $data);

        // BulkCompanyGatewayRequest validates ids belong to user's company via Rule::exists
        $response->assertStatus(422);
    }

    public function testSameCompanyBulkCompanyGatewayAllowed(): void
    {
        $cg = CompanyGatewayFactory::create($this->company->id, $this->user->id);
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($cg->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/company_gateways/bulk', $data);

        $arr = $response->json();
        $this->assertCount(1, $arr['data']);
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }
}
