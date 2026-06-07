<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\Export;

use App\Export\CSV\ActivityExport;
use App\Export\CSV\ClientExport;
use App\Export\CSV\ContactExport;
use App\Export\CSV\CreditExport;
use App\Export\CSV\DocumentExport;
use App\Export\CSV\ExpenseExport;
use App\Export\CSV\InvoiceExport;
use App\Export\CSV\PaymentExport;
use App\Export\CSV\ProductExport;
use App\Export\CSV\PurchaseOrderExport;
use App\Export\CSV\QuoteExport;
use App\Jobs\Report\PreviewReport;
use App\Models\Client;
use App\Models\Document;
use App\Models\Expense;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 */
class ReportPreviewTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();

    }


    public function testProductJsonExport()
    {
        \App\Models\Product::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/products?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, ProductExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }


    public function testPaymentJsonExport()
    {
        \App\Models\Payment::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/payments?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, PaymentExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }


    public function testPurchaseOrderItemJsonExport()
    {
        \App\Models\PurchaseOrder::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/purchase_order_items?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, \App\Export\CSV\PurchaseOrderItemExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

        //nlog($r);

    }

    public function testQuoteItemJsonExport()
    {
        \App\Models\Quote::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/quote_items?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, \App\Export\CSV\QuoteItemExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

        //nlog($r);

    }


    public function testInvoiceItemJsonExport()
    {
        \App\Models\Invoice::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/invoice_items?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, \App\Export\CSV\InvoiceItemExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

        //nlog($r);

    }



    public function testPurchaseOrderJsonExport()
    {
        \App\Models\PurchaseOrder::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/purchase_orders?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, PurchaseOrderExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }

    public function testQuoteJsonExport()
    {
        \App\Models\Quote::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/quotes?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, QuoteExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }

    public function testInvoiceJsonExport()
    {
        \App\Models\Invoice::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/invoices?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, InvoiceExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }

    public function testInvoiceJsonPreviewReturnsAllRows(): void
    {
        $invoice_count = 5;

        \App\Models\Invoice::factory()->count($invoice_count)->create([
            "company_id" => $this->company->id,
            "user_id" => $this->user->id,
            "client_id" => $this->client->id,
            "date" => "2030-01-01",
            "due_date" => "2030-01-31",
        ]);

        $data = [
            "send_email" => false,
            "date_range" => "custom",
            "start_date" => "2030-01-01",
            "end_date" => "2030-01-02",
            "report_keys" => ["invoice.number"],
            "include_deleted" => false,
            "user_id" => $this->user->id,
            "output" => "json",
        ];

        (new PreviewReport($this->company, $data, InvoiceExport::class, "invoice_preview_full"))->handle();

        $report = Cache::pull("invoice_preview_full");

        $this->assertIsArray($report);
        $this->assertCount($invoice_count + 1, $report);
    }

    public function testInvoiceFanOutReportEagerLoadsPaymentables(): void
    {
        $invoice_count = 5;

        for ($i = 0; $i < $invoice_count; $i++) {
            $invoice = \App\Models\Invoice::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'number' => "INV-FAN-{$i}",
                'date' => '2030-02-01',
                'due_date' => '2030-02-28',
                'status_id' => \App\Models\Invoice::STATUS_SENT,
            ]);

            $payment = \App\Models\Payment::factory()->create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'number' => "PAY-FAN-{$i}",
                'amount' => 25,
                'applied' => 25,
                'refunded' => 0,
                'date' => '2030-02-01',
                'is_deleted' => 0,
            ]);

            \App\Models\Paymentable::create([
                'payment_id' => $payment->id,
                'paymentable_type' => 'invoices',
                'paymentable_id' => $invoice->id,
                'amount' => 25,
                'refunded' => 0,
                'created_at' => now()->timestamp + $i,
                'updated_at' => now()->timestamp + $i,
            ]);
        }

        $data = [
            'send_email' => false,
            'date_range' => 'custom',
            'start_date' => '2030-02-01',
            'end_date' => '2030-02-02',
            'report_keys' => ['invoice.number', 'payment.number', 'payment.amount', 'payment.applied_date'],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        $csv = (new InvoiceExport($this->company, $data))->run();

        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $reader = \League\Csv\Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $rows = iterator_to_array($reader->getRecords(), false);

        $paymentable_queries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'from `paymentables`');
        });

        $this->assertCount($invoice_count, $rows);
        $this->assertLessThanOrEqual(2, count($paymentable_queries));
    }

    public function testExpenseJsonExport()
    {
        Expense::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/expenses?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, ExpenseExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);
        //nlog($r);
    }

    public function testDocumentJsonExport()
    {
        Document::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'documentable_type' => Client::class,
            'documentable_id' => $this->client->id,
        ]);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/documents?output=json', $data)
        ->assertStatus(200);

        $p = (new PreviewReport($this->company, $data, DocumentExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);
        //nlog($r);
    }

    public function testClientExportJson()
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
                ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/clients?output=json', $data)
        ->assertStatus(200);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => ['client.name','client.balance'],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];


        $p = (new PreviewReport($this->company, $data, ClientExport::class, 'client_export1'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('client_export1');

        $this->assertNotNull($r);


    }

    public function testClientContactExportJsonLimitedKeys()
    {

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/client_contacts?output=json', $data)
        ->assertStatus(200);

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => ['client.name','client.balance','contact.email'],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $p = (new PreviewReport($this->company, $data, ContactExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }

    public function testActivityCSVExportJson()
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/activities?output=json', $data)
        ->assertStatus(200);


        $p = (new PreviewReport($this->company, $data, ActivityExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);


    }

    public function testCreditExportPreview()
    {

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $p = (new PreviewReport($this->company, $data, CreditExport::class, '123'))->handle();

        $this->assertNull($p);

        $r = Cache::pull('123');

        $this->assertNotNull($r);

    }

    public function testCreditPreview()
    {
        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/credits?output=json', $data)
        ->assertStatus(200);


    }
}
