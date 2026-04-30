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

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\ClientContact;
use App\Models\VendorContact;
use App\Models\RecurringInvoice;
use App\Models\RecurringExpense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\RecurringInvoiceInvitation;
use Tests\MockAccountData;
use App\Utils\Traits\MakesHash;
use App\Factory\InvoiceFactory;
use App\Factory\CreditFactory;
use App\Factory\QuoteFactory;
use App\Factory\PurchaseOrderFactory;
use App\Factory\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Tests for N+1 query detection on list endpoints.
 *
 * Each test creates N entities, measures query count, then creates
 * N more entities and measures again. If query count scales with
 * entity count, it indicates an N+1 problem.
 */
class NPlusOneListTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        Session::start();
        Model::reguard();
    }

    /**
     * Measure query count for a GET request to the given endpoint.
     * Returns [queryCount, entityCount].
     */
    private function measureQueryCount(string $url): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson($url);

        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $arr = $response->json();
        $entityCount = count($arr['data']);

        return [count($queries), $entityCount, $queries];
    }

    /**
     * Assert that query count does not scale with entity count.
     */
    private function assertNoNPlusOne(string $endpoint, string $includes, callable $factory, int $batchSize = 5): void
    {
        // Create initial batch
        for ($i = 0; $i < $batchSize; $i++) {
            $factory($i);
        }

        $url = "/api/v1/{$endpoint}?per_page=100";
        if ($includes) {
            $url .= "&include={$includes}";
        }

        [$baselineCount, $baselineEntities] = $this->measureQueryCount($url);

        // Create second batch
        for ($i = 0; $i < $batchSize; $i++) {
            $factory($batchSize + $i);
        }

        [$secondCount, $secondEntities, $secondQueries] = $this->measureQueryCount($url);

        $this->assertGreaterThan(
            $baselineEntities,
            $secondEntities,
            "Expected more entities in second request for {$endpoint}"
        );

        $queryDescriptions = array_map(fn ($q) => $q['query'], $secondQueries);

        // Allow tolerance of 2 queries for minor variations
        $this->assertLessThanOrEqual(
            $baselineCount + 2,
            $secondCount,
            "N+1 on GET /api/v1/{$endpoint}?include={$includes}: "
            . "queries grew from {$baselineCount} ({$baselineEntities} entities) "
            . "to {$secondCount} ({$secondEntities} entities).\n"
            . "Queries:\n" . implode("\n", $queryDescriptions)
        );
    }

    public function testClientListNPlusOne(): void
    {
        $this->assertNoNPlusOne('clients', 'group_settings', function ($i) {
            $client = Client::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => "N+1 Client {$i}",
            ]);

            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $client->id,
                'is_primary' => true,
            ]);
        });
    }

    public function testInvoiceListNPlusOne(): void
    {
        $contact = ClientContact::query()
            ->where('client_id', $this->client->id)
            ->first();

        $this->assertNoNPlusOne('invoices', 'client,payments', function ($i) use ($contact) {
            $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
            $invoice->client_id = $this->client->id;
            $invoice->line_items = InvoiceItemFactory::generate(1);
            $invoice->uses_inclusive_taxes = false;
            $invoice->save();

            InvoiceInvitation::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_contact_id' => $contact->id,
                'invoice_id' => $invoice->id,
            ]);
        });
    }

    public function testQuoteListNPlusOne(): void
    {
        $contact = ClientContact::query()
            ->where('client_id', $this->client->id)
            ->first();

        $this->assertNoNPlusOne('quotes', 'client', function ($i) use ($contact) {
            $quote = QuoteFactory::create($this->company->id, $this->user->id);
            $quote->client_id = $this->client->id;
            $quote->line_items = InvoiceItemFactory::generate(1);
            $quote->uses_inclusive_taxes = false;
            $quote->save();

            QuoteInvitation::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_contact_id' => $contact->id,
                'quote_id' => $quote->id,
            ]);
        });
    }

    public function testCreditListNPlusOne(): void
    {
        $contact = ClientContact::query()
            ->where('client_id', $this->client->id)
            ->first();

        $this->assertNoNPlusOne('credits', 'client', function ($i) use ($contact) {
            $credit = CreditFactory::create($this->company->id, $this->user->id);
            $credit->client_id = $this->client->id;
            $credit->line_items = InvoiceItemFactory::generate(1);
            $credit->uses_inclusive_taxes = false;
            $credit->save();

            CreditInvitation::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_contact_id' => $contact->id,
                'credit_id' => $credit->id,
            ]);
        });
    }

    public function testExpenseListNPlusOne(): void
    {
        $this->assertNoNPlusOne('expenses', 'client,vendor,category', function ($i) {
            Expense::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'vendor_id' => $this->vendor->id,
            ]);
        });
    }

    public function testPaymentListNPlusOne(): void
    {
        $this->assertNoNPlusOne('payments', 'client,invoices', function ($i) {
            Payment::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'amount' => 100,
                'applied' => 0,
            ]);
        });
    }

    public function testProjectListNPlusOne(): void
    {
        $this->assertNoNPlusOne('projects', 'user,assigned_user,client', function ($i) {
            Project::factory()->create([
                'user_id' => $this->user->id,
                'assigned_user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'name' => "N+1 Project {$i}",
            ]);
        });
    }

    public function testTaskListNPlusOne(): void
    {
        $this->assertNoNPlusOne('tasks', 'user,assigned_user,client,status,project', function ($i) {
            Task::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'project_id' => $this->project->id,
                'status_id' => $this->task_status->id,
            ]);
        });
    }

    public function testVendorListNPlusOne(): void
    {
        $this->assertNoNPlusOne('vendors', '', function ($i) {
            $vendor = Vendor::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => "N+1 Vendor {$i}",
            ]);

            VendorContact::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'vendor_id' => $vendor->id,
                'is_primary' => true,
            ]);
        });
    }

    public function testProductListNPlusOne(): void
    {
        $this->assertNoNPlusOne('products', 'user', function ($i) {
            Product::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'product_key' => "n1-product-{$i}",
            ]);
        });
    }

    public function testRecurringInvoiceListNPlusOne(): void
    {
        $contact = ClientContact::query()
            ->where('client_id', $this->client->id)
            ->first();

        $this->assertNoNPlusOne('recurring_invoices', 'client', function ($i) use ($contact) {
            $ri = RecurringInvoice::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'frequency_id' => 5,
                'line_items' => InvoiceItemFactory::generate(1),
                'uses_inclusive_taxes' => false,
            ]);

            RecurringInvoiceInvitation::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_contact_id' => $contact->id,
                'recurring_invoice_id' => $ri->id,
            ]);
        });
    }

    public function testRecurringExpenseListNPlusOne(): void
    {
        $this->assertNoNPlusOne('recurring_expenses', 'client,vendor', function ($i) {
            RecurringExpense::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'frequency_id' => 5,
                'amount' => 100,
            ]);
        });
    }

    public function testPurchaseOrderListNPlusOne(): void
    {
        $vendorContact = VendorContact::query()
            ->where('vendor_id', $this->vendor->id)
            ->first();

        $this->assertNoNPlusOne('purchase_orders', 'vendor', function ($i) use ($vendorContact) {
            $po = PurchaseOrderFactory::create($this->company->id, $this->user->id);
            $po->vendor_id = $this->vendor->id;
            $po->amount = 10;
            $po->balance = 10;
            $po->line_items = InvoiceItemFactory::generate(1);
            $po->uses_inclusive_taxes = false;
            $po->save();

            PurchaseOrderInvitation::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'vendor_contact_id' => $vendorContact->id,
                'purchase_order_id' => $po->id,
            ]);
        });
    }
}
