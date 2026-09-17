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
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use League\Csv\Reader;
use Tests\TestCase;

/**
 * Coverage for tags appearing as a column in report exports, and for the
 * tag_ids filter restricting report rows.
 */
class ReportTagColumnTest extends TestCase
{
    use MakesHash;

    public $faker;

    public $company;

    public $user;

    public $account;

    public $client;

    public $token;

    public $cu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutExceptionHandling();

        config(['queue.default' => 'sync']);

        $this->buildData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }
    }

    private function buildData(): void
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => Str::random(32).'@gmail.com',
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;
        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'bob',
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'first_name' => 'john',
            'last_name' => 'doe',
            'email' => 'john@doe.com',
        ]);
    }

    private function makeTag(string $entity_type, string $name): Tag
    {
        return Tag::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'entity_type' => $entity_type,
            'name' => $name,
        ]);
    }

    private function runReport(string $endpoint, array $report_keys, array $extra = []): string
    {
        $data = array_merge([
            'date_range' => 'all',
            'report_keys' => $report_keys,
            'send_email' => false,
            'include_deleted' => false,
        ], $extra);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post($endpoint, $data);

        $response->assertStatus(200);

        return $this->poll($response->json('message'))->body();
    }

    private function poll($hash)
    {
        return Http::retry(100, 200, throw: false)
            ->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post(config('ninja.app_url')."/api/v1/exports/preview/{$hash}");
    }

    /**
     * @return array<int, array<string, string>> records keyed by header
     */
    private function records(string $csv): array
    {
        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);

        return iterator_to_array($reader->getRecords(), false);
    }

    private function tagCell(string $csv): ?string
    {
        $records = $this->records($csv);
        $first = $records[0] ?? [];

        foreach ($first as $column => $value) {
            if (stripos($column, 'tag') !== false) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Creates two tags, attaches them via the callback, runs the report and
     * asserts the tag column renders the names as a comma separated string
     * (not a raw array dump).
     */
    private function assertTagsRender(string $endpoint, array $report_keys, string $entity_type, callable $attach): void
    {
        $a = $this->makeTag($entity_type, 'aaa'.Str::random(6));
        $b = $this->makeTag($entity_type, 'bbb'.Str::random(6));

        $attach([$this->encodePrimaryKey($a->id), $this->encodePrimaryKey($b->id)]);

        $cell = $this->tagCell($this->runReport($endpoint, $report_keys));

        $this->assertSame($a->name.', '.$b->name, $cell);
    }

    public function testInvoiceReportRendersTagNames(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/invoices',
            ['invoice.number', 'invoice.tags'],
            Invoice::class,
            fn (array $ids) => $invoice->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testInvoiceItemReportRendersTagNames(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/invoice_items',
            ['invoice.number', 'invoice.tags'],
            Invoice::class,
            fn (array $ids) => $invoice->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testCreditReportRendersTagNames(): void
    {
        $credit = Credit::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/credits',
            ['credit.number', 'credit.tags'],
            Credit::class,
            fn (array $ids) => $credit->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testPaymentReportRendersTagNames(): void
    {
        $payment = Payment::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/payments',
            ['payment.number', 'payment.tags'],
            Payment::class,
            fn (array $ids) => $payment->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testClientReportRendersTagNames(): void
    {
        $this->assertTagsRender(
            '/api/v1/reports/clients',
            ['client.name', 'client.tags'],
            Client::class,
            fn (array $ids) => $this->client->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testContactReportRendersTagNames(): void
    {
        $this->assertTagsRender(
            '/api/v1/reports/contacts',
            ['contact.email', 'client.tags'],
            Client::class,
            fn (array $ids) => $this->client->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testVendorReportRendersTagNames(): void
    {
        $vendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/vendors',
            ['vendor.name', 'vendor.tags'],
            Vendor::class,
            fn (array $ids) => $vendor->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testExpenseReportRendersTagNames(): void
    {
        $expense = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/expenses',
            ['expense.amount', 'expense.tags'],
            Expense::class,
            fn (array $ids) => $expense->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testProductReportRendersTagNames(): void
    {
        $product = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/products',
            ['product.product_key', 'product.tags'],
            Product::class,
            fn (array $ids) => $product->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testPurchaseOrderReportRendersTagNames(): void
    {
        $vendor = Vendor::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $purchase_order = PurchaseOrder::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'vendor_id' => $vendor->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/purchase_orders',
            ['purchase_order.number', 'purchase_order.tags'],
            PurchaseOrder::class,
            fn (array $ids) => $purchase_order->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testTaskReportRendersTagNames(): void
    {
        $task = Task::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertTagsRender(
            '/api/v1/reports/tasks',
            ['task.number', 'task.tags'],
            Task::class,
            fn (array $ids) => $task->syncTags($ids)
        );

        $this->account->forceDelete();
    }

    public function testTagFilterRestrictsRows(): void
    {
        $tagged = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $tag = $this->makeTag(Invoice::class, 'filter'.Str::random(6));
        $tagged->syncTags([$this->encodePrimaryKey($tag->id)]);

        $csv = $this->runReport(
            '/api/v1/reports/invoices',
            ['invoice.number'],
            ['tag_ids' => $this->encodePrimaryKey($tag->id)]
        );

        $records = $this->records($csv);

        $this->assertCount(1, $records);
        $this->assertSame($tagged->number, reset($records[0]));

        $this->account->forceDelete();
    }

    public function testTagColumnNotForcedWhenNotSelected(): void
    {
        $invoice = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $tag = $this->makeTag(Invoice::class, 'hidden'.Str::random(6));
        $invoice->syncTags([$this->encodePrimaryKey($tag->id)]);

        $csv = $this->runReport(
            '/api/v1/reports/invoices',
            ['invoice.number'],
            ['tag_ids' => $this->encodePrimaryKey($tag->id)]
        );

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);

        foreach ($reader->getHeader() as $header) {
            $this->assertStringNotContainsStringIgnoringCase('tag', $header);
        }

        $this->account->forceDelete();
    }
}
