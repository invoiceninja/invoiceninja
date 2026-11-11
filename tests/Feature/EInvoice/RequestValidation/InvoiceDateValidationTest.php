<?php

namespace Tests\Feature\EInvoice\RequestValidation;

use App\Http\ValidationRules\EInvoice\ValidInvoiceScheme;
use App\Services\EDocument\Standards\Validation\Peppol\InvoiceLevel;
use Illuminate\Support\Facades\Validator;
use InvoiceNinja\EInvoice\EInvoice;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceDateValidationTest extends TestCase
{
    public function testRequestAcceptsNullPeriodAndDeliveryDates(): void
    {
        $validator = Validator::make(['e_invoice' => ['Invoice' => [
            'InvoicePeriod' => [['StartDate' => null, 'EndDate' => null]],
            'Delivery' => [['ActualDeliveryDate' => null]],
        ]]], ['e_invoice' => [new ValidInvoiceScheme()]]);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public function testRequestRejectsReversedPeriod(): void
    {
        $validator = Validator::make(['e_invoice' => ['Invoice' => [
            'InvoicePeriod' => [['StartDate' => '2026-10-31', 'EndDate' => '2026-10-01']],
        ]]], ['e_invoice' => [new ValidInvoiceScheme()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame(['e_invoice.InvoicePeriod.0.EndDate'], array_keys($validator->errors()->toArray()));
    }

    #[DataProvider('invalidInvoiceDates')]
    public function testInvoiceDateErrorsMatchExistingFrontendKeys(array $payload, string $expected_path, string $expected_key): void
    {
        $errors = (new EInvoice())->validatePayload($payload, InvoiceLevel::class);

        $this->assertCount(1, $errors);
        $this->assertSame($expected_path, $errors[0]->getPropertyPath());

        $validator = Validator::make(['e_invoice' => ['Invoice' => $payload]], [
            'e_invoice' => [new ValidInvoiceScheme()],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertSame([$expected_key => [$errors[0]->getMessage()]], $validator->errors()->toArray());
    }

    public static function invalidInvoiceDates(): iterable
    {
        yield 'second period reversed' => [
            ['InvoicePeriod' => [[], ['StartDate' => '2026-10-31', 'EndDate' => '2026-10-01']]],
            'InvoicePeriod[1].EndDate',
            'e_invoice.InvoicePeriod.1.EndDate',
        ];
        yield 'invalid period date' => [
            ['InvoicePeriod' => [['StartDate' => '2026-02-30']]], 'InvoicePeriod[0].StartDate',
            'e_invoice.InvoicePeriod.0.StartDate',
        ];
        yield 'invalid end date' => [
            ['InvoicePeriod' => [['EndDate' => '2026-02-30']]], 'InvoicePeriod[0].EndDate',
            'e_invoice.InvoicePeriod.0.EndDate',
        ];
        yield 'invalid first delivery date' => [
            ['Delivery' => [['ActualDeliveryDate' => '2026-02-30']]], 'Delivery[0].ActualDeliveryDate',
            'e_invoice.Invoice.Delivery.0.ActualDeliveryDate',
        ];
        yield 'invalid delivery date' => [
            ['Delivery' => [[], ['ActualDeliveryDate' => '2026-02-30']]], 'Delivery[1].ActualDeliveryDate',
            'e_invoice.Invoice.Delivery.1.ActualDeliveryDate',
        ];
    }

    public function testMultipleViolationsRetainTheirFieldKeys(): void
    {
        $period = ['StartDate' => '2026-10-31', 'EndDate' => '2026-10-01'];
        $validator = Validator::make(['e_invoice' => ['Invoice' => [
            'InvoicePeriod' => [$period, $period],
        ]]], ['e_invoice' => [new ValidInvoiceScheme()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame([
            'e_invoice.InvoicePeriod.0.EndDate',
            'e_invoice.InvoicePeriod.1.EndDate',
        ], array_keys($validator->errors()->toArray()));
    }
}
