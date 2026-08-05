<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\DataMapper\ClientSettings;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Services\EDocument\Standards\France\FranceReportEntryBuilder;
use Tests\TestCase;

class FranceReportEntryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->instance('currencies', collect([
            $this->currency(3, 'EUR'),
            $this->currency(1, 'USD'),
        ]));
    }

    public function testB2BIPaymentAllocatesTheFullPaymentAcrossGrossTaxBuckets(): void
    {
        $company = $this->company();
        $client = $this->client($company);
        $invoice = $this->invoice($company, $client);
        $payment = $this->payment($company, $client);

        $b2biPayment = (new FranceReportEntryBuilder())->b2biPayment(
            payment: $payment,
            invoice: $invoice,
            paymentAmount: "230",
            paymentDate: "2026-09-15",
        );

        $amountsIncludingTax = collect($b2biPayment->taxSubtotals)
            ->map(fn (object $taxSubtotal): string => (string) $taxSubtotal->amountIncludingTax)
            ->all();
        $amountIncludingTaxTotal = collect($b2biPayment->taxSubtotals)
            ->sum(fn (object $taxSubtotal): float => (float) $taxSubtotal->amountIncludingTax);

        $this->assertCount(2, $b2biPayment->taxSubtotals);
        $this->assertSame(["120", "110"], $amountsIncludingTax);
        $this->assertSame(230.0, $amountIncludingTaxTotal);
    }

    public function testB2BIPartialPaymentAllocatesProportionallyAndPreservesTheRoundedTotal(): void
    {
        $company = $this->company();
        $client = $this->client($company);
        $invoice = $this->invoice($company, $client);
        $payment = $this->payment($company, $client);

        $taxSubtotals = (new FranceReportEntryBuilder())
            ->b2biPayment($payment, $invoice, '100')
            ->taxSubtotals;

        $this->assertSame([52.17, 47.83], array_map(
            static fn (object $taxSubtotal): int|float => $taxSubtotal->amountIncludingTax,
            $taxSubtotals,
        ));
        $this->assertSame(100.0, collect($taxSubtotals)->sum(
            static fn (object $taxSubtotal): float => (float) $taxSubtotal->amountIncludingTax,
        ));
    }

    public function testB2CPaymentUsesStorecoveAmountRowsInTheInvoiceCurrency(): void
    {
        $company = $this->company();
        $client = $this->client($company);
        $invoice = $this->invoice($company, $client);
        $payment = $this->payment($company, $client);

        $payload = (new FranceReportEntryBuilder())
            ->b2cPayment($payment, $invoice, '-23', '2026-09-20')
            ->toArray();

        $this->assertSame('2026-09-20', $payload['date']);
        $this->assertSame([-12, -11], array_column($payload['taxSubtotal'], 'amount'));
        $this->assertSame(['EUR', 'EUR'], array_column($payload['taxSubtotal'], 'currency'));
        $this->assertSame(['FR', 'FR'], array_column($payload['taxSubtotal'], 'country'));
        $this->assertArrayNotHasKey('taxableAmount', $payload['taxSubtotal'][0]);
        $this->assertArrayNotHasKey('taxAmount', $payload['taxSubtotal'][0]);
        $this->assertArrayNotHasKey('amountIncludingTax', $payload['taxSubtotal'][0]);
    }

    public function testPaymentsWithoutTaxUseOneExemptGrossAmountRow(): void
    {
        $company = $this->company();
        $client = $this->client($company);
        $invoice = $this->invoice(
            $company,
            $client,
            amount: 100,
            lineItems: [$this->lineItem('EXEMPT-SERVICE', 100, '', 0)],
        );
        $payment = $this->payment($company, $client);
        $builder = new FranceReportEntryBuilder();

        $this->assertSame([
            'category' => 'exempt',
            'percentage' => 0,
            'currency' => 'EUR',
            'country' => 'FR',
            'amount' => 40,
        ], $builder->b2cPayment($payment, $invoice, 40)->toArray()['taxSubtotal'][0]);

        $this->assertSame(40, $builder->b2biPayment($payment, $invoice, 40)->taxSubtotals[0]->amountIncludingTax);
    }

    public function testForeignCurrencyInitialAndCorrectivePaymentsRemainInTheInvoiceCurrency(): void
    {
        $company = $this->company();
        $client = $this->client($company, '1');
        $invoice = $this->invoice($company, $client);
        $payment = $this->payment($company, $client);
        $builder = new FranceReportEntryBuilder();

        $initial = $builder->b2cPayment($payment, $invoice, '230', '2026-09-15')->toArray();
        $corrective = $builder->b2cPayment($payment, $invoice, '-46', '2026-10-12')->toArray();
        $b2bi = $builder->b2biPayment($payment, $invoice, '230', '2026-09-15')->toArray();

        $this->assertSame(['USD', 'USD'], array_column($initial['taxSubtotal'], 'currency'));
        $this->assertSame([120, 110], array_column($initial['taxSubtotal'], 'amount'));
        $this->assertSame(['USD', 'USD'], array_column($corrective['taxSubtotal'], 'currency'));
        $this->assertSame([-24, -22], array_column($corrective['taxSubtotal'], 'amount'));
        $this->assertSame(['USD', 'USD'], array_column($b2bi['taxSubtotals'], 'currency'));
        $this->assertSame(230.0, (float) collect($b2bi['taxSubtotals'])->sum('amountIncludingTax'));
    }

    public function testB2CSupplyCategoryIsInferredAndUnsupportedLinesFailClosed(): void
    {
        $company = $this->company();
        $client = $this->client($company);
        $builder = new FranceReportEntryBuilder();
        $goods = $this->invoice($company, $client, lineItems: [
            $this->lineItem('GOODS', 100, 'VAT', 20, Product::PRODUCT_TYPE_PHYSICAL),
        ]);
        $services = $this->invoice($company, $client, lineItems: [
            $this->lineItem('SERVICE', 100, 'VAT', 20, Product::PRODUCT_TYPE_SERVICE),
        ]);
        $mixed = $this->invoice($company, $client, lineItems: [
            $this->lineItem('GOODS', 50, 'VAT', 20, Product::PRODUCT_TYPE_PHYSICAL),
            $this->lineItem('SERVICE', 50, 'VAT', 20, Product::PRODUCT_TYPE_SERVICE),
        ]);
        $unknown = $this->invoice($company, $client, lineItems: [
            $this->lineItem('FEE', 100, 'VAT', 20, 3),
        ]);
        $empty = $this->invoice($company, $client, lineItems: []);

        $this->assertSame('TLB1', $builder->b2cSupplyCategory($goods));
        $this->assertSame('TPS1', $builder->b2cSupplyCategory($services));
        $this->assertSame('TLB1', $builder->b2cTransaction($goods)?->category);
        $this->assertSame('TPS1', $builder->b2cTransaction($services)?->category);
        $this->assertNull($builder->b2cSupplyCategory($mixed));
        $this->assertNull($builder->b2cTransaction($mixed));
        $this->assertNull($builder->b2cSupplyCategory($unknown));
        $this->assertNull($builder->b2cSupplyCategory($empty));
    }

    private function company(): Company
    {
        $company = new Company();
        $company->setRawAttributes([
            "id" => 1,
        ], true);

        $settings = CompanySettings::defaults();
        $settings->currency_id = "3";

        $company->settings = $settings;

        return $company;
    }

    private function client(Company $company, string $currency_id = '3'): Client
    {
        $client = new Client();
        $client->setRawAttributes([
            "id" => 30,
            "company_id" => 1,
            "is_tax_exempt" => false,
        ], true);
        $settings = ClientSettings::defaults();
        $settings->currency_id = $currency_id;
        $client->settings = $settings;
        $client->setRelation("company", $company);

        return $client;
    }

    private function currency(int $id, string $code): Currency
    {
        $currency = new Currency();
        $currency->setRawAttributes([
            'id' => $id,
            'code' => $code,
            'precision' => 2,
        ], true);

        return $currency;
    }

    /**
     * @param array<int, object>|null $lineItems
     */
    private function invoice(Company $company, Client $client, int|float $amount = 230, ?array $lineItems = null): Invoice
    {
        $invoice = new Invoice();
        $invoice->setRawAttributes([
            "id" => 20,
            "company_id" => 1,
            "client_id" => 30,
            "number" => "FR-MULTI-TAX-001",
            "date" => "2026-09-01",
            "amount" => $amount,
            "balance" => $amount,
            "uses_inclusive_taxes" => false,
            "discount" => 0,
            "is_amount_discount" => true,
            "tax_name1" => "",
            "tax_rate1" => 0,
            "tax_name2" => "",
            "tax_rate2" => 0,
            "tax_name3" => "",
            "tax_rate3" => 0,
            "custom_surcharge1" => 0,
            "custom_surcharge2" => 0,
            "custom_surcharge3" => 0,
            "custom_surcharge4" => 0,
            "custom_surcharge_tax1" => false,
            "custom_surcharge_tax2" => false,
            "custom_surcharge_tax3" => false,
            "custom_surcharge_tax4" => false,
            "status_id" => Invoice::STATUS_SENT,
        ], true);
        $invoice->line_items = $lineItems ?? [
            $this->lineItem("CONSULTING-20", 100, "VAT20", 20),
            $this->lineItem("CONSULTING-10", 100, "VAT10", 10),
        ];
        $invoice->setRelation("company", $company);
        $invoice->setRelation("client", $client);

        return $invoice;
    }

    private function lineItem(
        string $productKey,
        int|float $cost,
        string $taxName,
        int|float $taxRate,
        int $typeId = Product::PRODUCT_TYPE_SERVICE,
    ): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;
        $item->tax_name1 = $taxName;
        $item->tax_rate1 = $taxRate;
        $item->product_key = $productKey;
        $item->notes = "Consulting services";
        $item->type_id = (string) $typeId;

        return $item;
    }

    private function payment(Company $company, Client $client): Payment
    {
        $payment = new Payment();
        $payment->setRawAttributes([
            "id" => 10,
            "company_id" => 1,
            "client_id" => 30,
            "amount" => 230,
            "applied" => 230,
            "date" => "2026-09-15",
            "status_id" => Payment::STATUS_COMPLETED,
        ], true);
        $payment->setRelation("company", $company);
        $payment->setRelation("client", $client);

        return $payment;
    }
}
