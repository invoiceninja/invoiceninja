<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards;

use DateTime;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\DataMapper\InvoiceItem;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use horstoeko\zugferd\ZugferdProfiles;
use App\Helpers\Invoice\InvoiceSumInclusive;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\codelists\ZugferdDocumentType;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;

class ZugferdEDocument extends AbstractService
{
    public ZugferdDocumentBuilder $xdocument;

    private Company $company;

    private Client $client;

    private InvoiceSum | InvoiceSumInclusive $calc;

    private ?string $tax_code = null;

    private ?string $exemption_reason_code = null;

    private ?string $temp_file_path = null;

    /**
     * __construct
     *
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document
     * @param  bool $returnObject
     * @param  array $tax_map
     * @return void
     */
    public function __construct(public \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document, private readonly bool $returnObject = false, private array $tax_map = [])
    {
    }

    public function run(): self
    {

        $this->company = $this->document->company;

        $this->client = $this->document->client;

        $profile = $this->client->getSetting('e_invoice_type');

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

        $this->xdocument = ZugferdDocumentBuilder::CreateNew($profile);


        $this->bootFlags()
            ->setBaseDocument()
            ->setDocumentInformation()
            ->setPoNumber()
            ->setRoutingNumber()
            ->setIdNumber()
            ->setDeliveryAddress()
            ->setDocumentTaxes()        // 1. First set taxes
            ->setPaymentMeans()         // 2. Then payment means
            ->setPaymentTerms()         // 3. Then payment terms
            ->setLineItems()            // 4. Then line items
            ->setCustomSurcharges()     // 4a. Surcharges
            ->setDocumentSummation();   // 5. Finally document summation
        // ->setAdditionalReferencedDocument();   // 6. Additional referenced document

        return $this;

    }

    private function setCustomSurcharges(): self
    {
        $item = $this->calc->getTaxMap()->first() ?: ['tax_rate' => 0, 'tax_id' => null];

        $tax_code = $item['tax_id'] ? $this->getTaxType($item["tax_id"] ?? '2') : $this->tax_code;

        if ($this->document->custom_surcharge1 > 0) {
            $surcharge = $this->document->uses_inclusive_taxes ? ($this->document->custom_surcharge1 / (1 + ($item["tax_rate"] / 100))) : $this->document->custom_surcharge1;
            $this->xdocument->addDocumentAllowanceCharge($surcharge, true, $tax_code, "VAT", $item["tax_rate"], null, null, null, null, null, null, ctrans('texts.surcharge'));
        }

        if ($this->document->custom_surcharge2 > 0) {
            $surcharge = $this->document->uses_inclusive_taxes ? ($this->document->custom_surcharge2 / (1 + ($item["tax_rate"] / 100))) : $this->document->custom_surcharge2;
            $this->xdocument->addDocumentAllowanceCharge($surcharge, true, $tax_code, "VAT", $item["tax_rate"], null, null, null, null, null, null, ctrans('texts.surcharge'));
        }

        if ($this->document->custom_surcharge3 > 0) {
            $surcharge = $this->document->uses_inclusive_taxes ? ($this->document->custom_surcharge3 / (1 + ($item["tax_rate"] / 100))) : $this->document->custom_surcharge3;
            $this->xdocument->addDocumentAllowanceCharge($surcharge, true, $tax_code, "VAT", $item["tax_rate"], null, null, null, null, null, null, ctrans('texts.surcharge'));
        }

        if ($this->document->custom_surcharge4 > 0) {
            $surcharge = $this->document->uses_inclusive_taxes ? ($this->document->custom_surcharge4 / (1 + ($item["tax_rate"] / 100))) : $this->document->custom_surcharge4;
            $this->xdocument->addDocumentAllowanceCharge($surcharge, true, $tax_code, "VAT", $item["tax_rate"], null, null, null, null, null, null, ctrans('texts.surcharge'));
        }

        return $this;
    }

    /**
     * setAdditionalReferencedDocument
     *
     * circular reference causing the file to never be created.
     * PDF => xml => PDF => xml
     *
     * Need to abstract the insertion of the base64 document into the XML.
     *
     * @return self
     */
    // private function setAdditionalReferencedDocument(): self
    // {
    //     if($this->document->client->getSetting('merge_e_invoice_to_pdf')) {
    //         return $this;
    //     }

    //     $invitation = $this->document->invitations()->first();
    //     $pdf = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
    //     $file_name = $this->document->numberFormatter().'.pdf';

    //     $this->temp_file_path = \App\Utils\TempFile::filePath($pdf, $file_name);

    //     $this->xdocument->addDocumentInvoiceSupportingDocumentWithFile(
    //         $this->document->number,
    //         $this->temp_file_path,
    //         $file_name,
    //     );

    //     return $this;
    // }

    /**
     * setDocumentTaxes
     *
     * VATEX-EU-143     - Article 143 - Exemptions on importation
     * VATEX-EU-146     - Article 146 - Exemptions on exportation
     * VATEX-EU-148     - Article 148 - Exemptions for international transport
     * VATEX-EU-151     - Article 151 - Exemptions for certain transactions
     * VATEX-EU-169     - Article 169 - Right of deduction
     * VATEX-EU-AE      - Reverse charge - VAT to be paid by the recipient
     * VATEX-EU-D       - Triangulation rule - Intra-EU supply
     * VATEX-EU-F       - Free export item, tax not charged
     * VATEX-EU-G       - Export outside the EU
     * VATEX-EU-IC      - Intra-Community supply
     * VATEX-EU-O       - Outside scope of tax
     * VATEX-EU-IC-SC   - Intra-Community supply of services to customer in another member state
     * VATEX-EU-AE-SC   - Services to customer outside the EU
     * VATEX-EU-NOT-TAX - Not subject to VAT
     *
     * @return self
     */
    private function setDocumentTaxes(): self
    {

        if ((string) $this->document->total_taxes == '0') {

            $base_amount = 0;
            $tax_amount = 0;
            $tax_rate = 0;

            if (in_array($this->tax_code, [ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE, ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX])) { //reverse charge
                $base_amount = $this->document->amount;
            }

            $this->xdocument->addDocumentTax(
                $this->tax_code,
                "VAT",
                $base_amount,
                $tax_amount,
                $tax_rate,
                null,
                $this->exemption_reason_code
            );


            if ($this->calc->getTotalDiscount() > 0) {

                $this->xdocument->addDocumentAllowanceCharge(
                    $this->calc->getTotalDiscount(),
                    false,
                    $this->tax_code,
                    "VAT",
                    0,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    ctrans('texts.discount')
                );
            }

            return $this;
        }

        $tax_map = $this->calc->getTaxMap();
        $net_subtotal = $tax_map->sum('base_amount');

        $total_tax = $this->calc->getTotalTaxes();
        $taxable_amount = $this->document->amount - $total_tax;

        //taxable amount and net subtotal should be the same
        $adjustment = round($taxable_amount - $net_subtotal, 2);

        // Process each tax rate group
        foreach ($tax_map as $item) {
            $tax_type = $this->getTaxType($item["tax_id"]);
            // Add tax information
            $this->xdocument->addDocumentTax(
                $tax_type,
                "VAT",
                $item["base_amount"] + $adjustment, // Taxable amount after discount
                $item["total"],
                $item["tax_rate"],
                $tax_type == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES
                    ? ctrans('texts.intracommunity_tax_info')
                    : ''
            );

            if ($this->calc->getTotalDiscount() > 0) {

                $ratio = $item["base_amount"] / $net_subtotal;

                $this->xdocument->addDocumentAllowanceCharge(
                    round($this->calc->getTotalDiscount() * $ratio, 2),
                    false,
                    $this->getTaxType($item["tax_id"] ?? '2'),
                    "VAT",
                    $item["tax_rate"],
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    ctrans('texts.discount')
                );
            }

            $adjustment = 0;
        }

        return $this;
    }

    private function setPaymentTerms(): self
    {
        $this->xdocument->addDocumentPaymentTerm(
            ctrans("texts.xinvoice_payable", [
                'payeddue' => date_create($this->document->date ?? now()->format('Y-m-d'))
                    ->diff(date_create($this->document->due_date ?? now()->format('Y-m-d')))
                    ->format("%d"),
                'paydate' => $this->document->due_date
            ])
        );

        return $this;
    }

    public function getDocument()
    {
        return $this->xdocument;
    }

    public function getXml(): string
    {
        $xml = $this->xdocument->getContent();

        //used if we are embedding the document within the PDF
        if ($this->temp_file_path) {
            unlink($this->temp_file_path);
        }

        return $xml;
    }

    private function bootFlags(): self
    {

        $this->calc = $this->document->calc();

        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_states = $br->eu_country_codes;

        $item = $this->document->line_items[0] ?? null;

        if (is_null($item)) {
            $this->tax_code = ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
            return $this;
        }

        if (!in_array($this->document->client->country->iso_3166_2, $eu_states)) {
            $this->tax_code = ZugferdDutyTaxFeeCategories::FREE_EXPORT_ITEM_TAX_NOT_CHARGED;
            $exemption_reason_code = "VATEX-EU-G";
        } elseif ($this->client->is_tax_exempt || $item->tax_id == '5' || $item->tax_id == '8') {
            $this->tax_code =  ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
            // $this->exemption_reason_code = "VATEX-EU-NOT-TAX";
            $this->exemption_reason_code = "VATEX-EU-O";
            // nlog("exemption_reason_code: {$this->exemption_reason_code}");
        } elseif ($item->tax_id == '9') { //reverse charge
            $this->tax_code = ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE;
            $this->exemption_reason_code = "VATEX-EU-AE";
        } elseif ($item->tax_id == '10') { //intra-community
            $this->tax_code = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            $this->exemption_reason_code = "VATEX-EU-IC";
        } else {
            $this->tax_code = ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
            $this->exemption_reason_code = "VATEX-EU-O";
        }

        return $this;

    }

    private function setDocumentSummation(): self
    {
        $document_discount = $this->calc->getTotalDiscount();
        $total_tax = round($this->calc->getTotalTaxes(), 2);
        $taxable_amount = $this->document->amount - $total_tax;
        $base_taxable_amount = $this->calc->getTaxMap()->sum('base_amount');

        $subtotal = $this->document->uses_inclusive_taxes ? ($this->calc->getTotal() - $total_tax - $this->calc->getTotalNetSurcharges() + $this->calc->getTotalDiscount()) : ($this->calc->getSubTotal());

        // nlog([
        //      $this->document->amount,                    // Total amount with VAT
        //     $this->document->balance,                   // Amount due
        //     $subtotal,                                  // Sum before tax
        //     $this->calc->getTotalSurcharges(),         // Total charges
        //     $document_discount,                         // Total allowances
        //     $taxable_amount,                           // Tax basis total (net)
        //     $total_tax,                                // Total tax amount
        //     0,
        //     // round($this->document->amount - ($base_taxable_amount+$total_tax),2),                                       // Total prepaid amount
        //     $this->document->amount - $this->document->balance,
        // ]);

        $this->xdocument->setDocumentSummation(
            $this->document->amount,                    // Total amount with VAT
            $this->document->balance,                   // Amount due
            $subtotal,                                  // Sum before tax
            $this->document->uses_inclusive_taxes ? $this->calc->getTotalNetSurcharges() : $this->calc->getTotalSurcharges(),         // Total charges
            $document_discount,                         // Total allowances
            $taxable_amount,                           // Tax basis total (net)
            round($total_tax, 2),                       // Total tax amount
            0,
            // round($this->document->amount - ($base_taxable_amount+$total_tax),2),                                       // Total rounding amount
            $this->document->amount - $this->document->balance  // Amount already paid
        );

        return $this;
    }

    private function setLineItems(): self
    {
        foreach ($this->document->line_items as $index => $item) {
            /** @var InvoiceItem $item **/

            $position_id = (string) ($index + 1);
            // 1. Start new position and set basic details
            $this->xdocument->addNewPosition($position_id)
                ->setDocumentPositionProductDetails(
                    strlen($item->product_key ?? '') >= 1 ? $item->product_key : "no product name defined",
                    $item->notes
                )
                ->setDocumentPositionQuantity(
                    $item->quantity,
                    $item->type_id == 2 ? "HUR" : "H87"
                )
                ->setDocumentPositionNetPrice(
                    $this->document->uses_inclusive_taxes ? $item->net_cost : $item->cost
                );

            // 2. ALWAYS add tax information (even if zero)
            if (strlen($item->tax_name1) > 1) {
                $this->xdocument->addDocumentPositionTax(
                    $this->getTaxType($item->tax_id ?? '2'),
                    'VAT',
                    $item->tax_rate1
                );
            } else {
                // Add zero tax if no tax is specified
                $this->xdocument->addDocumentPositionTax(
                    ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX,
                    'VAT',
                    0
                );
            }

            $line_discount = 0;

            // 3. Add allowances/charges (discounts) if any
            if ($item->discount > 0) {
                $line_discount = $this->calculateTotalItemDiscountAmount($item);
                $this->xdocument->addDocumentPositionGrossPriceAllowanceCharge(
                    abs($line_discount),
                    false
                );
            }
            // 4. Finally add monetary summation
            $this->xdocument->setDocumentPositionLineSummation($this->document->uses_inclusive_taxes ? ($item->line_total - $item->tax_amount) : $item->line_total);
        }

        return $this;
    }

    private function calculateTotalItemDiscountAmount($item): float
    {
        if ($item->is_amount_discount) {
            return $item->discount;
        }

        return ($item->cost * $item->quantity) * ($item->discount / 100);
    }


    private function setCompanyTaxRegistration(): array
    {
        if (str_contains($this->company->getSetting('vat_number'), "/")) {
            return ["FC", $this->company->getSetting('vat_number')];
        }

        return ["VA", $this->company->getSetting('vat_number')];
    }

    private function setPaymentMeans(): self
    {

        /**Check if the e_invoice object is populated */
        if (isset($this->company->e_invoice->Invoice->PaymentMeans) && ($pm = $this->company->e_invoice->Invoice->PaymentMeans[0] ?? false)) {

            switch ($pm->PaymentMeansCode->value ?? false) {
                case '30':
                case '58':
                    $iban = $pm->PayeeFinancialAccount->ID->value;
                    $name = $pm->PayeeFinancialAccount->Name ?? '';
                    $bic = $pm->PayeeFinancialAccount->FinancialInstitutionBranch->FinancialInstitution->ID->value ?? '';
                    $typecode = $pm->PaymentMeansCode->value;

                    $this->xdocument->addDocumentPaymentMean(typeCode: $typecode, payeeIban: $iban, payeeAccountName: $name, payeeBic: $bic);

                    return $this;

                default:
                    # code...
                    break;
            }

        }

        //Otherwise default to the "old style"

        $custom_value1 = $this->company->settings->custom_value1;
        //BR-DE-23 - If „Payment means type code“ (BT-81) contains a code for credit transfer (30, 58), „CREDIT TRANSFER“ (BG-17) shall be provided.
        //Payment Means - Switcher
        if (isset($custom_value1) && !empty($custom_value1) && ($custom_value1 == '30' || $custom_value1 == '58')) {
            $this->xdocument->addDocumentPaymentMean(typeCode: $this->company->settings->custom_value1, payeeIban: $this->company->settings->custom_value2, payeeAccountName: $this->company->settings->custom_value4, payeeBic: $this->company->settings->custom_value3);
        } else {
            $this->xdocument->addDocumentPaymentMean('68', ctrans("texts.xinvoice_online_payment"));
        }

        return $this;

    }

    private function setDeliveryAddress(): self
    {

        if (isset($this->client->shipping_address1) && $this->client->shipping_country) {
            $this->xdocument->setDocumentShipToAddress(
                $this->client->shipping_address1,
                $this->client->shipping_address2,
                "",
                $this->client->shipping_postal_code,
                $this->client->shipping_city,
                $this->client->shipping_country->iso_3166_2,
                $this->client->shipping_state
            );
        }

        if (isset($this->document->e_invoice->Invoice->Delivery[0]->ActualDeliveryDate)) {
            $this->xdocument->setDocumentSupplyChainEvent(new \DateTime($this->document->e_invoice->Invoice->Delivery[0]->ActualDeliveryDate));
        }

        return $this;
    }

    private function setDocumentInformation(): self
    {
        $this->xdocument->setDocumentInformation(
            $this->getDocumentNumber(),
            $this->getDocumentType(),
            $this->getDocumentDate(),
            $this->getDocumentCurrency()
        );

        return $this;
    }

    private function setBaseDocument(): self
    {

        $user_or_company_phone = strlen($this->company->present()->phone()) > 3 ? $this->company->present()->phone() : $this->document->user->present()->phone();

        $company_tax_registration = $this->setCompanyTaxRegistration();

        $this->xdocument
            ->setDocumentSupplyChainEvent($this->getDocumentDate())
            ->setDocumentSeller($this->company->getSetting('name'))
            ->setDocumentSellerAddress($this->company->getSetting("address1"), $this->company->getSetting("address2"), "", $this->company->getSetting("postal_code"), $this->company->getSetting("city"), $this->company->country()->iso_3166_2, $this->company->getSetting("state"))
            ->setDocumentSellerContact($this->document->user->present()->getFullName(), "", $user_or_company_phone, "", $this->document->user->email)
            ->setDocumentSellerCommunication("EM", $this->document->user->email)
            ->addDocumentSellerTaxRegistration($company_tax_registration[0], $company_tax_registration[1])
            ->setDocumentBuyer($this->client->present()->name(), $this->client->number)
            ->setDocumentBuyerAddress($this->client->address1, "", "", $this->client->postal_code, $this->client->city, $this->client->country->iso_3166_2, $this->client->state)
            ->setDocumentBuyerContact($this->client->present()->primary_contact_name(), "", $this->client->present()->phone(), "", $this->client->present()->email())
            ->setDocumentBuyerCommunication("EM", $this->client->present()->email())
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($this->document->date ?? now()->format('Y-m-d'))->diff(date_create($this->document->due_date ?? now()->format('Y-m-d')))->format("%d"), 'paydate' => $this->document->due_date]));

        if (strlen($this->client->vat_number ?? '') > 1) {
            $this->xdocument->addDocumentBuyerTaxRegistration($this->getDocumentLevelTaxRegistration(), $this->client->vat_number);
        }

        return $this;
    }

    private function setRoutingNumber(): self
    {
        if (empty($this->client->routing_id)) {
            $this->xdocument->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        } else {
            $this->xdocument->setDocumentBuyerReference($this->client->routing_id)
                 ->setDocumentBuyerCommunication("0204", $this->client->routing_id);
        }
        return $this;
    }

    private function setPoNumber(): self
    {
        if (isset($this->document->po_number) && strlen($this->document->po_number) > 1) {
            $this->xdocument->setDocumentBuyerOrderReferencedDocument($this->document->po_number);
        }

        return $this;
    }

    private function setIdNumber(): self
    {
        $id_number = $this->company->getSetting('id_number');
        
        if (!empty($id_number) && str_contains($id_number, "/")) {
            $id_number = trim($id_number);
            
            // BT-29: Seller identifier
            $this->xdocument->addDocumentSellerGlobalId($id_number, "0088");
            
            // BT-32: Tax registration identifier
            $this->xdocument->addDocumentSellerTaxRegistration("FC", $id_number);
        }

        return $this;
    }

    //////////////////Getters//////////////////
    private function getDocumentNumber(): string
    {
        return empty($this->document->number) ? "DRAFT" : $this->document->number;
    }

    private function getDocumentType(): string
    {
        return match (get_class($this->document)) {
            Quote::class => ZugferdDocumentType::CONTRACT_PRICE_QUOTE,
            Invoice::class => ZugferdDocumentType::COMMERCIAL_INVOICE,
            Credit::class => ZugferdDocumentType::CREDIT_NOTE,
            default => ZugferdDocumentType::COMMERCIAL_INVOICE,
        };
    }

    private function getDocumentDate(): DateTime
    {
        return date_create($this->document->date ?? now()->format('Y-m-d'));
    }

    private function getDocumentCurrency(): string
    {
        return $this->client->getCurrencyCode();
    }

    private function getDocumentLevelTaxRegistration(): string
    {
        return strlen($this->client->vat_number ?? '') > 1 ? "VA" : "FC";
    }


    private function getIdNumber(): ?string
    {
        return !empty($this->company->getSetting('id_number')) 
            ? trim($this->company->getSetting('id_number')) 
            : null;
    }

    private function getIdNumberRegistrationType(): ?string
    {
        return !empty($this->getIdNumber()) && str_contains($this->getIdNumber(), "/") 
            ? "FC" 
            : null;
    }

    private function getTaxType(string $tax_id): string
    {

        switch ($tax_id) {
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

            default:
                $tax_type = null;
                break;
        }

        if ($this->client->is_tax_exempt) {
            $tax_type = ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
        }

        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_states = $br->eu_country_codes;

        if (empty($tax_type)) {
            if ((in_array($this->company->country()->iso_3166_2, $eu_states) && in_array($this->client->country->iso_3166_2, $eu_states)) && $this->company->country()->iso_3166_2 != $this->client->country->iso_3166_2) {
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            } elseif (!in_array($this->document->client->country->iso_3166_2, $eu_states)) {
                $tax_type = ZugferdDutyTaxFeeCategories::FREE_EXPORT_ITEM_TAX_NOT_CHARGED;
            } elseif ($this->document->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = ZugferdDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            } elseif (in_array($this->document->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = ZugferdDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            } else {
                // nlog("Unkown tax case for xinvoice");
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
            }
        }

        return $tax_type;
    }

}
