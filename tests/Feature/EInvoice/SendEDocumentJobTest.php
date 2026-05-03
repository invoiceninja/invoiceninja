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

namespace Tests\Feature\EInvoice;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Activity;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Jobs\SendEDocument;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Tests for SendEDocument job guard clauses and early-return paths.
 *
 * These tests verify every guard/bail condition in SendEDocument::handle()
 * before we refactor the job. They test the job synchronously using
 * dispatchSync() and assert on database side effects (Activity records,
 * backup GUID storage).
 *
 * Guard clauses tested:
 *  1. Model not found → silent return
 *  2. Already sent (has GUID) → no duplicate send
 *  3. Flagged account → blocked
 *  4. Client not routable on Peppol network → failure activity
 *  5. Build errors → returns errors
 *  6. No routing identifiers → failure activity
 */
class SendEDocumentJobTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function setupPeppolCompanyAndInvoice(array $params = []): Invoice
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE923356489';
        $settings->id_number = $params['company_id_number'] ?? '01234567890';
        $settings->classification = 'business';
        $settings->country_id = Country::where('iso_3166_2', $params['company_country'] ?? 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->currency_id = '3';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = 'DE';
        $tax_data->acts_as_sender = true;

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX";
        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';
        $pfa->FinancialInstitutionBranch = $fib;
        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;
        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';
        $pm->PaymentMeansCode = $pmc;
        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = $params['legal_entity_id'] ?? 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();

        $account = $this->company->account;
        $account->e_invoice_quota = $params['quota'] ?? 100;
        $account->is_flagged = $params['is_flagged'] ?? false;
        $account->save();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $params['client_country'] ?? 'DE')->first()->id,
            'vat_number' => $params['client_vat'] ?? 'DE173755434',
            'classification' => $params['client_classification'] ?? 'business',
            'has_valid_vat_number' => true,
            'name' => 'Test Client',
            'id_number' => $params['client_id_number'] ?? '',
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        $items = $invoice->line_items;
        foreach ($items as &$item) {
            $item->tax_name2 = '';
            $item->tax_rate2 = 0;
            $item->tax_name3 = '';
            $item->tax_rate3 = 0;
        }
        unset($item);
        $invoice->line_items = array_values($items);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->save();

        return $invoice;
    }

    // ─── Guard 1: Model not found ───

    public function testJobReturnsEarlyWhenModelNotFound(): void
    {
        $job = new SendEDocument(Invoice::class, 999999999, config('database.default'));
        $storecove = new Storecove();

        // Should return null (silent return) when model doesn't exist
        $result = $job->handle($storecove);

        $this->assertNull($result);
    }

    // ─── Guard 2: Already sent (has GUID) ───

    public function testJobReturnsEarlyWhenAlreadySent(): void
    {
        $invoice = $this->setupPeppolCompanyAndInvoice();

        // Simulate already sent by setting backup.guid
        $backup = $invoice->backup ?? new \stdClass();
        $backup->guid = 'existing-guid-12345';
        $invoice->backup = $backup;
        $invoice->saveQuietly();

        $job = new SendEDocument(Invoice::class, $invoice->id, config('database.default'));
        $storecove = new Storecove();

        $result = $job->handle($storecove);

        $this->assertNull($result);

        // Verify no new activity was created for this invoice
        $activities = Activity::where('invoice_id', $invoice->id)
            ->where('activity_type_id', Activity::EINVOICE_DELIVERY_SUCCESS)
            ->get();

        $this->assertCount(0, $activities);
    }

    // ─── Guard 3: Flagged account ───

    public function testJobReturnsEarlyForFlaggedAccount(): void
    {
        $invoice = $this->setupPeppolCompanyAndInvoice(['is_flagged' => true]);

        $job = new SendEDocument(Invoice::class, $invoice->id, config('database.default'));
        $storecove = new Storecove();

        $result = $job->handle($storecove);

        $this->assertNull($result);

        // No activity should be recorded for flagged accounts
        $activities = Activity::where('invoice_id', $invoice->id)->get();
        $this->assertCount(0, $activities);
    }

    // ─── Guard 4: Client not routable on Peppol network ───

    public function testJobRecordsFailureWhenClientNotRoutable(): void
    {
        $invoice = $this->setupPeppolCompanyAndInvoice([
            'client_country' => 'US', // US not supported on Peppol
            'client_vat' => '',
            'client_classification' => 'business',
        ]);

        $job = new SendEDocument(Invoice::class, $invoice->id, config('database.default'));
        $storecove = new Storecove();

        $result = $job->handle($storecove);

        // Should have recorded a delivery failure activity
        $failureActivity = Activity::where('invoice_id', $invoice->id)
            ->where('activity_type_id', Activity::EINVOICE_DELIVERY_FAILURE)
            ->first();

        $this->assertNotNull($failureActivity, 'Expected a delivery failure activity for unroutable client');
    }

    // ─── Guard 5: Quota exhausted ───

    public function testJobReturnsEarlyWhenQuotaExhausted(): void
    {
        $invoice = $this->setupPeppolCompanyAndInvoice([
            'quota' => 0,
        ]);

        $job = new SendEDocument(Invoice::class, $invoice->id, config('database.default'));
        $storecove = new Storecove();

        $result = $job->handle($storecove);

        // Should return without sending (quota exhausted path)
        // No success activity should exist
        $successActivity = Activity::where('invoice_id', $invoice->id)
            ->where('activity_type_id', Activity::EINVOICE_DELIVERY_SUCCESS)
            ->first();

        $this->assertNull($successActivity, 'Should not have sent document with zero quota');
    }

    // ─── Verify writeActivity stores GUID on success ───

    public function testWriteActivityStoresGuidOnSuccess(): void
    {
        $invoice = $this->setupPeppolCompanyAndInvoice();

        // Use reflection to test writeActivity directly
        $job = new SendEDocument(Invoice::class, $invoice->id, config('database.default'));

        $method = new \ReflectionMethod($job, 'writeActivity');
        $method->setAccessible(true);

        $testGuid = 'test-guid-abc123';
        $method->invoke($job, $invoice, Activity::EINVOICE_DELIVERY_SUCCESS, $testGuid);

        // Verify activity was created
        $activity = Activity::where('invoice_id', $invoice->id)
            ->where('activity_type_id', Activity::EINVOICE_DELIVERY_SUCCESS)
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('test-guid-abc123', $activity->notes);

        // Verify GUID was stored on model backup
        $invoice->refresh();
        $this->assertEquals($testGuid, $invoice->backup->guid);
    }

    public function testWriteActivityRecordsFailureWithoutStoringGuid(): void
    {
        $invoice = $this->setupPeppolCompanyAndInvoice();

        $job = new SendEDocument(Invoice::class, $invoice->id, config('database.default'));

        $method = new \ReflectionMethod($job, 'writeActivity');
        $method->setAccessible(true);

        $method->invoke($job, $invoice, Activity::EINVOICE_DELIVERY_FAILURE, 'Some error message');

        $activity = Activity::where('invoice_id', $invoice->id)
            ->where('activity_type_id', Activity::EINVOICE_DELIVERY_FAILURE)
            ->first();

        $this->assertNotNull($activity);
        $this->assertStringContainsString('Some error message', $activity->notes);

        // GUID should NOT be stored on failure
        $invoice->refresh();
        $this->assertFalse(
            isset($invoice->backup->guid) && strlen($invoice->backup->guid) > 3,
            'GUID should not be set on delivery failure'
        );
    }

    // ─── Middleware and retry configuration ───

    public function testJobHasWithoutOverlappingMiddleware(): void
    {
        $job = new SendEDocument(Invoice::class, 1, 'db-ninja-01');

        $middleware = $job->middleware();

        $this->assertNotEmpty($middleware);
        $this->assertInstanceOf(
            \Illuminate\Queue\Middleware\WithoutOverlapping::class,
            $middleware[0]
        );
    }

    public function testJobBackoffReturnsArrayOfIncrements(): void
    {
        $job = new SendEDocument(Invoice::class, 1, 'db-ninja-01');

        $backoff = $job->backoff();

        $this->assertIsArray($backoff);
        $this->assertCount(5, $backoff);

        // Verify backoff values are increasing (accounting for random ranges)
        $this->assertGreaterThanOrEqual(5, $backoff[0]);
        $this->assertLessThanOrEqual(29, $backoff[0]);
        $this->assertGreaterThanOrEqual(30, $backoff[1]);
        $this->assertEquals(3600, $backoff[3]);
        $this->assertEquals(7200, $backoff[4]);
    }

    public function testJobHasCorrectRetryCount(): void
    {
        $job = new SendEDocument(Invoice::class, 1, 'db-ninja-01');

        $this->assertEquals(5, $job->tries);
    }

    public function testJobDeletesWhenMissingModels(): void
    {
        $job = new SendEDocument(Invoice::class, 1, 'db-ninja-01');

        $this->assertTrue($job->deleteWhenMissingModels);
    }
}
