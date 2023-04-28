<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice\EInvoice;

use App\Models\Invoice;
use App\Models\Product;
use App\Services\AbstractService;
use horstoeko\zugferd\ZugferdProfiles;
use Illuminate\Support\Facades\Storage;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;

class ZugferdEInvoice extends AbstractService
{

    public function __construct(public Invoice $invoice, private bool $alterPDF, private string $custom_pdf_path = "")
    {
    }

    public function run()
    {

        $company = $this->invoice->company;
        $client = $this->invoice->client;
        $profile = $client->getSetting('e_invoice_type');

        $profile = match ($profile) {
            "XInvoice_2_2" => ZugferdProfiles::PROFILE_XRECHNUNG_2_2,
            "XInvoice_2_1" => ZugferdProfiles::PROFILE_XRECHNUNG_2_1,
            "XInvoice_2_0" => ZugferdProfiles::PROFILE_XRECHNUNG_2,
            "XInvoice_1_0" => ZugferdProfiles::PROFILE_XRECHNUNG,
            "XInvoice-Extended" => ZugferdProfiles::PROFILE_EXTENDED,
            "XInvoice-BasicWL" => ZugferdProfiles::PROFILE_BASICWL,
            "XInvoice-Basic" => ZugferdProfiles::PROFILE_BASIC,
            default => ZugferdProfiles::PROFILE_EN16931,
        };


        $xrechnung = ZugferdDocumentBuilder::CreateNew($profile);

        $xrechnung
            ->setDocumentInformation($this->invoice->number, "380", date_create($this->invoice->date), $this->invoice->client->getCurrencyCode())
            ->setDocumentSupplyChainEvent(date_create($this->invoice->date))
            ->setDocumentSeller($company->getSetting('name'))
            ->setDocumentSellerAddress($company->getSetting("address1"), $company->getSetting("address2"), "", $company->getSetting("postal_code"), $company->getSetting("city"), $company->country()->iso_3166_2, $company->getSetting("state"))
            ->setDocumentSellerContact($this->invoice->user->first_name." ".$this->invoice->user->last_name, "", $this->invoice->user->phone, "", $this->invoice->user->email)
            ->setDocumentBuyer($client->name, $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->iso_3166_2)
            ->setDocumentBuyerContact($client->primary_contact()->first()->first_name . " " . $client->primary_contact()->first()->last_name, "", $client->primary_contact()->first()->phone, "", $client->primary_contact()->first()->email)
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($this->invoice->date)->diff(date_create($this->invoice->due_date))->format("%d"), 'paydate' => $this->invoice->due_date]));

            if (!empty($this->invoice->public_notes)) {
            $xrechnung->addDocumentNote($this->invoice->public_notes);
        }

        if (!empty($this->invoice->po_number)) {
            $xrechnung->setDocumentBuyerOrderReferencedDocument($this->invoice->po_number);
        }

        if (empty($client->routing_id)) {
            $xrechnung->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        } else {
            $xrechnung->setDocumentBuyerReference($client->routing_id);
        }
        if (!empty($client->shipping_address1)){
            $xrechnung->setDocumentShipToAddress($client->shipping_address1, $client->shipping_address2, "", $client->shipping_postal_code, $client->shipping_city, $client->shipping_country->iso_3166_2, $client->shipping_state);
        }

        $xrechnung->addDocumentPaymentMean(68, ctrans("texts.xinvoice_online_payment"));

        if (str_contains($company->getSetting('vat_number'), "/")) {
            $xrechnung->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
        } else {
            $xrechnung->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));
        }

        $invoicing_data = $this->invoice->calc();
        $globaltax = null;

        //Create line items and calculate taxes
        foreach ($this->invoice->line_items as $index => $item) {
            $xrechnung->addNewPosition($index)
                ->setDocumentPositionGrossPrice($item->gross_line_total)
                ->setDocumentPositionNetPrice($item->line_total);
            if (!empty($item->product_key)){
                if (!empty($item->notes)){
                   $xrechnung->setDocumentPositionProductDetails($item->product_key, $item->notes);
                }
                $xrechnung->setDocumentPositionProductDetails($item->product_key);
            }
            else {
                if (!empty($item->notes)){
                    $xrechnung->setDocumentPositionProductDetails($item->notes);
                }
                else {
                    $xrechnung->setDocumentPositionProductDetails("no product name defined");
                }
            }
            if (isset($item->task_id)) {
                $xrechnung->setDocumentPositionQuantity($item->quantity, "HUR");
            } else {
                $xrechnung->setDocumentPositionQuantity($item->quantity, "H87");
            }
            $linenetamount = $item->line_total;
            if ($item->discount > 0) {
                if ($this->invoice->is_amount_discount) {
                    $linenetamount -= $item->discount;
                } else {
                    $linenetamount -= $linenetamount * ($item->discount / 100);
                }
            }
            $xrechnung->setDocumentPositionLineSummation($linenetamount);
            // According to european law, each line item can only have one tax rate
            if (!(empty($item->tax_name1) && empty($item->tax_name2) && empty($item->tax_name3))) {
                if (!empty($item->tax_name1)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_id), 'VAT', $item->tax_rate1);
                } elseif (!empty($item->tax_name2)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_id), 'VAT', $item->tax_rate2);
                } elseif (!empty($item->tax_name3)) {
                    $xrechnung->addDocumentPositionTax($this->getTaxType($item->tax_id), 'VAT', $item->tax_rate3);
                } else {
                    nlog("Can't add correct tax position");
                }
            } else {
                if (!empty($this->invoice->tax_name1)) {
                    $globaltax = 0;
                    $xrechnung->addDocumentPositionTax($this->getTaxType($this->invoice->tax_name1), 'VAT', $this->invoice->tax_rate1);
                } elseif (!empty($this->invoice->tax_name2)) {
                    $globaltax = 1;
                    $xrechnung->addDocumentPositionTax($this->getTaxType($this->invoice->tax_name2), 'VAT', $this->invoice->tax_rate2);
                } elseif (!empty($this->invoice->tax_name3)) {
                    $globaltax = 2;
                    $xrechnung->addDocumentPositionTax($this->getTaxType($this->invoice->tax_name3), 'VAT', $item->tax_rate3);
                } else {
                    nlog("Can't add correct tax position");
                }
            }
        }


        if ($this->invoice->isPartial()) {
            $xrechnung->setDocumentSummation($this->invoice->amount, $this->invoice->amount-$this->invoice->balance, $invoicing_data->getSubTotal(), $invoicing_data->getTotalSurcharges(), $invoicing_data->getTotalDiscount(), $invoicing_data->getSubTotal(), $invoicing_data->getItemTotalTaxes(), null, $this->invoice->partial);
        } else {
            $xrechnung->setDocumentSummation($this->invoice->amount, $this->invoice->amount-$this->invoice->balance, $invoicing_data->getSubTotal(), $invoicing_data->getTotalSurcharges(), $invoicing_data->getTotalDiscount(), $invoicing_data->getSubTotal(), $invoicing_data->getItemTotalTaxes(), null, 0.0);
        }


        foreach ($invoicing_data->getTaxMap() as $item) {

            $tax_name = explode(" ", $item["name"]);
            $tax_rate = (explode("%", end($tax_name))[0] / 100);

            $total_tax = $tax_rate == 0 ? 0 : $item["total"] / $tax_rate;

            $xrechnung->addDocumentTax($this->getTaxType(""), "VAT", $total_tax, $item["total"], explode("%", end($tax_name))[0]);
            // TODO: Add correct tax type within getTaxType
        }

        if (!empty($globaltax && isset($invoicing_data->getTotalTaxMap()[$globaltax]["name"]))) {
            $tax_name = explode(" ", $invoicing_data->getTotalTaxMap()[$globaltax]["name"]);
            $xrechnung->addDocumentTax($this->getTaxType(""), "VAT", $invoicing_data->getTotalTaxMap()[$globaltax]["total"] / (explode("%", end($tax_name))[0] / 100), $invoicing_data->getTotalTaxMap()[$globaltax]["total"], explode("%", end($tax_name))[0]);
            // TODO: Add correct tax type within getTaxType
        }

        $disk = config('filesystems.default');

        if (!Storage::disk($disk)->exists($client->e_invoice_filepath($this->invoice->invitations->first()))) {
            Storage::makeDirectory($client->e_invoice_filepath($this->invoice->invitations->first()));
        }

        $xrechnung->writeFile(Storage::disk($disk)->path($client->e_invoice_filepath($this->invoice->invitations->first()) . $this->invoice->getFileName("xml")));
        // The validity can be checked using https://portal3.gefeg.com/invoice/validation or https://e-rechnung.bayern.de/app/#/upload

        if ($this->alterPDF) {
            if ($this->custom_pdf_path != "") {
                $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, $this->custom_pdf_path);
                $pdfBuilder->generateDocument();
                $pdfBuilder->saveDocument($this->custom_pdf_path);
            } else {
                $filepath_pdf = $client->invoice_filepath($this->invoice->invitations->first()) . $this->invoice->getFileName();
                $file = Storage::disk($disk)->exists($filepath_pdf);
                if ($file) {
                    $pdfBuilder = new ZugferdDocumentPdfBuilder($xrechnung, Storage::disk($disk)->path($filepath_pdf));
                    $pdfBuilder->generateDocument();
                    $pdfBuilder->saveDocument(Storage::disk($disk)->path($filepath_pdf));
                }
            }
        }

        return $client->e_invoice_filepath($this->invoice->invitations->first()) . $this->invoice->getFileName("xml");
    }

    private function getTaxType($name): string
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
        if (empty($taxtype)) {
            if ((in_array($this->invoice->company->country()->iso_3166_2, $eu_states) && in_array($this->invoice->client->country->iso_3166_2, $eu_states)) && $this->invoice->company->country()->iso_3166_2 != $this->invoice->client->country->iso_3166_2) {
                $taxtype = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            } elseif (!in_array($this->invoice->client->country->iso_3166_2, $eu_states)) {
                $taxtype = ZugferdDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX;
            } elseif ($this->invoice->client->country->iso_3166_2 == "ES-CN") {
                $taxtype = ZugferdDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            } elseif (in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $taxtype = ZugferdDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            } else {
                nlog("Unkown tax case for xinvoice");
                $taxtype = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
            }
        }
        return $taxtype;
    }

}
