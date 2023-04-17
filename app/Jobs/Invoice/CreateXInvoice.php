<?php

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use App\Models\Product;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
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

    public function __construct(private Invoice $invoice, private bool $alterPDF, private string $custom_pdf_path = "")
    {
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
        switch ($company->e_invoice_type) {
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
            ->setDocumentBuyerReference($client->routing_id)
            ->setDocumentBuyerContact($client->primary_contact()->first()->first_name . " " . $client->primary_contact()->first()->last_name, "", $client->primary_contact()->first()->phone, "", $client->primary_contact()->first()->email)
            ->setDocumentShipToAddress($client->shipping_address1, $client->shipping_address2, "", $client->shipping_postal_code, $client->shipping_city, $client->shipping_country->iso_3166_2, $client->shipping_state)
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($invoice->date)->diff(date_create($invoice->due_date))->format("%d"), 'paydate' => $invoice->due_date]));
        if (!empty($invoice->public_notes)) {
            $xrechnung->addDocumentNote($invoice->public_notes);
        }
        if (!empty($invoice->po_number)) {
            $xrechnung->setDocumentBuyerOrderReferencedDocument($invoice->po_number);
        }
        if (empty($client->routing_id)){
            $xrechnung->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        }
        $xrechnung->addDocumentPaymentMean(68, ctrans("texts.xinvoice_online_payment"));

        if (str_contains($company->getSetting('vat_number'), "/")) {
            $xrechnung->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
        } else {
            $xrechnung->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));
        }

        $invoicing_data = $invoice->calc();
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
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_id, $invoice), 'VAT', $item->tax_rate1);
                } elseif (!empty($item->tax_name2)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_id, $invoice), 'VAT', $item->tax_rate2);
                } elseif (!empty($item->tax_name3)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_id, $invoice), 'VAT', $item->tax_rate3);
                } else {
                    nlog("Can't add correct tax position");
                }
            } else {
               if (!empty($invoice->tax_name1)) {
                   $globaltax = 0;
                   $xrechnung->addDocumentPositionTax($this->getTaxType($invoice->tax_name1, $invoice), 'VAT', $invoice->tax_rate1);
                } elseif (!empty($invoice->tax_name2)) {
                   $globaltax = 1;
                   $xrechnung->addDocumentPositionTax($this->getTaxType($invoice->tax_name2, $invoice), 'VAT', $invoice->tax_rate2);
                } elseif (!empty($invoice->tax_name3)) {
                   $globaltax = 2;
                   $xrechnung->addDocumentPositionTax($this->getTaxType($invoice->tax_name3, $invoice), 'VAT', $item->tax_rate3);
               } else {
                    nlog("Can't add correct tax position");
              }
            }
        }


        if ($invoice->isPartial()) {
            $xrechnung->setDocumentSummation($invoice->amount, $invoice->balance, $invoicing_data->getSubTotal(), $invoicing_data->getTotalSurcharges(), $invoicing_data->getTotalDiscount(), $invoicing_data->getSubTotal(), $invoicing_data->getItemTotalTaxes(), null, $invoice->partial);
        } else {
            $xrechnung->setDocumentSummation($invoice->amount, $invoice->balance, $invoicing_data->getSubTotal(), $invoicing_data->getTotalSurcharges(), $invoicing_data->getTotalDiscount(), $invoicing_data->getSubTotal(), $invoicing_data->getItemTotalTaxes(), null, 0.0);
        }

        foreach ($invoicing_data->getTaxMap() as $item) {
            $tax = explode(" ", $item["name"]);
            $xrechnung->addDocumentTax($this->getTaxType("", $invoice), "VAT", $item["total"] / (explode("%", end($tax))[0] / 100), $item["total"], explode("%", end($tax))[0]);
            // TODO: Add correct tax type within getTaxType
        }
        if (!empty($globaltax)){
            $tax = explode(" ", $invoicing_data->getTotalTaxMap()[$globaltax]["name"]);
            $xrechnung->addDocumentTax($this->getTaxType("", $invoice), "VAT", $invoicing_data->getTotalTaxMap()[$globaltax]["total"] / (explode("%", end($tax))[0] / 100), $invoicing_data->getTotalTaxMap()[$globaltax]["total"], explode("%", end($tax))[0]);
            // TODO: Add correct tax type within getTaxType
        }

        $disk = config('filesystems.default');
        if (!Storage::exists($client->e_invoice_filepath($invoice->invitations->first()))) {
            Storage::makeDirectory($client->e_invoice_filepath($invoice->invitations->first()));
        }
        $xrechnung->writeFile(Storage::disk($disk)->path($client->e_invoice_filepath($invoice->invitations->first()) . $invoice->getFileName("xml")));
        // The validity can be checked using https://portal3.gefeg.com/invoice/validation

        if ($this->alterPDF) {
            if ($this->custom_pdf_path != "") {
                $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, $this->custom_pdf_path);
                $pdfBuilder->generateDocument();
                $pdfBuilder->saveDocument($this->custom_pdf_path);
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

        return $client->e_invoice_filepath($invoice->invitations->first()) . $invoice->getFileName("xml");
    }

    private function getTaxType($name, Invoice $invoice): string
    {
        $taxtype = null;
        switch ($name) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $taxtype = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $taxtype =  ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $taxtype = ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS;
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $taxtype = ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE;
                break;
        }
        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE", "IS", "LI", "NO", "CH"];
        if (empty($taxtype)){
            if (in_array($invoice->company->country()->iso_3166_2, $eu_states) && in_array($invoice->client->country->iso_3166_2, $eu_states)){
                $taxtype = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            }
            elseif (!in_array($invoice->client->country->iso_3166_2, $eu_states)){
                $taxtype = ZugferdDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX;
            }
            elseif ($invoice->client->country->iso_3166_2 == "ES-CN"){
                $taxtype = ZugferdDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            }
            elseif (in_array($invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])){
                $taxtype = ZugferdDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            }
            else {
                nlog("Unkown tax case for xinvoice");
                $taxtype = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
            }
        }
        return $taxtype;
    }
}
