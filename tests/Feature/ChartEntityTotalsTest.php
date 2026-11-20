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

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

class ChartEntityTotalsTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const ENTITIES = ['clients', 'invoices', 'quotes', 'credits', 'payments', 'recurring_invoices', 'purchase_orders', 'expenses', 'recurring_expenses'];

    private const ARCHIVED = ['deleted_at' => '2026-06-01 00:00:00'];

    private const DELETED = ['is_deleted' => true];

    private Company $metricCompany;

    private string $metricToken;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-15 12:00:00 UTC'));
        $this->makeTestData();

        $settings = CompanySettings::defaults();
        $settings->currency_id = '1';
        $settings->timezone_id = (string) app('timezones')->firstWhere('name', 'Europe/London')->id;

        $this->metricCompany = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->metricToken = $this->companyToken($this->user, true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * entity => [included records, excluded records], each record [attributes, client attributes].
     * A purchase order's client attributes are its vendor's.
     * Every included record totals 100.
     */
    public static function entityTotals(): array
    {
        return [
            'clients' => ['clients', [
                [[], []],
                [[], []],
            ], [
                [self::ARCHIVED, []],
                [self::DELETED, []],
            ]],
            'invoices' => ['invoices', [
                [['status_id' => Invoice::STATUS_SENT], []],
                [['status_id' => Invoice::STATUS_PARTIAL, 'amount' => 150], []],
            ], [
                [['status_id' => Invoice::STATUS_DRAFT], []],
                [['status_id' => Invoice::STATUS_PAID], []],
                [['status_id' => Invoice::STATUS_CANCELLED], []],
                [['status_id' => Invoice::STATUS_REVERSED], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'quotes' => ['quotes', [
                [['status_id' => Quote::STATUS_SENT], []],
                [['status_id' => Quote::STATUS_APPROVED], []],
                [['due_date' => null], []],
                [['due_date' => '2026-06-15'], []],
            ], [
                [['status_id' => Quote::STATUS_DRAFT], []],
                [['status_id' => Quote::STATUS_CONVERTED], []],
                [['status_id' => Quote::STATUS_APPROVED, 'invoice_id' => 1], []],
                [['status_id' => Quote::STATUS_REJECTED], []],
                [['status_id' => Quote::STATUS_CANCELLED], []],
                [['due_date' => '2026-06-14'], []],
                [['status_id' => Quote::STATUS_APPROVED, 'due_date' => '2026-06-14'], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'credits' => ['credits', [
                [['status_id' => Credit::STATUS_SENT], []],
                [['status_id' => Credit::STATUS_PARTIAL, 'amount' => 150], []],
            ], [
                [['status_id' => Credit::STATUS_DRAFT], []],
                [['status_id' => Credit::STATUS_APPLIED], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'payments' => ['payments', [
                [['status_id' => Payment::STATUS_COMPLETED, 'amount' => 150, 'applied' => 50], []],
                [['status_id' => Payment::STATUS_PARTIALLY_REFUNDED, 'amount' => 150, 'refunded' => 50], []],
            ], [
                [['status_id' => Payment::STATUS_PARTIALLY_REFUNDED, 'amount' => 150, 'applied' => 100, 'refunded' => 50], []],
                [['status_id' => Payment::STATUS_PENDING], []],
                [['status_id' => Payment::STATUS_CANCELLED], []],
                [['status_id' => Payment::STATUS_FAILED], []],
                [['status_id' => Payment::STATUS_REFUNDED, 'refunded' => 100], []],
                [['applied' => 100], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'recurring_invoices' => ['recurring_invoices', [
                [[], []],
            ], [
                [['status_id' => RecurringInvoice::STATUS_DRAFT], []],
                [['status_id' => RecurringInvoice::STATUS_PAUSED], []],
                [['status_id' => RecurringInvoice::STATUS_COMPLETED], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'purchase_orders' => ['purchase_orders', [
                [['status_id' => PurchaseOrder::STATUS_SENT], []],
                [['status_id' => PurchaseOrder::STATUS_ACCEPTED], []],
            ], [
                [['status_id' => PurchaseOrder::STATUS_DRAFT], []],
                [['status_id' => PurchaseOrder::STATUS_RECEIVED], []],
                [['status_id' => PurchaseOrder::STATUS_CANCELLED], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'expenses' => ['expenses', [
                [[], []],
                [['client_id' => null], []],
                // exclusive taxes are added, as the expense lists show them
                [['uses_inclusive_taxes' => false, 'amount' => 80, 'tax_name1' => 'VAT', 'tax_rate1' => 25], []],
                [['uses_inclusive_taxes' => false, 'amount' => 90, 'calculate_tax_by_amount' => true, 'tax_amount1' => 10, 'tax_amount2' => 0, 'tax_amount3' => 0], []],
                [['uses_inclusive_taxes' => true, 'tax_name1' => 'VAT', 'tax_rate1' => 10], []],
                [['uses_inclusive_taxes' => false, 'tax_rate1' => 10], []],
            ], [
                [['should_be_invoiced' => false], []],
                [['invoice_id' => 1], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
            'recurring_expenses' => ['recurring_expenses', [
                [[], []],
                [['remaining_cycles' => 3], []],
                [['client_id' => null], []],
                // exclusive taxes are added, as the expense lists show them
                [['uses_inclusive_taxes' => false, 'amount' => 80, 'tax_name1' => 'VAT', 'tax_rate1' => 25], []],
                [['uses_inclusive_taxes' => false, 'amount' => 90, 'calculate_tax_by_amount' => true, 'tax_amount1' => 10, 'tax_amount2' => 0, 'tax_amount3' => 0], []],
                [['uses_inclusive_taxes' => true, 'tax_name1' => 'VAT', 'tax_rate1' => 10], []],
                [['uses_inclusive_taxes' => false, 'tax_rate1' => 10], []],
            ], [
                [['remaining_cycles' => 0], []],
                [['remaining_cycles' => null], []],
                [['status_id' => RecurringInvoice::STATUS_DRAFT], []],
                [['status_id' => RecurringInvoice::STATUS_PAUSED], []],
                [['status_id' => RecurringInvoice::STATUS_COMPLETED], []],
                [self::ARCHIVED, []],
                [self::DELETED, []],
                [[], self::ARCHIVED],
                [[], self::DELETED],
            ]],
        ];
    }

    #[DataProvider('entityTotals')]
    public function testEntityTotalsArePerCurrency(string $entity, array $included, array $excluded): void
    {
        foreach (array_merge($included, $excluded) as [$attributes, $client_attributes]) {
            $this->createRecord($entity, $attributes, $client_attributes, 2);
        }

        // Falls back to the company currency
        $this->createRecord($entity);

        $this->postEntityTotals($entity, $this->metricToken)
            ->assertStatus(200)
            ->assertExactJson([
                'data' => [
                    '1' => ['amount' => '100.00', 'count' => 1],
                    '2' => ['amount' => number_format(100 * count($included), 2, '.', ''), 'count' => count($included)],
                ],
            ]);
    }

    public function testPaymentTotalsLeaveOutRefundsOfTheUnappliedAmount(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
        ]);

        // 100 unapplied, 50 of it refunded
        $unapplied = $this->postPayment(['client_id' => $client->hashed_id, 'amount' => 100]);
        $this->postRefund(['id' => $unapplied, 'amount' => 50]);

        // 50 unapplied, beside an invoice refund
        $invoice = $this->createSentInvoice($client, 100);
        $applied = $this->postPayment([
            'client_id' => $client->hashed_id,
            'amount' => 150,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 100]],
        ]);
        $this->postRefund(['id' => $applied, 'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 40]]]);

        // 20 unapplied, beside an invoice refund that returns the credit first
        $credit_invoice = $this->createSentInvoice($client, 100);
        $credit = Credit::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 30,
            'balance' => 30,
        ]);
        $with_credit = $this->postPayment([
            'client_id' => $client->hashed_id,
            'amount' => 90,
            'invoices' => [['invoice_id' => $credit_invoice->hashed_id, 'amount' => 100]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 30]],
        ]);
        $this->postRefund(['id' => $with_credit, 'invoices' => [['invoice_id' => $credit_invoice->hashed_id, 'amount' => 50]]]);

        $this->postEntityTotals('payments', $this->metricToken)
            ->assertStatus(200)
            ->assertExactJson(['data' => ['1' => ['amount' => '120.00', 'count' => 3]]]);

        // deleting the invoice keeps the refund on its paymentable
        $invoice->service()->markDeleted()->save();

        $this->postEntityTotals('payments', $this->metricToken)
            ->assertStatus(200)
            ->assertExactJson(['data' => ['1' => ['amount' => '120.00', 'count' => 3]]]);
    }

    public function testRefundedPaymentWithAnUnappliedAmountIsCounted(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
        ]);

        // 120 cash and a 50 credit on a 150 invoice leaves 20 unapplied
        $invoice = $this->createSentInvoice($client, 150);
        $credit = Credit::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 50,
            'balance' => 50,
        ]);
        $payment = $this->postPayment([
            'client_id' => $client->hashed_id,
            'amount' => 120,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 150]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 50]],
        ]);

        // refunding 120 of the invoice marks the payment refunded, the 20 still unapplied
        $this->postRefund(['id' => $payment, 'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 120]]]);

        $this->assertEquals(Payment::STATUS_REFUNDED, Payment::where('company_id', $this->metricCompany->id)->first()->status_id);

        $this->postEntityTotals('payments', $this->metricToken)
            ->assertStatus(200)
            ->assertExactJson(['data' => ['1' => ['amount' => '20.00', 'count' => 1]]]);
    }

    public function testDeletingAReversalCreditRefundsTheInvoicePayment(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
        ]);

        $invoice = $this->createSentInvoice($client, 100);
        $this->postPayment([
            'client_id' => $client->hashed_id,
            'amount' => 150,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 100]],
        ]);

        $credit = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->metricToken,
        ])->postJson('/api/v1/credits', [
            'client_id' => $client->hashed_id,
            'invoice_id' => $invoice->hashed_id,
            'line_items' => [['product_key' => 'reversal', 'cost' => 100, 'quantity' => 1]],
        ])->assertStatus(200)->json('data.id');

        $this->postEntityTotals('payments', $this->metricToken)
            ->assertStatus(200)
            ->assertExactJson(['data' => ['1' => ['amount' => '50.00', 'count' => 1]]]);

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->metricToken,
        ])->postJson('/api/v1/credits/bulk', ['action' => 'delete', 'ids' => [$credit]])
            ->assertStatus(200);

        $this->assertEquals(100, Payment::where('company_id', $this->metricCompany->id)->first()->refunded);

        $this->postEntityTotals('payments', $this->metricToken)
            ->assertStatus(200)
            ->assertExactJson(['data' => ['1' => ['amount' => '50.00', 'count' => 1]]]);
    }

    public static function expenseTaxRounding(): array
    {
        // 5% + 5% of the amount, each rate rounded to the currency precision on its own, two places for a zero precision
        return [
            'two places' => [1, 10.10, '11.12'],
            'no places' => [45, 10, '11'],
            'three places' => [89, 10.01, '11.012'],
        ];
    }

    #[DataProvider('expenseTaxRounding')]
    public function testExpenseTaxesAreRoundedOneRateAtATime(int $currency_id, float $amount, string $total): void
    {
        foreach (['expenses', 'recurring_expenses'] as $entity) {
            $this->createRecord($entity, [
                'uses_inclusive_taxes' => false,
                'amount' => $amount,
                'tax_name1' => 'VAT',
                'tax_rate1' => 5,
                'tax_name2' => 'GST',
                'tax_rate2' => 5,
            ], [], $currency_id);

            $this->postEntityTotals($entity, $this->metricToken)
                ->assertStatus(200)
                ->assertExactJson(['data' => [(string) $currency_id => ['amount' => $total, 'count' => 1]]]);
        }
    }

    public function testEntityTotalsWithoutRecordsAreAnEmptyObject(): void
    {
        foreach (self::ENTITIES as $entity) {
            $response = $this->postEntityTotals($entity, $this->metricToken);

            $response->assertStatus(200);
            $this->assertSame('{"data":{}}', $response->getContent());
        }
    }

    public function testUnknownEntityIsRejected(): void
    {
        $this->postEntityTotals('tasks', $this->metricToken)
            ->assertStatus(422)
            ->assertJsonValidationErrors('entity');

        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->metricToken,
        ])->postJson('/api/v1/charts/entity_totals', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('entity');
    }

    public static function permissions(): array
    {
        return [
            'own and assigned records' => [false, 2],
            'every record' => [true, 3],
        ];
    }

    #[DataProvider('permissions')]
    public function testEntityTotalsAreScopedToTheRecordsTheUserCanList(bool $can_view_entity, int $count): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('restricted') . '@gmail.com',
        ]);

        $token = $this->companyToken($user, false);

        // clients come first, before the other entities add clients of their own
        foreach (self::ENTITIES as $entity) {
            $this->createRecord($entity, ['user_id' => $user->id], [], 2);
            $this->createRecord($entity, ['assigned_user_id' => $user->id], [], 2);
            $this->createRecord($entity, [], [], 2);

            $permissions = $can_view_entity ? ['view_' . Str::singular($entity)] : [];

            // view_client alone hides client balances
            if ($can_view_entity && $entity == 'clients') {
                $permissions[] = 'view_invoice';
            }

            CompanyUser::query()
                ->where('user_id', $user->id)
                ->where('company_id', $this->metricCompany->id)
                ->update(['permissions' => json_encode($permissions)]);

            $this->postEntityTotals($entity, $token)
                ->assertStatus(200)
                ->assertJsonPath('data.2.count', $count);
        }
    }

    public function testClientTotalsAreHiddenFromUsersWhoCannotSeeClientBalances(): void
    {
        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => uniqid('restricted') . '@gmail.com',
        ]);

        $token = $this->companyToken($user, false, '["view_client"]');

        $this->postEntityTotals('clients', $token)->assertStatus(403);
        $this->postEntityTotals('invoices', $token)->assertStatus(200);

        CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('company_id', $this->metricCompany->id)
            ->update(['permissions' => '["view_client","view_invoice"]']);

        $this->postEntityTotals('clients', $token)->assertStatus(200);
    }

    private function postEntityTotals(string $entity, string $token)
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
        ])->postJson('/api/v1/charts/entity_totals', ['entity' => $entity]);
    }

    private function createSentInvoice(Client $client, float $amount): Invoice
    {
        return Invoice::factory()->create([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => $amount,
            'balance' => $amount,
            'paid_to_date' => 0,
        ]);
    }

    private function postPayment(array $data): string
    {
        return $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->metricToken,
        ])->postJson('/api/v1/payments', array_merge(['date' => '2026-06-15'], $data))
            ->assertStatus(200)
            ->json('data.id');
    }

    private function postRefund(array $data): void
    {
        $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->metricToken,
        ])->postJson('/api/v1/payments/refund', array_merge(['date' => '2026-06-15', 'gateway_refund' => false], $data))
            ->assertStatus(200);
    }

    private function companyToken(User $user, bool $is_admin, string $permissions = ''): string
    {
        $company_user = CompanyUserFactory::create($user->id, $this->metricCompany->id, $this->account->id);
        $company_user->is_owner = false;
        $company_user->is_admin = $is_admin;
        $company_user->is_locked = false;
        $company_user->permissions = $permissions;
        $company_user->save();

        $token = Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $this->metricCompany->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'entity totals token';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        return $token;
    }

    /**
     * Creates an eligible record totalling 100 unless the attributes say otherwise.
     * A null currency leaves the client, vendor or record currency unset.
     */
    private function createRecord(string $entity, array $attributes = [], array $client_attributes = [], ?int $currency_id = null): void
    {
        $settings = ClientSettings::defaults();

        if ($currency_id) {
            $settings->currency_id = (string) $currency_id;
        }

        $client_defaults = [
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
            'settings' => $settings,
            'balance' => 100,
        ];

        if ($entity == 'clients') {
            Client::factory()->create(array_merge($client_defaults, $client_attributes, $attributes));

            return;
        }

        $client = Client::factory()->create(array_merge($client_defaults, $client_attributes));

        $attributes = array_merge([
            'company_id' => $this->metricCompany->id,
            'user_id' => $this->user->id,
        ], $attributes);

        match ($entity) {
            'invoices' => Invoice::factory()->create(array_merge([
                'client_id' => $client->id,
                'status_id' => Invoice::STATUS_SENT,
                'amount' => 100,
                'balance' => 100,
            ], $attributes)),
            'quotes' => Quote::factory()->create(array_merge([
                'client_id' => $client->id,
                'status_id' => Quote::STATUS_SENT,
                'invoice_id' => null,
                'due_date' => '2026-12-31',
                'amount' => 100,
                'balance' => 100,
            ], $attributes)),
            'credits' => Credit::factory()->create(array_merge([
                'client_id' => $client->id,
                'status_id' => Credit::STATUS_SENT,
                'amount' => 100,
                'balance' => 100,
            ], $attributes)),
            'payments' => Payment::factory()->create(array_merge([
                'client_id' => $client->id,
                'currency_id' => $currency_id ?? 1,
                'status_id' => Payment::STATUS_COMPLETED,
                'amount' => 100,
                'applied' => 0,
                'refunded' => 0,
            ], $attributes)),
            'recurring_invoices' => RecurringInvoice::factory()->create(array_merge([
                'client_id' => $client->id,
                'status_id' => RecurringInvoice::STATUS_ACTIVE,
                'amount' => 100,
                'balance' => 100,
            ], $attributes)),
            'purchase_orders' => PurchaseOrder::factory()->create(array_merge([
                'vendor_id' => Vendor::factory()->create(array_merge([
                    'company_id' => $this->metricCompany->id,
                    'user_id' => $this->user->id,
                    'currency_id' => $currency_id,
                ], $client_attributes))->id,
                'status_id' => PurchaseOrder::STATUS_SENT,
                'amount' => 100,
                'balance' => 100,
            ], $attributes)),
            'expenses' => Expense::factory()->create(array_merge([
                'client_id' => $client->id,
                'currency_id' => $currency_id,
                'should_be_invoiced' => true,
                'invoice_id' => null,
                'amount' => 100,
            ], $attributes)),
            'recurring_expenses' => RecurringExpense::factory()->create(array_merge([
                'client_id' => $client->id,
                'currency_id' => $currency_id,
                'status_id' => RecurringInvoice::STATUS_ACTIVE,
                'remaining_cycles' => -1,
                'amount' => 100,
            ], $attributes)),
        };
    }
}
