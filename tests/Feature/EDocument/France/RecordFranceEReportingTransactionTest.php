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
use App\Models\Product;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceEReportCompiler;
use App\Services\EDocument\Standards\France\FranceEReportVariant;
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

    public function testItDurablyQuarantinesAnUnsupportedNonEurTransaction(): void
    {
        $settings = $this->company->settings;
        $settings->currency_id = '1';
        $this->company->settings = $settings;
        $this->company->save();
        $this->company = $this->company->fresh();
        $invoice = $this->makeInvoice(clientCountry: 'FR', classification: 'individual', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
            ->firstOrFail();

        $this->assertNull($event->reporting_data);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->payment_status);
        $this->assertSame('France e-report source mapping failed.', data_get($event->payment_request, 'skip_reason'));
        $this->assertStringContainsString('Only EUR', data_get($event->payment_request, 'error.message'));
    }

    public function testItInfersAServiceB2CTransaction(): void
    {
        $invoice = $this->makeInvoice(
            clientCountry: 'FR',
            classification: 'individual',
            date: '2026-09-15',
            lineItems: [$this->makeLineItem(Product::PRODUCT_TYPE_SERVICE)],
        );

        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('invoice_id', $invoice->id)
            ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
            ->firstOrFail();

        $this->assertSame('TPS1', $event->reporting_data->frReportEntry->b2cTransaction->category);
    }

    public function testItRecordsMixedAndUnknownB2CTransactionsUsingFirstLineFallback(): void
    {
        $mixed = $this->makeInvoice(
            clientCountry: 'FR',
            classification: 'individual',
            date: '2026-09-15',
            lineItems: [
                $this->makeLineItem(Product::PRODUCT_TYPE_PHYSICAL),
                $this->makeLineItem(Product::PRODUCT_TYPE_SERVICE),
            ],
        );
        $unknown = $this->makeInvoice(
            clientCountry: 'FR',
            classification: 'individual',
            date: '2026-09-15',
            lineItems: [$this->makeLineItem(999)],
            numberSuffix: '-UNKNOWN',
        );

        (new RecordFranceEReportingTransaction(Invoice::class, $mixed->id, $this->company->db))->handle();
        (new RecordFranceEReportingTransaction(Invoice::class, $unknown->id, $this->company->db))->handle();

        $events = TransactionEvent::query()
            ->whereIn('invoice_id', [$mixed->id, $unknown->id])
            ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
            ->get();

        $this->assertCount(2, $events);
        $this->assertSame(
            ['TLB1', 'TLB1'],
            $events->map(fn (TransactionEvent $event): string => $event->reporting_data->frReportEntry->b2cTransaction->category)->all()
        );
    }

    public function testItRecordsAMixedB2CCreditUsingTheFirstLine(): void
    {
        $credit = $this->makeCredit(
            clientCountry: 'FR',
            classification: 'individual',
            date: '2026-09-15',
            lineItems: [
                $this->makeLineItem(Product::PRODUCT_TYPE_PHYSICAL),
                $this->makeLineItem(Product::PRODUCT_TYPE_SERVICE),
            ],
        );

        (new RecordFranceEReportingTransaction(Credit::class, $credit->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('credit_id', $credit->id)
            ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
            ->firstOrFail();

        $this->assertSame('TLB1', $event->reporting_data->frReportEntry->b2cTransaction->category);
    }

    public function testItRecordsAServiceB2CCreditWithNegativeAmounts(): void
    {
        $credit = $this->makeCredit(
            clientCountry: 'FR',
            classification: 'individual',
            date: '2026-09-15',
            lineItems: [$this->makeLineItem(Product::PRODUCT_TYPE_SERVICE)],
        );

        (new RecordFranceEReportingTransaction(Credit::class, $credit->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('credit_id', $credit->id)
            ->where('event_id', TransactionEvent::FR_B2C_TRANSACTION)
            ->firstOrFail();
        $transaction = $event->reporting_data->frReportEntry->b2cTransaction;

        $this->assertSame('TPS1', $transaction->category);
        $this->assertSame(-1000, $transaction->amountExcludingVat);
        $this->assertSame(-1200, $transaction->amountIncludingVat);
        $this->assertSame(1, $transaction->transactionsCount);
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
        $this->assertSame('2026-09-20', $event->period->toDateString());
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

    public function testItQuarantinesAnUnsupportedForeignBusinessCreditWithoutPoisoningThePeriod(): void
    {
        $credit = $this->makeCredit(clientCountry: 'DE', classification: 'business', date: '2026-09-15');

        (new RecordFranceEReportingTransaction(Credit::class, $credit->id, $this->company->db))->handle();

        $event = TransactionEvent::query()
            ->where('credit_id', $credit->id)
            ->where('event_id', TransactionEvent::FR_VAT_EXCLUDED_TRANSACTION)
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('2026-09-20', $event->period->toDateString());
        $this->assertNull($event->reporting_data);
        $this->assertSame(TransactionEvent::FR_REPORTING_STATUS_FAILED, $event->payment_status);
        $this->assertSame('France e-report source mapping failed.', data_get($event->payment_request, 'skip_reason'));
        $this->assertStringContainsString('Credit and rectificative', data_get($event->payment_request, 'error.message'));

        $invoice = $this->makeInvoice(clientCountry: 'DE', classification: 'business', date: '2026-09-15');
        (new RecordFranceEReportingTransaction(Invoice::class, $invoice->id, $this->company->db))->handle();
        $sources = (new FranceEReportCompiler())->sourceEventsForVariant(
            $this->company,
            FranceEReportVariant::TransactionInitial,
            '2026-09-20',
        );

        $this->assertCount(1, $sources);
        $this->assertSame((int) $invoice->id, (int) $sources->first()->invoice_id);
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

    private function enableFranceReporting(string $schedule = 'ten_day'): void
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
        $settings->email = uniqid('testuser') . '@gmail.com';

        $taxData = new TaxModel();
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->seller_subregion = 'FR';

        $this->company->settings = $settings;
        $this->company->tax_data = $taxData;
        $this->company->calculate_taxes = true;
        $this->company->save();
        $this->company = $this->company->fresh();
    }

    private function makeCredit(string $clientCountry, string $classification, string $date, ?array $lineItems = null): Credit
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();
        $client = $this->makeClient($country, $classification, $clientCountry);
        $lineItems ??= [$this->makeLineItem()];

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
            'line_items' => $lineItems,
        ]);

        $credit = $credit->calc()->getCredit();
        $credit->setRelation('client', $client);
        $credit->setRelation('company', $this->company);
        $credit->save();

        $credit->service()->createInvitations();
        $credit->load('invitations');

        return $credit;
    }

    private function makeInvoice(
        string $clientCountry,
        string $classification,
        string $date,
        ?array $lineItems = null,
        string $numberSuffix = '',
    ): Invoice
    {
        $country = Country::query()->where('iso_3166_2', $clientCountry)->firstOrFail();

        $client = $this->makeClient($country, $classification, $clientCountry);
        $lineItems ??= [$this->makeLineItem()];

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'number' => 'FR-REPORT-'.$clientCountry.'-'.$classification.$numberSuffix,
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
            'line_items' => $lineItems,
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
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        $client->setRelation('company', $this->company);
        $client->setRelation('contacts', collect([$contact]));
        $client->setRelation('country', $country);

        return $client;
    }

    private function makeLineItem(int $typeId = Product::PRODUCT_TYPE_PHYSICAL): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 500;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 20;
        $item->product_key = 'CONSULTING';
        $item->notes = 'Consulting services';
        $item->type_id = (string) $typeId;

        return $item;
    }
}
