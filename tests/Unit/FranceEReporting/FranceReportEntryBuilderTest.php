<?php

namespace Tests\Unit\FranceEReporting;

use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\EDocument\Standards\France\FranceReportEntryBuilder;
use Tests\TestCase;

class FranceReportEntryBuilderTest extends TestCase
{
    public function testB2BIPaymentRepeatsTheFullPaymentAmountForEveryTaxSubtotal(): void
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
        $this->assertSame(["230", "230"], $amountsIncludingTax);
        $this->assertSame(460.0, $amountIncludingTaxTotal);
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

    private function client(Company $company): Client
    {
        $client = new Client();
        $client->setRawAttributes([
            "id" => 30,
            "company_id" => 1,
            "is_tax_exempt" => false,
        ], true);
        $client->setRelation("company", $company);

        return $client;
    }

    private function invoice(Company $company, Client $client): Invoice
    {
        $invoice = new Invoice();
        $invoice->setRawAttributes([
            "id" => 20,
            "company_id" => 1,
            "client_id" => 30,
            "number" => "FR-MULTI-TAX-001",
            "date" => "2026-09-01",
            "amount" => 230,
            "balance" => 230,
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
        $invoice->line_items = [
            $this->lineItem("CONSULTING-20", 100, "VAT20", 20),
            $this->lineItem("CONSULTING-10", 100, "VAT10", 10),
        ];
        $invoice->setRelation("company", $company);
        $invoice->setRelation("client", $client);

        return $invoice;
    }

    private function lineItem(string $productKey, int|float $cost, string $taxName, int|float $taxRate): object
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;
        $item->tax_name1 = $taxName;
        $item->tax_rate1 = $taxRate;
        $item->product_key = $productKey;
        $item->notes = "Consulting services";

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
