<?php

namespace Tests\Feature\EDocument\France;

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Factory\InvoiceItemFactory;
use App\Jobs\EDocument\RecordFranceEReportingTransaction;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use Faker\Factory;
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

        $this->faker = Factory::create();
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
        $this->assertNotNull($event->reporting_data);
        $this->assertSame('2026-09-15', $event->reporting_data->frReportEntry->b2cTransaction->date);
        $this->assertSame('TLB1', $event->reporting_data->frReportEntry->b2cTransaction->category);
        $this->assertSame('EUR', $event->reporting_data->frReportEntry->b2cTransaction->currency);
        $this->assertSame(1, $event->reporting_data->frReportEntry->b2cTransaction->transactionsCount);
        $this->assertSame(1200, $event->reporting_data->frReportEntry->b2cTransaction->amountIncludingVat);
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
        $this->assertNotNull($event->reporting_data);
        $this->assertNull($event->reporting_data->frReport);
        $this->assertSame($invoice->number, $event->reporting_data->frReportEntry->b2biInvoice->invoiceNumber);
        $this->assertSame('EUR', $event->reporting_data->frReportEntry->b2biInvoice->documentCurrency);
        $this->assertSame(1200, $event->reporting_data->frReportEntry->b2biInvoice->amountIncludingVat);
        $this->assertSame('standard', $event->reporting_data->frReportEntry->b2biInvoice->taxSubtotals[0]->taxCategory);
        $this->assertArrayHasKey('amountExcludingVat', $event->reporting_data->frReportEntry->b2biInvoice->invoiceLines[0]);

        $invoice->number = 'MUTATED-AFTER-CAPTURE';
        $invoice->amount = 9999;
        $invoice->save();

        $event = $event->fresh();

        $this->assertSame('FR-REPORT-DE-business', $event->reporting_data->frReportEntry->b2biInvoice->invoiceNumber);
        $this->assertSame(1200, $event->reporting_data->frReportEntry->b2biInvoice->amountIncludingVat);
    }

    public function testItRecordsAForeignBusinessCreditAsAVatExcludedTransactionWithSnapshotData(): void
    {
        $credit = $this->makeCredit(clientCountry: 'DE', classification: 'business', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Credit::class, $credit->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('credit_id', $credit->id)
            ->where('event_id', TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('2026-10-31', $event->period->toDateString());
        $this->assertNotNull($event->reporting_data);
        $this->assertNull($event->reporting_data->frReport);
        $this->assertSame($credit->number, $event->reporting_data->frReportEntry->b2biInvoice->invoiceNumber);
        $this->assertSame('EUR', $event->reporting_data->frReportEntry->b2biInvoice->documentCurrency);
        $this->assertSame(-1200, $event->reporting_data->frReportEntry->b2biInvoice->amountIncludingVat);
        $this->assertNotEmpty($event->reporting_data->frReportEntry->b2biInvoice->invoiceLines);
        $this->assertSame('standard', $event->reporting_data->frReportEntry->b2biInvoice->taxSubtotals[0]->taxCategory);
        $this->assertSame(-1000, $event->reporting_data->frReportEntry->b2biInvoice->taxSubtotals[0]->taxableAmount);
        $this->assertSame(-200, $event->reporting_data->frReportEntry->b2biInvoice->taxSubtotals[0]->taxAmount);
        $this->assertSame(-1000, $event->reporting_data->frReportEntry->b2biInvoice->invoiceLines[0]['amountExcludingVat']);

        $credit->number = 'MUTATED-CREDIT-AFTER-CAPTURE';
        $credit->save();

        $event = $event->fresh();

        $this->assertSame('FR-CREDIT-REPORT-DE-business', $event->reporting_data->frReportEntry->b2biInvoice->invoiceNumber);
    }

    public function testItWritesAVatExcludedInvoiceReportDataArtifact(): void
    {
        $invoice = $this->makeInvoice(clientCountry: 'DE', classification: 'business', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION)
            ->firstOrFail();

        $artifact = $this->reportingDataArtifact($event);
        $artifactPath = base_path('tests/artifacts/fr_reportdata_vat_excluded_invoice_entry.json');

        $this->writeJsonArtifact($artifactPath, $artifact);

        $this->assertFileExists($artifactPath);
        $this->assertSame($artifact, json_decode(file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION, $artifact['transactionEvent']['eventId']);
        $this->assertSame('2026-10-31', $artifact['transactionEvent']['period']);
        $this->assertSame('FR-REPORT-DE-business', $artifact['reportingData']['invoiceNumber']);
        $this->assertSame(1200, $artifact['reportingData']['amountIncludingVat']);
        $this->assertArrayNotHasKey('frReportEntry', $artifact['reportingData']);
    }

    public function testItWritesAVatExcludedCreditReportDataArtifact(): void
    {
        $credit = $this->makeCredit(clientCountry: 'DE', classification: 'business', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Credit::class, $credit->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('credit_id', $credit->id)
            ->where('event_id', TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION)
            ->firstOrFail();

        $artifact = $this->reportingDataArtifact($event);
        $artifactPath = base_path('tests/artifacts/fr_reportdata_vat_excluded_credit_entry.json');

        $this->writeJsonArtifact($artifactPath, $artifact);

        $this->assertFileExists($artifactPath);
        $this->assertSame($artifact, json_decode(file_get_contents($artifactPath), true, 512, JSON_THROW_ON_ERROR));
        $this->assertSame(TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION, $artifact['transactionEvent']['eventId']);
        $this->assertSame('2026-10-31', $artifact['transactionEvent']['period']);
        $this->assertSame('FR-CREDIT-REPORT-DE-business', $artifact['reportingData']['invoiceNumber']);
        $this->assertSame(-1200, $artifact['reportingData']['amountIncludingVat']);
        $this->assertArrayNotHasKey('frReportEntry', $artifact['reportingData']);
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

    /**
     * @return array<string, mixed>
     */
    private function reportingDataArtifact(TransactionEvent $event): array
    {
        return [
            'transactionEvent' => [
                'eventId' => $event->event_id,
                'period' => $event->period?->toDateString(),
            ],
            'reportingData' => is_null($event->getRawOriginal('reporting_data'))
                ? null
                : json_decode($event->getRawOriginal('reporting_data'), true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, mixed> $artifact
     */
    private function writeJsonArtifact(string $path, array $artifact): void
    {
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
        );
    }

    private function enableFranceReporting(string $schedule = 'ten_days'): void
    {
        $france = Country::query()->where('iso_3166_2', 'FR')->firstOrFail();
        $settings = $this->company->settings ?: CompanySettings::defaults();
        $settings->country_id = (string) $france->id;
        $settings->france_reporting_enabled = true;
        $settings->france_reporting_schedule = $schedule;
        $settings->currency_id = '3';
        $settings->vat_number = 'FR12345678901';
        $settings->id_number = '12345678900012';
        $settings->e_invoice_type = 'PEPPOL';
        $settings->email = $this->faker->safeEmail();

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeCredit(string $clientCountry, string $classification, string $date): Credit
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();
        $client = $this->makeClient($country, $classification, $clientCountry);
        $item = $this->makeLineItem();

        $credit = Credit::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-CREDIT-REPORT-'.$clientCountry.'-'.$classification,
            'date' => $date,
            'due_date' => '2026-10-15',
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'status_id' => Credit::STATUS_SENT,
            'line_items' => [$item],
        ]);

        $credit = $credit->calc()->getCredit();
        $credit->setRelation('client', $client);
        $credit->setRelation('company', $this->company);
        $credit->save();

        $credit->service()->createInvitations();
        $credit->load('invitations');

        return $credit;
    }

    private function makeInvoice(string $clientCountry, string $classification, string $date): Invoice
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();

        $client = $this->makeClient($country, $classification, $clientCountry);
        $item = $this->makeLineItem();

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-REPORT-'.$clientCountry.'-'.$classification,
            'date' => $date,
            'due_date' => '2026-10-15',
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
            'status_id' => Invoice::STATUS_SENT,
            'line_items' => [$item],
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $this->company);
        $invoice->save();

        $invoice->service()->createInvitations();
        $invoice->load('invitations');

        return $invoice;
    }

    private function makeClient(Country $country, string $classification, string $clientCountry): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $country->id,
            'classification' => $classification,
            'has_valid_vat_number' => false,
            'vat_number' => $clientCountry === 'DE' ? 'DE173755434' : '',
            'name' => 'France Reporting Client',
            'address1' => '987654321',
            'address2' => 'METACORTEX',
            'city' => 'Scala Ritiro',
            'postal_code' => '98152',
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'is_primary' => true,
            'send_email' => true,
            'email' => $this->faker->safeEmail(),
        ]);

        $client->setRelation('company', $this->company);
        $client->setRelation('contacts', collect([$contact]));
        $client->setRelation('country', $country);

        return $client;
    }

    private function makeLineItem(): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 500;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 20;
        $item->product_key = 'CONSULTING';
        $item->notes = 'Consulting services';

        return $item;
    }
}
