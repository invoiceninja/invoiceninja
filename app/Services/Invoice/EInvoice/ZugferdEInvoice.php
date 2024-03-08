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
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;

class ZugferdEInvoice extends AbstractService
{
    public ZugferdDocumentBuilder $xrechnung;

    public function __construct(public Invoice $invoice, private readonly bool $returnObject = false, private array $tax_map = [])
    {
    }

    public function run(): self
    {

        $company = $this->invoice->company;
        $client = $this->invoice->client;
        $profile = $client->getSetting('e_invoice_type');

        $profile = match ($profile) {
            "XInvoice_3_0" => ZugferdProfiles::PROFILE_XRECHNUNG_3,
            "XInvoice_2_3" => ZugferdProfiles::PROFILE_XRECHNUNG_2_3,
            "XInvoice_2_2" => ZugferdProfiles::PROFILE_XRECHNUNG_2_2,
            "XInvoice_2_1" => ZugferdProfiles::PROFILE_XRECHNUNG_2_1,
            "XInvoice_2_0" => ZugferdProfiles::PROFILE_XRECHNUNG_2,
            "XInvoice_1_0" => ZugferdProfiles::PROFILE_XRECHNUNG,
            "XInvoice-Extended" => ZugferdProfiles::PROFILE_EXTENDED,
            "XInvoice-BasicWL" => ZugferdProfiles::PROFILE_BASICWL,
            "XInvoice-Basic" => ZugferdProfiles::PROFILE_BASIC,
            default => ZugferdProfiles::PROFILE_EN16931,
        };

        $this->xrechnung = ZugferdDocumentBuilder::CreateNew($profile);

        $this->xrechnung
            ->setDocumentSupplyChainEvent(date_create($this->invoice->date ?? now()->format('Y-m-d')))
            ->setDocumentSeller($company->getSetting('name'))
            ->setDocumentSellerAddress($company->getSetting("address1"), $company->getSetting("address2"), "", $company->getSetting("postal_code"), $company->getSetting("city"), $company->country()->iso_3166_2, $company->getSetting("state"))
            ->setDocumentSellerContact($this->invoice->user->present()->getFullName(), "", $this->invoice->user->present()->phone(), "", $this->invoice->user->email)
            ->setDocumentBuyer($client->present()->name(), $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->iso_3166_2, $client->state)
            ->setDocumentBuyerContact($client->present()->primary_contact_name(), "", $client->present()->phone(), "", $client->present()->email())
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($this->invoice->date ?? now()->format('Y-m-d'))->diff(date_create($this->invoice->due_date ?? now()->format('Y-m-d')))->format("%d"), 'paydate' => $this->invoice->due_date]));

        if (!empty($this->invoice->public_notes)) {
            $this->xrechnung->addDocumentNote($this->invoice->public_notes ?? '');
        }
        if (empty($this->invoice->number)) {
            $this->xrechnung->setDocumentInformation("DRAFT", "380", date_create($this->invoice->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
        } else {
            $this->xrechnung->setDocumentInformation($this->invoice->number, "380", date_create($this->invoice->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
        }
        if (isset($this->invoice->po_number)) {
            $this->xrechnung->setDocumentBuyerOrderReferencedDocument($this->invoice->po_number);
        }

        if (empty($client->routing_id)) {
            $this->xrechnung->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        } else {
            $this->xrechnung->setDocumentBuyerReference($client->routing_id);
        }
        if (isset($client->shipping_address1) && $client->shipping_country) {
            $this->xrechnung->setDocumentShipToAddress($client->shipping_address1, $client->shipping_address2, "", $client->shipping_postal_code, $client->shipping_city, $client->shipping_country->iso_3166_2, $client->shipping_state);
        }

        $this->xrechnung->addDocumentPaymentMean(68, ctrans("texts.xinvoice_online_payment"));

        if (str_contains($company->getSetting('vat_number'), "/")) {
            $this->xrechnung->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
        } else {
            $this->xrechnung->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));
        }

        $invoicing_data = $this->invoice->calc();

        //Create line items and calculate taxes
        foreach ($this->invoice->line_items as $index => $item) {
            /** @var \App\DataMapper\InvoiceItem $item **/
            $this->xrechnung->addNewPosition($index)
                ->setDocumentPositionGrossPrice($item->gross_line_total)
                ->setDocumentPositionNetPrice($item->line_total);
            if (!empty($item->product_key)) {
                if (!empty($item->notes)) {
                    $this->xrechnung->setDocumentPositionProductDetails($item->product_key, $item->notes);
                } else {
                    $this->xrechnung->setDocumentPositionProductDetails($item->product_key);
                }
            } else {
                if (!empty($item->notes)) {
                    $this->xrechnung->setDocumentPositionProductDetails($item->notes);
                } else {
                    $this->xrechnung->setDocumentPositionProductDetails("no product name defined");
                }
            }
            if (isset($item->task_id)) {
                $this->xrechnung->setDocumentPositionQuantity($item->quantity, "HUR");
            } else {
                $this->xrechnung->setDocumentPositionQuantity($item->quantity, "H87");
            }
            $linenetamount = $item->line_total;
            if ($item->discount > 0) {
                if ($this->invoice->is_amount_discount) {
                    $linenetamount -= $item->discount;
                } else {
                    $linenetamount -= $linenetamount * ($item->discount / 100);
                }
            }
            $this->xrechnung->setDocumentPositionLineSummation($linenetamount);
            // According to european law, each line item can only have one tax rate
            if (!(empty($item->tax_name1) && empty($item->tax_name2) && empty($item->tax_name3))) {
                $taxtype = $this->getTaxType($item->tax_id);
                if (!empty($item->tax_name1)) {
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate1);
                    $this->addtoTaxMap($taxtype, $linenetamount, $item->tax_rate1);
                } elseif (!empty($item->tax_name2)) {
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate2);
                    $this->addtoTaxMap($taxtype, $linenetamount, $item->tax_rate2);
                } elseif (!empty($item->tax_name3)) {
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate3);
                    $this->addtoTaxMap($taxtype, $linenetamount, $item->tax_rate3);
                } else {
                    // nlog("Can't add correct tax position");
                }
            } else {
                if (!empty($this->invoice->tax_name1)) {
                    $taxtype = $this->getTaxType($this->invoice->tax_name1);
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', $this->invoice->tax_rate1);
                    $this->addtoTaxMap($taxtype, $linenetamount, $this->invoice->tax_rate1);
                } elseif (!empty($this->invoice->tax_name2)) {
                    $taxtype = $this->getTaxType($this->invoice->tax_name2);
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', $this->invoice->tax_rate2);
                    $this->addtoTaxMap($taxtype, $linenetamount, $this->invoice->tax_rate2);
                } elseif (!empty($this->invoice->tax_name3)) {
                    $taxtype = $this->getTaxType($this->invoice->tax_name3);
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', $this->invoice->tax_rate3);
                    $this->addtoTaxMap($taxtype, $linenetamount, $this->invoice->tax_rate3);
                } else {
                    $taxtype = ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS;
                    $this->xrechnung->addDocumentPositionTax($taxtype, 'VAT', 0);
                    $this->addtoTaxMap($taxtype, $linenetamount, 0);
                    // nlog("Can't add correct tax position");
                }
            }
        }

        $this->xrechnung->setDocumentSummation($this->invoice->amount, $this->invoice->balance, $invoicing_data->getSubTotal(), $invoicing_data->getTotalSurcharges(), $invoicing_data->getTotalDiscount(), $invoicing_data->getSubTotal(), $invoicing_data->getItemTotalTaxes(), 0.0, $this->invoice->amount - $this->invoice->balance);

        foreach ($this->tax_map as $item) {
            $this->xrechnung->addDocumentTax($item["tax_type"], "VAT", $item["net_amount"], $item["tax_rate"] * $item["net_amount"], $item["tax_rate"] * 100);
        }

        // The validity can be checked using https://portal3.gefeg.com/invoice/validation or https://e-rechnung.bayern.de/app/#/upload
        return $this;

    }

    /**
     * Returns the XML document
     * in string format
     *
     * @return string
     */
    public function getXml(): string
    {
        return $this->xrechnung->getContent();
    }

    private function getTaxType($name): string
    {
        $tax_type = null;
        switch ($name) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS;
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE;
                break;
        }
        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE", "IS", "LI", "NO", "CH"];
        if (empty($tax_type)) {
            if ((in_array($this->invoice->company->country()->iso_3166_2, $eu_states) && in_array($this->invoice->client->country->iso_3166_2, $eu_states)) && $this->invoice->company->country()->iso_3166_2 != $this->invoice->client->country->iso_3166_2) {
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            } elseif (!in_array($this->invoice->client->country->iso_3166_2, $eu_states)) {
                $tax_type = ZugferdDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX;
            } elseif ($this->invoice->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = ZugferdDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            } elseif (in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = ZugferdDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            } else {
                nlog("Unkown tax case for xinvoice");
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
            }
        }
        return $tax_type;
    }
    private function addtoTaxMap(string $tax_type, float $net_amount, float $tax_rate): void
    {
        $hash = hash("md5", $tax_type."-".$tax_rate);
        if (array_key_exists($hash, $this->tax_map)) {
            $this->tax_map[$hash]["net_amount"] += $net_amount;
        } else {
            $this->tax_map[$hash] = [
                "tax_type" => $tax_type,
                "net_amount" => $net_amount,
                "tax_rate" => $tax_rate / 100
            ];
        }
    }

}
