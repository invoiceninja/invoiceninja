<?php

namespace Tests\Feature\EDocument\France;

use App\DataMapper\CompanySettings;
use App\Jobs\EDocument\RecordFranceEReportingTransaction;
use App\Models\Client;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class RecordFranceEReportingTransactionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->enableFranceReporting();
    }

    public function testItRecordsAB2CFranceReportingTransaction(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame($this->company->id, $event->company_id);
        $this->assertSame($invoice->client_id, $event->client_id);
        $this->assertSame('2026-09-20', $event->period->toDateString());
        $this->assertNull($event->reporting_data);
    }

    public function testItRecordsAForeignBusinessVatExcludedFranceReportingTransaction(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'DE', classification: 'business', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('2026-10-31', $event->period->toDateString());
        $this->assertNull($event->reporting_data);
    }

    public function testItDoesNotRecordDomesticFrenchBusinessTransactions(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'business', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();

        $this->assertFalse(TransactionEvent::query()->where('invoice_id', $invoice->id)->exists());
    }

    public function testItDoesNotRecordTheSameDocumentTwice(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-15');
        $job = new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db);

        $job->handle();
        $job->handle();

        $this->assertSame(
            1,
            TransactionEvent::query()
                ->where('invoice_id', $invoice->id)
                ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
                ->count()
        );
    }

    private function enableFranceReporting(string $schedule = 'ten_days'): void
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->france_reporting_enabled = true;
        $settings->france_reporting_schedule = $schedule;

        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeInvoice(string $clientCountry, string $classification, string $date): Invoice
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $country->id,
            'classification' => $classification,
            'name' => 'France Reporting Client',
        ]);

        return Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 1200,
            'balance' => 1200,
            'paid_to_date' => 0,
        ]);
    }
}
