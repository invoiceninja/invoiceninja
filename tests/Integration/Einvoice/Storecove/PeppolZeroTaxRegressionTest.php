<?php

namespace Tests\Integration\Einvoice\Storecove;

use App\Models\Country;
use App\Models\Credit;
use App\Models\Product;
use App\Repositories\InvoiceRepository;
use App\Services\EDocument\Standards\Peppol;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\Einvoice\Storecove\Support\UblStorecoveTestHarness;
use Tests\MockAccountData;
use Tests\TestCase;

class PeppolZeroTaxRegressionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use UblStorecoveTestHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->bootUblStorecoveHarness();
    }

    public static function documents(): iterable
    {
        foreach (['BE', 'DE'] as $country) {
            foreach ([false, true] as $credit) {
                foreach (['zero', 'mixed', 'mixed-reversed', 'mixed-flat', 'zero-header-1', 'zero-header-2', 'zero-header-3', 'mixed-header-Z-first', 'mixed-header-E-first'] as $scenario) {
                    yield "$country " . ($credit ? 'credit' : 'invoice') . " $scenario" => [$country, $credit, $scenario];
                }
            }
        }
    }

    #[DataProvider('documents')]
    public function testZeroTaxDocumentsPassFullValidation(string $country, bool $credit, string $scenario): void
    {
        if ($country === 'BE') {
            $settings = $this->company->settings;
            $settings->country_id = Country::where('iso_3166_2', 'BE')->first()->id;
            $settings->vat_number = 'BE0477472701';
            $settings->id_number = '0477472701';
            $this->company->settings = $settings;
            $this->company->save();
        }
        $client = $this->harnessClient();
        $client->country_id = Country::where('iso_3166_2', $country)->first()->id;
        if ($country === 'BE') {
            $client->vat_number = 'BE0428649334';
            $client->id_number = '0428649334';
        }
        $client->save();

        $zero = $this->harnessLineItem('Zero', 100, 1, taxRate: 0);
        $zero->tax_id = (string) Product::PRODUCT_TYPE_ZERO_RATED;
        // No named tax is a normal representation of zero-tax lines.
        $zero->tax_name1 = '';
        $items = [$zero];
        $zeroHeader = str_starts_with($scenario, 'zero-header-');
        if ($scenario !== 'zero' && !$zeroHeader) {
            $exempt = $this->harnessLineItem('Exempt', 100, 1, taxRate: 0);
            $exempt->tax_id = (string) Product::PRODUCT_TYPE_EXEMPT;
            $exempt->tax_name1 = '';
            $items[] = $exempt;
            $items[] = $this->harnessLineItem('Standard', 100, 1, taxRate: 21);
        }
        if ($scenario === 'mixed-header-E-first') {
            [$items[0], $items[1]] = [$items[1], $items[0]];
        }
        if ($scenario === 'mixed-reversed') {
            $items = array_reverse($items);
        }
        $model = $credit ? $this->harnessCredit($client, $items) : $this->harnessInvoice($client, $items);
        $model->po_number = 'ZERO-TAX-REGRESSION';
        if ($zeroHeader) {
            $slot = (int) substr($scenario, -1);
            $model->{"tax_name{$slot}"} = 'VAT';
            $model->{"tax_rate{$slot}"} = 0;
            $model->custom_surcharge1 = 15;
            $model->custom_surcharge_tax1 = true;
        } elseif ($scenario !== 'zero') {
            $model->discount = $scenario === 'mixed-flat' ? 30 : 10;
            $model->is_amount_discount = $scenario === 'mixed-flat';
            $model->custom_surcharge1 = 15;
            $model->custom_surcharge_tax1 = true;
        }
        if (str_starts_with($scenario, 'mixed-header-')) {
            $model->tax_name1 = 'VAT';
            $model->tax_rate1 = 0;
        }
        $model = $model instanceof Credit
            ? $this->harnessSaveCredit($model)
            : (new InvoiceRepository())->save([], $model);
        $result = $this->buildUbl($model);
        $doc = $result['peppol']->getDocument();
        $breakdowns = [];
        foreach ($doc->TaxTotal[0]->TaxSubtotal as $subtotal) {
            $category = $subtotal->TaxCategory;
            $breakdowns[$category->ID->value] = (float) $subtotal->TaxableAmount->amount;
            if ($category->ID->value === 'Z') {
                $this->assertNull($category->TaxExemptionReasonCode ?? null);
                $this->assertNull($category->TaxExemptionReason ?? null);
            }
            if ($category->ID->value === 'E') {
                $this->assertNull($category->TaxExemptionReasonCode ?? null);
                $this->assertSame('Exempt from VAT', $category->TaxExemptionReason);
            }
        }
        ksort($breakdowns);
        // Document discounts are proportional; surcharge VAT follows the first
        // line, matching the application calculator's existing accounting rule.
        $expected = match ($scenario) {
            'zero' => ['Z' => 100.0],
            'zero-header-1', 'zero-header-2', 'zero-header-3' => ['Z' => 115.0],
            'mixed-reversed' => ['E' => 90.0, 'S' => 105.0, 'Z' => 90.0],
            'mixed-header-E-first' => ['E' => 105.0, 'S' => 90.0, 'Z' => 90.0],
            default => ['E' => 90.0, 'S' => 90.0, 'Z' => 105.0],
        };
        $this->assertSame($expected, $breakdowns);
        $this->assertEqualsWithDelta(abs((float) $model->amount), (float) $doc->LegalMonetaryTotal->TaxInclusiveAmount->amount, 0.01, 'UBL must agree with the saved document total');
        if ($zeroHeader) {
            $this->assertSame('115.00', number_format((float) $model->amount, 2, '.', ''));
            $this->assertSame('115.00', number_format((float) $doc->LegalMonetaryTotal->PayableAmount->amount, 2, '.', ''));
            $this->assertCount(1, $doc->AllowanceCharge);
            $this->assertSame('Z', $doc->AllowanceCharge[0]->TaxCategory[0]->ID->value);
            $this->assertEquals(15, $doc->AllowanceCharge[0]->Amount->amount);
        }
        $this->assertUblPassesSchematron($result['xml']);
    }

    public function testDomesticZeroRateOverrideIsZeroRated(): void
    {
        $client = $this->harnessClient();
        $supplied = $this->harnessLineItem('Abonnementen', 82.6, 1, taxRate: 0);
        $supplied->tax_id = (string) Product::PRODUCT_TYPE_ZERO_RATED;
        $supplied->tax_name1 = 'BTW';

        $applied = $this->harnessLineItem('', 1899.8, 0, taxRate: 0);
        $applied->tax_id = (string) Product::PRODUCT_TYPE_OVERRIDE_TAX;
        $applied->tax_name1 = 'Vrijgesteld van belastingen';
        $applied->line_total = 0;

        $credit = $this->harnessCredit($client, [$supplied, $applied]);
        $result = $this->buildUbl($credit);

        $lines = $result['peppol']->getDocument()->CreditNoteLine;
        $this->assertCount(2, $lines);
        $this->assertSame('Z', $lines[1]->Item->ClassifiedTaxCategory[0]->ID->value);
        $this->assertUblPassesSchematron($result['xml']);
    }

    public function testUnclassifiedDomesticZeroTaxIsRejected(): void
    {
        $client = $this->harnessClient();
        $item = $this->harnessLineItem('Unclassified', 100, 1, taxRate: 0);
        $item->tax_id = '';
        $model = $this->harnessInvoice($client, [$item]);
        $peppol = (new Peppol($model))->run();
        $this->assertStringContainsString('Select a tax category', implode('; ', $peppol->getErrors()));
    }

    public function testLegacyDomesticZeroTaxIsTreatedAsExempt(): void
    {
        $client = $this->harnessClient();

        foreach ([Product::PRODUCT_TYPE_PHYSICAL, Product::PRODUCT_TYPE_SERVICE] as $tax_id) {
            foreach ([false, true] as $is_credit) {
                $item = $this->harnessLineItem('Legacy zero tax', 100, 1, taxRate: 0);
                $item->tax_id = (string) $tax_id;

                $model = $is_credit
                    ? $this->harnessCredit($client, [$item])
                    : $this->harnessInvoice($client, [$item]);
                $model->po_number = 'LEGACY-ZERO-TAX';

                $result = $this->buildUbl($model);
                $document = $result['peppol']->getDocument();
                $lines = $is_credit
                    ? $document->CreditNoteLine
                    : $document->InvoiceLine;

                $this->assertSame('E', $lines[0]->Item->ClassifiedTaxCategory[0]->ID->value);
                $this->assertSame('E', $document->TaxTotal[0]->TaxSubtotal[0]->TaxCategory->ID->value);
                $this->assertSame((string) $tax_id, $model->line_items[0]->tax_id);
                $this->assertUblPassesSchematron($result['xml']);
            }
        }
    }

    public function testClientExemptionIsPreserved(): void
    {
        $client = $this->harnessClient();
        $client->is_tax_exempt = true;
        $client->save();
        $item = $this->harnessLineItem('Client exempt', 100, 1, taxRate: 0);
        $model = $this->harnessInvoice($client, [$item]);
        $model->po_number = 'EXEMPT';
        $result = $this->buildUbl($model);
        $this->assertSame('E', $result['peppol']->getDocument()->TaxTotal[0]->TaxSubtotal[0]->TaxCategory->ID->value);
        $this->assertUblPassesSchematron($result['xml']);
    }

    public function testSmallDiscountAcrossManyCategoriesDoesNotOverAllocate(): void
    {
        $client = $this->harnessClient();
        $items = [];
        foreach ([0, 0, 5, 6, 12, 21] as $index => $rate) {
            $item = $this->harnessLineItem('Group ' . $index, 100, 1, taxRate: $rate);
            $item->tax_id = (string) match ($index) {
                0 => Product::PRODUCT_TYPE_ZERO_RATED,
                1 => Product::PRODUCT_TYPE_EXEMPT,
                default => Product::PRODUCT_TYPE_PHYSICAL,
            };
            $items[] = $item;
        }
        foreach ([false, true] as $credit) {
            $model = $credit ? $this->harnessCredit($client, $items) : $this->harnessInvoice($client, $items);
            $model->po_number = 'SMALL-DISCOUNT';
            $model->discount = 0.03;
            $model->is_amount_discount = true;
            $model = $credit ? $this->harnessSaveCredit($model) : (new InvoiceRepository())->save([], $model);
            $result = $this->buildUbl($model);
            $amounts = array_map(fn ($a) => (float) $a->Amount->amount, $result['peppol']->getDocument()->AllowanceCharge);
            $this->assertEqualsWithDelta(0.03, array_sum($amounts), 0.000001);
            $this->assertUblPassesSchematron($result['xml']);
        }
    }

    public function testTaxRatesRetainTheSupportedSixDecimalPrecision(): void
    {
        $client = $this->harnessClient();
        $model = $this->harnessInvoice($client, [
            $this->harnessLineItem('Rate A', 100, 1, taxRate: 5.123451),
            $this->harnessLineItem('Rate B', 100, 1, taxRate: 5.123452),
        ]);
        $model->po_number = 'RATE-PRECISION';
        $result = $this->buildUbl($model);
        $subtotals = $result['peppol']->getDocument()->TaxTotal[0]->TaxSubtotal;
        $this->assertCount(2, $subtotals);
        $this->assertUblPassesSchematron($result['xml']);
    }

    public function testDocumentDiscountWithNegativeZeroRatedGroup(): void
    {
        $client = $this->harnessClient();
        foreach ([false, true] as $credit) {
            foreach ([false, true] as $flat) {
                $offset = $this->harnessLineItem('Zero-rated offset', 20, -1, taxRate: 0);
                $offset->tax_id = (string) Product::PRODUCT_TYPE_ZERO_RATED;
                $items = [$this->harnessLineItem('Standard', 100, 1, taxRate: 21), $offset];
                $model = $credit ? $this->harnessCredit($client, $items) : $this->harnessInvoice($client, $items);
                $model->po_number = 'MIXED-SIGN-CATEGORIES';
                $model->discount = $flat ? 8 : 10;
                $model->is_amount_discount = $flat;
                $model = $credit ? $this->harnessSaveCredit($model) : (new InvoiceRepository())->save([], $model);
                $result = $this->buildUbl($model);
                $this->assertUblPassesSchematron($result['xml']);
                $lmt = $result['peppol']->getDocument()->LegalMonetaryTotal;
                $this->assertEquals(72, $lmt->TaxExclusiveAmount->amount);
                $this->assertEquals(10, $lmt->AllowanceTotalAmount->amount);
                $this->assertEquals(2, $lmt->ChargeTotalAmount->amount);
            }
        }
    }
}
