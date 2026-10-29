<?php

namespace Tests\Unit;

use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Helpers\SwissQr\SwissQrGenerator;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use PHPUnit\Framework\Attributes\DataProvider;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Tests\TestCase;

/**
 * Regression coverage for Swiss QR output with floating-point rappen amounts.
 * All models are in memory; no database or PDF service is needed.
 */
class SwissQrRappenRoundingTest extends TestCase
{
    public static function roundingCases(): iterable
    {
        foreach ([InvoiceSum::class, InvoiceSumInclusive::class] as $calculator) {
            foreach ([
                [10.01, 10.00],
                [10.03, 10.05],
                [10.09, 10.10],
                [10.14, 10.15],
                [10.19, 10.20],
                [10.24, 10.25],
                [1.14, 1.15],
                [100.03, 100.05],
            ] as [$input, $expected]) {
                yield class_basename($calculator)." $input" => [$calculator, $input, $expected];
            }
        }
    }

    #[DataProvider('roundingCases')]
    public function testCalculatedRappenAmountsRenderQrWithCorrectAmount(string $calculator, float $input, float $expected): void
    {
        $invoice = $this->invoice($input, true);
        (new $calculator($invoice))->build();

        $this->assertEqualsWithDelta($expected, $invoice->amount, 0.000000001);
        $this->assertQrRendered($invoice, $expected);
    }

    #[DataProvider('roundingCases')]
    public function testSameInputsRenderWithRappenRoundingDisabled(string $calculator, float $input, float $expected): void
    {
        $invoice = $this->invoice($input, false);
        (new $calculator($invoice))->build();

        $this->assertEquals($input, $invoice->amount);
        $this->assertTrue(PaymentAmountInformation::create('CHF', $invoice->amount)->isValid());
        $this->assertQrRendered($invoice, $input);
    }

    public static function dueAmountCases(): iterable
    {
        foreach ([InvoiceSum::class, InvoiceSumInclusive::class] as $calculator) {
            foreach (['draft', 'sent', 'partially paid', 'partial requested'] as $path) {
                yield class_basename($calculator)." $path" => [$calculator, $path];
            }
        }
    }

    #[DataProvider('dueAmountCases')]
    public function testEachDueAmountSourceRendersDespiteFloatingPointRemainder(string $calculator, string $path): void
    {
        $invoice = $this->invoice(10.09, true);
        if ($path !== 'draft') {
            $invoice->status_id = Invoice::STATUS_SENT;
        }
        (new $calculator($invoice))->build();
        // Reproduce the original multiplication artifact independently of calculator fixes.
        $badAmount = 10.100000000000001;
        $this->assertFalse(PaymentAmountInformation::create('CHF', $badAmount)->isValid());

        // Poison only the selected source, keeping the other sources valid.
        $invoice->amount = 20.00;
        $invoice->balance = 15.00;
        $field = match ($path) {
            'draft' => 'amount',
            'partial requested' => 'partial',
            default => 'balance',
        };
        if ($path === 'partially paid') {
            $invoice->status_id = Invoice::STATUS_PARTIAL;
            $invoice->paid_to_date = 5;
        }
        $invoice->{$field} = $badAmount;
        $this->assertQrRendered($invoice, 10.10);
        $this->assertSame($badAmount, $invoice->{$field}, 'Rendering must not mutate invoice amounts.');
    }

    public function testValidPartialOverridesInvalidTotalAndBalance(): void
    {
        $invoice = $this->invoice(10.09, true);
        $invoice->status_id = Invoice::STATUS_SENT;
        (new InvoiceSum($invoice))->build();
        $this->assertQrRendered($invoice, 10.10);
        $invoice->amount = 10.100000000000001;
        $invoice->balance = 10.100000000000001;
        $invoice->partial = 5;
        $this->assertQrRendered($invoice, 5.00);
    }

    private function invoice(float $cost, bool $rappen): Invoice
    {
        $company = new Company();
        $company->settings = (object) [
            'name' => 'Swiss Test AG',
            'address1' => 'Bahnhofstrasse',
            'address2' => '1',
            'postal_code' => '8001',
            'city' => 'Zurich',
            'qr_iban' => 'CH4431999123000889012',
        ];
        $company->calculate_taxes = false;

        $client = $this->getMockBuilder(Client::class)
            ->onlyMethods(['currency', 'getSetting', 'locale'])->getMock();
        $client->method('currency')->willReturn(new Currency(['precision' => 2]));
        $client->method('getSetting')->willReturnCallback(fn ($key) => $key === 'enable_rappen_rounding' ? $rappen : null);
        $client->method('locale')->willReturn('en');
        $client->name = 'Swiss Test Client';
        $client->address1 = 'Teststrasse';
        $client->address2 = '2';
        $client->postal_code = '3000';
        $client->city = 'Bern';

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;

        $invoice = new Invoice();
        $invoice->setRelation('client', $client);
        $invoice->setRelation('company', $company);
        $invoice->forceFill([
            'number' => 'INV-123',
            'public_notes' => 'Rappen rounding diagnostic',
            'status_id' => Invoice::STATUS_DRAFT,
            'amount' => 0,
            'balance' => 0,
            'partial' => 0,
            'paid_to_date' => 0,
            'discount' => 0,
            'line_items' => [$item],
        ]);

        return $invoice;
    }

    private function render(Invoice $invoice): string
    {
        return (new SwissQrGenerator($invoice, $invoice->company))->run();
    }

    private function assertQrRendered(Invoice $invoice, float $expectedAmount): void
    {
        $html = html_entity_decode($this->render($invoice));
        $this->assertStringContainsString('<', $html);
        $this->assertStringContainsString('CHF', $html);
        $this->assertStringContainsString('data:image/', $html);

        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach (['qr-bill-amount', 'qr-bill-amount-receipt'] as $id) {
            $amount = $xpath->query('//*[@id="'.$id.'"]/p')->item(0);
            $this->assertNotNull($amount);
            $this->assertSame(number_format($expectedAmount, 2, '.', ' '), trim($amount->textContent));
        }
    }
}
