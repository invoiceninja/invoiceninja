<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use Illuminate\Support\Facades\Bus;
use ReflectionClass;
use Tests\TestCase;

class FrancePaymentApplicationRecorderTest extends TestCase
{
    public function testItDispatchesPaymentMovementCaptureForAReportableInvoice(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: true);
        $payment = $this->payment($company, Payment::STATUS_COMPLETED);
        $invoice = $this->invoice($company, $this->client($company, 'individual', 'FR'), Invoice::STATUS_PARTIAL, 50);
        $paymentable = $this->paymentable();

        (new FrancePaymentApplicationRecorder())->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $paymentable,
            movementAmount: '55',
            movementDate: '2026-09-15',
        );

        Bus::assertDispatched(RecordFranceEReportingPayment::class, function (RecordFranceEReportingPayment $job): bool {
            $reflection = new ReflectionClass($job);

            return $this->property($reflection, $job, 'paymentId') === 10
                && $this->property($reflection, $job, 'db') === 'db-test'
                && $this->property($reflection, $job, 'invoiceId') === 20
                && $this->property($reflection, $job, 'paymentableId') === 40
                && $this->property($reflection, $job, 'movementAmount') === '55'
                && $this->property($reflection, $job, 'movementDate') === '2026-09-15'
                && $this->property($reflection, $job, 'movementType') === FrancePaymentApplicationRecorder::MOVEMENT_APPLIED;
        });
    }

    public function testItUsesPaymentableCreatedAtAsTheApplicationMovementDate(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: true);
        $payment = $this->payment($company, Payment::STATUS_COMPLETED);
        $invoice = $this->invoice($company, $this->client($company, 'individual', 'FR'), Invoice::STATUS_PAID, 0);

        (new FrancePaymentApplicationRecorder())->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $this->paymentable(),
            movementAmount: '55',
            movementDate: '2026-09-20',
        );

        Bus::assertDispatched(RecordFranceEReportingPayment::class, function (RecordFranceEReportingPayment $job): bool {
            $reflection = new ReflectionClass($job);

            return $this->property($reflection, $job, 'movementDate') === '2026-09-15';
        });
    }


    public function testItDispatchesRefundMovementsForRefundedPayments(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: true);
        $payment = $this->payment($company, Payment::STATUS_PARTIALLY_REFUNDED);
        $invoice = $this->invoice($company, $this->client($company, 'individual', 'FR'), Invoice::STATUS_PARTIAL, 25);

        (new FrancePaymentApplicationRecorder())->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $this->paymentable(),
            movementAmount: '-25',
            movementDate: '2026-09-18',
            movementType: FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED,
        );

        Bus::assertDispatched(RecordFranceEReportingPayment::class, function (RecordFranceEReportingPayment $job): bool {
            $reflection = new ReflectionClass($job);

            return $this->property($reflection, $job, 'movementAmount') === '-25'
                && $this->property($reflection, $job, 'movementDate') === '2026-09-18'
                && $this->property($reflection, $job, 'movementType') === FrancePaymentApplicationRecorder::MOVEMENT_REFUNDED;
        });
    }

    public function testItDispatchesForDomesticFrenchBusinessInvoicesSoFranceDomainCanChooseTheReportType(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: true);
        $payment = $this->payment($company, Payment::STATUS_COMPLETED);
        $invoice = $this->invoice($company, $this->client($company, 'business', 'FR'), Invoice::STATUS_PAID, 0);

        (new FrancePaymentApplicationRecorder())->recordMovement($payment, $invoice, $this->paymentable(), '100', '2026-09-15');

        Bus::assertDispatched(RecordFranceEReportingPayment::class);
    }

    public function testItDoesNotDispatchWhenFranceReportingIsDisabled(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: false);
        $payment = $this->payment($company, Payment::STATUS_COMPLETED);
        $invoice = $this->invoice($company, $this->client($company, 'individual', 'FR'), Invoice::STATUS_PAID, 0);

        (new FrancePaymentApplicationRecorder())->recordMovement($payment, $invoice, $this->paymentable(), '100', '2026-09-15');

        Bus::assertNotDispatched(RecordFranceEReportingPayment::class);
    }

    public function testItDoesNotDispatchForPendingAppliedPayments(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: true);
        $payment = $this->payment($company, Payment::STATUS_PENDING);
        $invoice = $this->invoice($company, $this->client($company, 'individual', 'FR'), Invoice::STATUS_PAID, 0);

        (new FrancePaymentApplicationRecorder())->recordMovement($payment, $invoice, $this->paymentable(), '100', '2026-09-15');

        Bus::assertNotDispatched(RecordFranceEReportingPayment::class);
    }

    public function testItDoesNotDispatchZeroMovements(): void
    {
        Bus::fake();

        $company = $this->company(franceReportingEnabled: true);
        $payment = $this->payment($company, Payment::STATUS_COMPLETED);
        $invoice = $this->invoice($company, $this->client($company, 'individual', 'FR'), Invoice::STATUS_PAID, 0);

        (new FrancePaymentApplicationRecorder())->recordMovement($payment, $invoice, $this->paymentable(), '0', '2026-09-15');

        Bus::assertNotDispatched(RecordFranceEReportingPayment::class);
    }

    public function testItDoesNotDispatchWithoutACompanyRelation(): void
    {
        Bus::fake();

        $payment = new Payment();
        $payment->setRawAttributes([
            'id' => 10,
            'status_id' => Payment::STATUS_COMPLETED,
        ], true);

        $invoice = new Invoice();
        $invoice->setRawAttributes(['id' => 20], true);

        (new FrancePaymentApplicationRecorder())->recordMovement($payment, $invoice, null, '100', '2026-09-15');

        Bus::assertNotDispatched(RecordFranceEReportingPayment::class);
    }

    private function company(bool $franceReportingEnabled): Company
    {
        $company = new Company();
        $company->setRawAttributes([
            'id' => 1,
            'db' => 'db-test',
        ], true);

        $settings = CompanySettings::defaults();
        $settings->france_reporting_enabled = $franceReportingEnabled;

        $company->settings = $settings;

        return $company;
    }

    private function payment(Company $company, int $statusId): Payment
    {
        $payment = new Payment();
        $payment->setRawAttributes([
            'id' => 10,
            'company_id' => 1,
            'status_id' => $statusId,
            'amount' => 100,
            'applied' => 55,
            'date' => '2026-09-15',
        ], true);
        $payment->setRelation('company', $company);

        return $payment;
    }

    private function invoice(Company $company, Client $client, int $statusId, float $balance): Invoice
    {
        $invoice = new Invoice();
        $invoice->setRawAttributes([
            'id' => 20,
            'company_id' => 1,
            'client_id' => 30,
            'status_id' => $statusId,
            'balance' => $balance,
            'amount' => 100,
        ], true);
        $invoice->setRelation('company', $company);
        $invoice->setRelation('client', $client);

        return $invoice;
    }

    private function paymentable(): Paymentable
    {
        $paymentable = new Paymentable();
        $paymentable->setRawAttributes([
            'id' => 40,
            'payment_id' => 10,
            'paymentable_id' => 20,
            'paymentable_type' => 'invoices',
            'amount' => 55,
            'refunded' => 0,
            'created_at' => strtotime('2026-09-15'),
        ], true);

        return $paymentable;
    }

    private function client(Company $company, string $classification, string $countryCode): Client
    {
        $country = new Country();
        $country->setRawAttributes(['iso_3166_2' => $countryCode], true);

        $client = new Client();
        $client->setRawAttributes([
            'id' => 30,
            'company_id' => 1,
            'classification' => $classification,
        ], true);
        $client->setRelation('company', $company);
        $client->setRelation('country', $country);

        return $client;
    }

    private function property(ReflectionClass $reflection, RecordFranceEReportingPayment $job, string $property): mixed
    {
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);

        return $property->getValue($job);
    }
}
