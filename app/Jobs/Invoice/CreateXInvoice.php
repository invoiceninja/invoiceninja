<?php

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;


class CreateXInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Invoice $invoice;
    private bool $alterpdf;
    private string $custompdfpath;

    public function __construct(Invoice $invoice, bool $alterPDF, string $custompdfpath = "")
    {
        $this->invoice = $invoice;
        $this->alterpdf = $alterPDF;
        $this->custompdfpath = $custompdfpath;
    }

    /**
     * Execute the job.
     *
     *
     * @return string
     */
    public function handle(): string
    {
        $invoice = $this->invoice;
        $company = $invoice->company;
        $client = $invoice->client;
        $profile = "";
        switch ($company->xinvoice_type) {
            case "EN16931":
                $profile = ZugferdProfiles::PROFILE_EN16931;
                break;
            case "XInvoice_2_2":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG_2_2;
                break;
            case "XInvoice_2_1":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG_2_1;
                break;
            case "XInvoice_2_0":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG_2;
                break;
            case "XInvoice_1_0":
                $profile = ZugferdProfiles::PROFILE_XRECHNUNG;
                break;
            case "XInvoice-Extended":
                $profile = ZugferdProfiles::PROFILE_EXTENDED;
                break;
            case "XInvoice-BasicWL":
                $profile = ZugferdProfiles::PROFILE_BASICWL;
                break;
            case "XInvoice-Basic":
                $profile = ZugferdProfiles::PROFILE_BASIC;
                break;
        }
        $xrechnung = ZugferdDocumentBuilder::CreateNew($profile);

        $xrechnung
            ->setDocumentInformation($invoice->number, "380", date_create($invoice->date), $invoice->client->getCurrencyCode())
            ->setDocumentSupplyChainEvent(date_create($invoice->date))
            ->setDocumentSeller($company->getSetting('name'))
            ->setDocumentSellerAddress($company->getSetting("address1"), $company->getSetting("address2"), "", $company->getSetting("postal_code"), $company->getSetting("city"), $company->country()->iso_3166_2, $company->getSetting("state"))
            ->setDocumentSellerContact($invoice->user->first_name." ".$invoice->user->last_name, "", $invoice->user->phone, "", $invoice->user->email)
            ->setDocumentBuyer($client->name, $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->iso_3166_2)
            ->setDocumentBuyerReference($client->leitweg_id)
            ->setDocumentBuyerContact($client->primary_contact()->first()->first_name . " " . $client->primary_contact()->first()->last_name, "", $client->primary_contact()->first()->phone, "", $client->primary_contact()->first()->email)
            ->setDocumentShipToAddress($client->shipping_address1, $client->shipping_address2, "", $client->shipping_postal_code, $client->shipping_city, $client->shipping_country->iso_3166_2, $client->shipping_state)
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($invoice->date)->diff(date_create($invoice->due_date))->format("%d"), 'paydate' => $invoice->due_date]));
        if (!empty($invoice->public_notes)) {
            $xrechnung->addDocumentNote($invoice->public_notes);
        }
        if (!empty($invoice->po_number)) {
            $xrechnung->setDocumentBuyerOrderReferencedDocument($invoice->po_number);
        }
        if (empty($client->leitweg_id)){
            $xrechnung->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        }
        $xrechnung->addDocumentPaymentMean(10, "");

        if (str_contains($company->getSetting('vat_number'), "/")) {
            $xrechnung->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
        } else {
            $xrechnung->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));
        }

        $invoicingdata = $invoice->calc();
        $globaltax = null;

        //Create line items and calculate taxes
        foreach ($invoice->line_items as $index => $item) {
            $xrechnung->addNewPosition($index)
                ->setDocumentPositionProductDetails($item->notes)
                ->setDocumentPositionGrossPrice($item->gross_line_total)
                ->setDocumentPositionNetPrice($item->line_total);
            if (isset($item->task_id)) {
                $xrechnung->setDocumentPositionQuantity($item->quantity, "HUR");
            } else {
                $xrechnung->setDocumentPositionQuantity($item->quantity, "H87");
            }
            $linenetamount = $item->line_total;
            if ($item->discount > 0){
                if ($invoice->is_amount_discount){
                    $linenetamount -= $item->discount;
                }
                else {
                    $linenetamount -= $linenetamount * ($item->discount / 100);
                }
            }
            $xrechnung->setDocumentPositionLineSummation($linenetamount);
            // According to european law, each line item can only have one tax rate
            if (!(empty($item->tax_name1) && empty($item->tax_name2) && empty($item->tax_name3))){
                if (!empty($item->tax_name1)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_name1), 'VAT', $item->tax_rate1);
                } elseif (!empty($item->tax_name2)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_name2), 'VAT', $item->tax_rate2);
                } elseif (!empty($item->tax_name3)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_name3), 'VAT', $item->tax_rate3);
                } else {
                    nlog("Can't add correct tax position");
                }
            } else {
               if (!empty($invoice->tax_name1)) {
                   $globaltax = 0;
                   $xrechnung->addDocumentPositionTax($this->getTaxType($invoice->tax_name1), 'VAT', $invoice->tax_rate1);
                } elseif (!empty($invoice->tax_name2)) {
                   $globaltax = 1;
                   $xrechnung->addDocumentPositionTax($this->getTaxType($invoice->tax_name2), 'VAT', $invoice->tax_rate2);
                } elseif (!empty($invoice->tax_name3)) {
                   $globaltax = 2;
                   $xrechnung->addDocumentPositionTax($this->getTaxType($invoice->tax_name3), 'VAT', $item->tax_rate3);
               } else {
                    nlog("Can't add correct tax position");
              }
            }
        }


        if ($invoice->isPartial()) {
            $xrechnung->setDocumentSummation($invoice->amount, $invoice->amount - $invoice->balance, $invoicingdata->getSubTotal(), $invoicingdata->getTotalSurcharges(), $invoicingdata->getTotalDiscount(), $invoicingdata->getSubTotal(), $invoicingdata->getItemTotalTaxes(), null, $invoice->partial);
        } else {
            $xrechnung->setDocumentSummation($invoice->amount, $invoice->amount - $invoice->balance, $invoicingdata->getSubTotal(), $invoicingdata->getTotalSurcharges(), $invoicingdata->getTotalDiscount(), $invoicingdata->getSubTotal(), $invoicingdata->getItemTotalTaxes(), null, 0.0);
        }

        foreach ($invoicingdata->getTaxMap() as $item) {
            $tax = explode(" ", $item["name"]);
            $xrechnung->addDocumentTax($this->getTaxType(""), "VAT", $item["total"] / (explode("%", end($tax))[0] / 100), $item["total"], explode("%", end($tax))[0]);
            // TODO: Add correct tax type within getTaxType
        }
        if (!empty($globaltax)){
            $tax = explode(" ", $invoicingdata->getTotalTaxMap()[$globaltax]["name"]);
            $xrechnung->addDocumentTax($this->getTaxType(""), "VAT", $invoicingdata->getTotalTaxMap()[$globaltax]["total"] / (explode("%", end($tax))[0] / 100), $invoicingdata->getTotalTaxMap()[$globaltax]["total"], explode("%", end($tax))[0]);
            // TODO: Add correct tax type within getTaxType
        }

        $disk = config('filesystems.default');
        if (!Storage::exists($client->xinvoice_filepath($invoice->invitations->first()))) {
            Storage::makeDirectory($client->xinvoice_filepath($invoice->invitations->first()));
        }
        $xrechnung->writeFile(Storage::disk($disk)->path($client->xinvoice_filepath($invoice->invitations->first()) . $invoice->getFileName("xml")));
        // The validity can be checked using https://portal3.gefeg.com/invoice/validation

        if ($this->alterpdf) {
            if ($this->custompdfpath != "") {
                $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, $this->custompdfpath);
                $pdfBuilder->generateDocument();
                $pdfBuilder->saveDocument($this->custompdfpath);
            } else {
                $filepath_pdf = $client->invoice_filepath($invoice->invitations->first()) . $invoice->getFileName();
                $file = Storage::disk($disk)->exists($filepath_pdf);
                if ($file) {
                    $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, Storage::disk($disk)->path($filepath_pdf));
                    $pdfBuilder->generateDocument();
                    $pdfBuilder->saveDocument(Storage::disk($disk)->path($filepath_pdf));
                }
            }
        }

        return $client->invoice_filepath($invoice->invitations->first()) . $invoice->getFileName("xml");
    }

    private function getTaxType(string $name): string
    {
        return match ($name) {
            "ZeroRate" => "Z",
            "Tax Exempt" => "E",
            "Reversal of tax liabilty" => "AE",
            "intra-community delivery" => "K",
            "Out of EU" => "G",
            "Outside the tax scope" => "O",
            "Canary Islands" => "L",
            "Ceuta / Melila" => "M",
            default => "S",
        };
    }
}
