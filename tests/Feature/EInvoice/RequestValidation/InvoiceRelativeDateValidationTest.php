<?php

namespace Tests\Feature\EInvoice\RequestValidation;

use App\Http\ValidationRules\EInvoice\ValidInvoiceScheme;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceRelativeDateValidationTest extends TestCase
{
    #[DataProvider('validDescriptions')]
    public function testValidRelativeDateSettingsPass(string $description): void
    {
        $validator = Validator::make(['e_invoice' => ['Invoice' => [
            'InvoicePeriod' => [['Description' => $description]],
        ]]], ['e_invoice' => [new ValidInvoiceScheme()]]);

        $this->assertTrue($validator->passes(), $validator->errors()->toJson());
    }

    public static function validDescriptions(): iterable
    {
        yield 'monthly period' => ['first day of this month|last day of this month'];
        yield 'relative dates' => ['today|tomorrow'];
        yield 'empty description' => [''];
        yield 'legacy short placeholder' => ['ab'];
    }

    #[DataProvider('invalidDescriptions')]
    public function testRelativeDateErrorsMatchExistingFrontendKeys(string $description, int $period_index, string $date_field, string $message_key): void
    {
        $periods = [['Description' => 'today|tomorrow']];
        $periods[$period_index] = ['Description' => $description];
        $validator = Validator::make(['e_invoice' => ['Invoice' => [
            'InvoicePeriod' => $periods,
        ]]], ['e_invoice' => [new ValidInvoiceScheme()]]);

        $this->assertTrue($validator->fails());
        $this->assertSame([
            "e_invoice.InvoicePeriod.Description.{$period_index}.{$date_field}" => [ctrans($message_key)],
        ], $validator->errors()->toArray());
    }

    public static function invalidDescriptions(): iterable
    {
        foreach ([
            'missing pair' => ['first day of this month', 'StartDate', 'texts.start_and_end_date_required'],
            'invalid start' => ['not-a-date|tomorrow', 'StartDate', 'texts.invalid_date_create_syntax'],
            'invalid end' => ['today|not-a-date', 'EndDate', 'texts.invalid_date_create_syntax'],
            'both invalid' => ['not-a-date|not-a-date', 'StartDate', 'texts.invalid_date_create_syntax'],
            'extra separator' => ['today|tomorrow|yesterday', 'StartDate', 'texts.start_and_end_date_required'],
        ] as $label => [$description, $date_field, $message_key]) {
            foreach ([0, 1] as $period_index) {
                yield "{$label}, period {$period_index}" => [$description, $period_index, $date_field, $message_key];
            }
        }
    }
}
