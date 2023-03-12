<?php

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;


class CreateXRechnung implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        $invoice = $this->invoice;
        $company = $invoice->company;
        $client = $invoice->client;
        $xrechnung =  ZugferdDocumentBuilder::CreateNew(ZugferdProfiles::PROFILE_EN16931);

        $xrechnung
            ->setDocumentInformation($invoice->number, "380", date_create($invoice->date), $invoice->client->getCurrencyCode())
            ->addDocumentNote($invoice->public_notes)
            ->setDocumentSupplyChainEvent(date_create($invoice->date))
            ->setDocumentSeller($company->name)
            //->addDocumentSellerGlobalId("4000001123452", "0088")
            //->addDocumentSellerTaxRegistration("FC", "201/113/40209")
            ->addDocumentSellerTaxRegistration("VA", $company->vat_number)
            ->setDocumentSellerAddress($company->address1, "", "", $company->postal_code, $company->city, $company->country->country->iso_3166_2)
            ->setDocumentBuyer($client->name, $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->country->iso_3166_2);
            //->addDocumentPaymentTerm("Zahlbar innerhalb 30 Tagen netto bis 04.04.2018, 3% Skonto innerhalb 10 Tagen bis 15.03.2018")
            $taxamount_1 = $taxAmount_2 = $taxamount_3 = 0.0;
            $netprice = 0.0;

            foreach ($invoice->line_items as $index => $item){
                $xrechnung->addNewPosition($index)
                    ->setDocumentPositionProductDetails($item->notes, "", "TB100A4", null, "0160", "4012345001235")
                    ->setDocumentPositionGrossPrice($item->gross_line_total)
                    ->setDocumentPositionNetPrice($item->line_total)
                    ->setDocumentPositionQuantity($item->quantity, "H87")
                    ->addDocumentPositionTax('S', 'VAT', 19);
                $netprice += $item->line_total;

            }
            if ($invoice->isPartial()){
                $xrechnung->setDocumentSummation($invoice->amount, $invoice->balance, $netprice, 0.0, 0.0, 473.00, $invoice->total_taxes, null, $invoice->partial);}
            else {
                $xrechnung->setDocumentSummation($invoice->amount, $invoice->balance, $netprice, 0.0, 0.0, 473.00, $invoice->total_taxes, null, 0.0);
            }
            if (strlen($invoice->tax_name1) > 1) {
            $xrechnung->addDocumentTax("S", "VAT", 275.0, 19.25, $invoice->tax_rate1);
            }
            if (strlen($invoice->tax_name2) > 1) {
            $xrechnung->addDocumentTax("S", "VAT", 275.0, 19.25, $invoice->tax_rate2);
            }
            if (strlen($invoice->tax_name3) > 1) {
                $xrechnung->addDocumentTax("S", "VAT", 275.0, 19.25, $invoice->tax_rate3);
            };
            $xrechnung->writeFile(getcwd() . "/factur-x.xml");

        $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, "/tmp/original.pdf");
        $pdfBuilder->generateDocument();
        $pdfBuilder->saveDocument("/tmp/new.pdf");
    }
}
