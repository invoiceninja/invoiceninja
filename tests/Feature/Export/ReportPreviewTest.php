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
 * @test
 */
class ReportPreviewTest extends TestCase
{
    use MakesHash;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

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
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/credits?output=json', $data)
        ->assertStatus(200);


    }
}
