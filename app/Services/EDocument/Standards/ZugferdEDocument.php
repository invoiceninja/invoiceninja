<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
use App\Helpers\Invoice\InclusiveTax;
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

    private InvoiceSum|InvoiceSumInclusive $calc;

    private ?string $tax_code = null;

    private ?string $exemption_reason_code = null;

    private ?string $temp_file_path = null;

    /**
     * Upper size limit (bytes) per embedded document.
     * Mirrors Peppol::$max_attachment_size so both standards behave alike.
     */
    public int $max_attachment_size = 2000000;

    /**
     * __construct
     *
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document
     * @param  bool $returnObject
     * @param  array $tax_map
     * @return void
     */
    public function __construct(public \App\Models\Invoice|\App\Models\Quote|\App\Models\PurchaseOrder|\App\Models\Credit $document, private readonly bool $returnObject = false, private array $tax_map = []) {}

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
            ->setDocumentSummation()    // 5. Finally document summation
            ->setSupportingDocuments(); // 6. Supporting documents (BG-24)

        return $this;

    }

    private function setCustomSurcharges(): self
    {
        $tax_groups = $this->buildDocumentTaxGroups();

        foreach ([1, 2, 3, 4] as $index) {
            $amount = (float) $this->document->{"custom_surcharge{$index}"};

            if ($amount <= 0) {
                continue;
            }

            [$tax_category, $tax_rate] = $this->documentSurchargeTaxClassification($index, $tax_groups);
            $surcharge = $this->document->uses_inclusive_taxes && $tax_rate > 0
                ? round($amount / (1 + ($tax_rate / 100)), 2)
                : $amount;

            $this->xdocument->addDocumentAllowanceCharge(
                $surcharge,
                true,
                $tax_category,
                "VAT",
                $tax_rate,
                null,
                null,
                null,
                null,
                null,
                null,
                ctrans('texts.surcharge')
            );
        }

        return $this;
    }

    /**
     * setSupportingDocuments
     *
     * Embeds the documents attached to the invoice into the XML as
     * BG-24 / BT-125 (ram:AdditionalReferencedDocument with an
     * AttachmentBinaryObject).
     *
     * Deliberately limited to documents already stored against the entity.
     * Embedding the rendered invoice PDF itself would reintroduce the
     * PDF => xml => PDF recursion that made the earlier implementation
     * unusable.
     *
     * The conditions mirror PeppolAttachmentBuilder so that CII and UBL
     * behave the same way.
     *
     * @return self
     */
    private function setSupportingDocuments(): self
    {
        if (!$this->document instanceof Invoice && !$this->document instanceof Credit) {
            return $this;
        }

        if (!$this->company->account->hasFeature(\App\Models\Account::FEATURE_DOCUMENTS)) {
            return $this;
        }

        // Deliberately NOT gated on document_email_attachment: whether a
        // supporting document belongs in the data set is independent of
        // whether it should also go out as a second mail attachment. Some
        // recipients require exactly the opposite - embedded, but only one
        // file in the email.
        $this->document
            ->documents()
            ->where('is_public', true)
            ->cursor()
            ->each(function ($document) {

            /** @var \App\Models\Document $document */
                if ($document->size > $this->max_attachment_size) {
                    return;
                }

                if (!in_array($document->getMimeType(), ['application/pdf', 'application/xml'])) {
                    return;
                }

                $file = $document->getFile();

                if (!$file) {
                    return;
                }

                // Sanitised like PeppolAttachmentBuilder does: some intake
                // systems fail on spaces and punctuation in file names.
                $file_name = preg_replace('/[^A-Za-z0-9._-]/', '_', $document->name);

                if (!\Illuminate\Support\Str::endsWith(strtolower($file_name), ['.pdf', '.xml'])) {
                    $file_name .= $document->getMimeType() === 'application/xml' ? '.xml' : '.pdf';
                }

                $path = \App\Utils\TempFile::filePath($file, $file_name);

                $this->xdocument->addDocumentInvoiceSupportingDocumentWithFile(
                    $document->name,
                    $path,
                    $file_name,
                );
            });

        return $this;
    }

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
        $document_discount = $this->getDocumentAllowanceTotalForZugferd();
        $tax_groups = $this->buildDocumentTaxGroups();
        $document_allowances = $this->allocateDocumentAllowanceAmounts(
            $document_discount,
            $this->buildLineTaxGroupBases()
        );

        foreach ($tax_groups as &$group) {
            $group['base_amount'] = round(
                $group['base_amount'] - ($document_allowances[$group['key']] ?? 0.0),
                2
            );
        }
        unset($group);

        $target_net = round((float) $this->document->amount - (float) $this->calc->getTotalTaxes(), 2);
        $tax_groups = $this->reconcileDocumentTaxGroupsToTarget($tax_groups, $target_net);

        $emitted_groups = [];

        foreach ($tax_groups as $group) {
            $base_amount = round($group['base_amount'], 2);

            if ($base_amount <= 0) {
                continue;
            }

            $group['base_amount'] = $base_amount;
            $group['tax_amount'] = round($base_amount * ($group['tax_rate'] / 100), 2);
            $emitted_groups[] = $group;
        }

        $emitted_groups = $this->reconcileDocumentTaxAmountsToTarget(
            $emitted_groups,
            round((float) $this->calc->getTotalTaxes(), 2)
        );

        foreach ($emitted_groups as $group) {
            $this->xdocument->addDocumentTax(
                $group['tax_category'],
                "VAT",
                $group['base_amount'],
                $group['tax_amount'],
                $group['tax_rate'],
                $this->exemptionReasonTextForDutyCategory($group['tax_category']),
                $this->exemptionReasonCodeForDutyCategory($group['tax_category'])
            );

            $allowance_amount = $document_allowances[$group['key']] ?? 0.0;

            if ($allowance_amount > 0) {
                $this->xdocument->addDocumentAllowanceCharge(
                    $allowance_amount,
                    false,
                    $group['tax_category'],
                    "VAT",
                    $group['tax_rate'],
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    ctrans('texts.discount')
                );
            }
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
                'paydate' => $this->document->due_date,
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
            $this->exemption_reason_code = "VATEX-EU-G";
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
        $document_discount = $this->getDocumentAllowanceTotalForZugferd();
        $total_tax = round($this->calc->getTotalTaxes(), 2);
        $taxable_amount = $this->document->amount - $total_tax;
        $base_taxable_amount = $this->calc->getTaxMap()->sum('base_amount');

        $subtotal = $this->getLineNetTotalSumForZugferd();

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
            $this->getDocumentChargeTotalForZugferd(),  // Total charges
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
            $unit_code = $item->type_id == 2 ? "HUR" : "H87";
            $pricing = $this->resolveLinePricing($item);

            // 1. Start new position and set basic details
            $this->xdocument->addNewPosition($position_id)
                ->setDocumentPositionProductDetails(
                    strlen($item->product_key ?? '') >= 1 ? $item->product_key : "no product name defined",
                    $item->notes
                )
                ->setDocumentPositionQuantity(
                    $item->quantity,
                    $unit_code
                )
                ->setDocumentPositionNetPrice(
                    $pricing['price_amount'],
                    $pricing['price_base_quantity'],
                    $pricing['price_base_quantity'] ? $unit_code : null
                );

            // 2. ALWAYS add tax information (even if zero)
            [$duty_category, $rate_percent] = $this->invoiceLineTradeTaxClassification($item);

            $this->xdocument->addDocumentPositionTax(
                $duty_category,
                'VAT',
                $rate_percent
            );

            // 3. Add allowances/charges (discounts) if any
            if ($pricing['allowance_amount'] > 0) {
                $this->xdocument->addDocumentPositionAllowanceCharge(
                    actualAmount: $pricing['allowance_amount'],
                    isCharge: false,
                    calculationPercent: null,
                    basisAmount: null,
                    reasonCode: null,
                    reason: 'Discount'
                );
            }

            // 4. Finally add monetary summation
            $this->xdocument->setDocumentPositionLineSummation($pricing['line_total']);
        }

        return $this;
    }

    /**
     * @return array{
     *     price_amount: float,
     *     price_base_quantity: float|null,
     *     allowance_amount: float,
     *     line_total: float
     * }
     */
    private function resolveLinePricing(object $item): array
    {
        $quantity = (float) $item->quantity;
        $undiscounted_line_total = round((float) $item->cost * $quantity, 2);
        $discounted_line_total = round((float) $item->line_total, 2);

        if ($this->document->uses_inclusive_taxes) {
            $rates = [
                (float) $item->tax_rate1,
                (float) $item->tax_rate2,
                (float) $item->tax_rate3,
            ];

            $undiscounted_line_total = InclusiveTax::backout($undiscounted_line_total, $rates, 2)['net'];
            $discounted_line_total = InclusiveTax::backout($discounted_line_total, $rates, 2)['net'];
        }

        $price_amount = $quantity != 0
            ? round($undiscounted_line_total / $quantity, 2)
            : $undiscounted_line_total;

        $price_base_quantity = null;

        if (
            $quantity != 0
            && abs(round($price_amount * $quantity, 2) - $undiscounted_line_total) > 0.001
        ) {
            $price_amount = $undiscounted_line_total;
            $price_base_quantity = abs($quantity);
        }

        return [
            'price_amount' => round($price_amount, 2),
            'price_base_quantity' => $price_base_quantity,
            'allowance_amount' => $item->discount > 0
                ? max(0, round($undiscounted_line_total - $discounted_line_total, 2))
                : 0.0,
            'line_total' => round($discounted_line_total, 2),
        ];
    }


    private function setCompanyTaxRegistration(): array
    {
        $vat_number = $this->company->getSetting('vat_number');

        if (str_contains($vat_number, "/")) {
            return ["FC", $vat_number];
        }

        $vat_number = $this->addVatCountryPrefix($vat_number, $this->company->country()->iso_3166_2);

        return ["VA", $vat_number];
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

        if (!empty($this->client->shipping_address1) && $this->client->shipping_country_id) {
            $this->xdocument->setDocumentShipTo();
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
            ->setDocumentBuyerContact($this->client->present()->primary_contact_name_or_null(), "", $this->client->present()->phone(), "", $this->client->present()->email())
            ->setDocumentBuyerCommunication("EM", $this->client->present()->email());

        if (!empty($this->document->public_notes)) {
            $this->xdocument->addDocumentNote($this->document->public_notes);
        }

        if (strlen($this->client->vat_number ?? '') > 1) {
            $buyer_vat = $this->addVatCountryPrefix($this->client->vat_number, $this->client->country->iso_3166_2);
            $this->xdocument->addDocumentBuyerTaxRegistration($this->getDocumentLevelTaxRegistration(), $buyer_vat);
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

    /**
     * Ensures a VAT number has an ISO 3166-1 alpha-2 country prefix
     * as required by BR-CO-09.
     */
    private function addVatCountryPrefix(string $vat_number, string $country_code): string
    {
        $vat_number = trim($vat_number);
        $country_code = strtoupper(substr($country_code, 0, 2));

        if (stripos($vat_number, $country_code) === 0) {
            return $vat_number;
        }

        return $country_code . $vat_number;
    }

    private function getLineNetTotalForZugferd(object $item): float
    {
        return $this->resolveLinePricing($item)['line_total'];
    }

    private function getLineNetTotalSumForZugferd(): float
    {
        $line_total = 0.0;

        foreach ($this->document->line_items as $item) {
            $line_total += $this->getLineNetTotalForZugferd($item);
        }

        return round($line_total, 2);
    }

    private function getDocumentAllowanceTotalForZugferd(): float
    {
        $document_discount = round((float) $this->calc->getTotalDiscount(), 2);

        if (! $this->document->uses_inclusive_taxes || $document_discount <= 0) {
            return $document_discount;
        }

        $line_total = $this->getLineNetTotalSumForZugferd();
        $charge_total = $this->getDocumentChargeTotalForZugferd();
        $tax_basis_total = round((float) $this->document->amount - (float) $this->calc->getTotalTaxes(), 2);

        return max(0, round($line_total + $charge_total - $tax_basis_total, 2));
    }

    private function getDocumentChargeTotalForZugferd(): float
    {
        $total = 0.0;
        $tax_groups = $this->buildDocumentTaxGroups();

        foreach ([1, 2, 3, 4] as $index) {
            $amount = (float) $this->document->{"custom_surcharge{$index}"};

            if ($amount <= 0) {
                continue;
            }

            [, $tax_rate] = $this->documentSurchargeTaxClassification($index, $tax_groups);
            $total += $this->document->uses_inclusive_taxes && $tax_rate > 0
                ? round($amount / (1 + ($tax_rate / 100)), 2)
                : $amount;
        }

        return round($total, 2);
    }

    /**
     * @param  array<array-key, float|int>  $weights
     * @return array<array-key, float>
     */
    private function allocateDocumentAllowanceAmounts(float $allowance, array $weights): array
    {
        $total_cents = max(0, (int) round($allowance * 100));
        $normalized_weights = [];
        $allocations = [];

        foreach ($weights as $key => $weight) {
            $weight = (float) $weight;
            $normalized_weights[$key] = is_finite($weight) ? max(0, $weight) : 0.0;
            $allocations[$key] = 0;
        }

        $total_weight = array_sum($normalized_weights);

        if ($total_cents === 0 || empty($allocations)) {
            return array_map(static fn(): float => 0.0, $allocations);
        }

        if ($total_weight <= 0) {
            foreach (array_keys($allocations) as $key) {
                $allocations[$key] = $total_cents;
                break;
            }

            return array_map(static fn(int $cents): float => $cents / 100, $allocations);
        }

        $remainders = [];
        $positions = [];

        foreach ($normalized_weights as $key => $weight) {
            $raw_cents = $total_cents * ($weight / $total_weight);
            $allocated_cents = (int) floor($raw_cents);

            $allocations[$key] = $allocated_cents;
            $remainders[$key] = $raw_cents - $allocated_cents;
            $positions[$key] = count($positions);
        }

        $remaining_cents = $total_cents - array_sum($allocations);
        $allocation_order = array_keys($allocations);

        usort($allocation_order, static function (int|string $left, int|string $right) use ($remainders, $positions): int {
            $remainder_comparison = $remainders[$right] <=> $remainders[$left];

            return $remainder_comparison !== 0
                ? $remainder_comparison
                : $positions[$left] <=> $positions[$right];
        });

        for ($index = 0; $index < $remaining_cents; $index++) {
            $allocations[$allocation_order[$index]]++;
        }

        return array_map(static fn(int $cents): float => $cents / 100, $allocations);
    }

    /**
     * @return array{0: string, 1: float}
     */
    private function invoiceLineTradeTaxClassification(object $item): array
    {
        $tax_id = (string) ($item->tax_id ?? '');
        $has_explicit_tax_category = in_array($tax_id, [
            (string) Product::PRODUCT_TYPE_EXEMPT,
            (string) Product::PRODUCT_TYPE_ZERO_RATED,
            (string) Product::PRODUCT_TYPE_REVERSE_TAX,
            (string) Product::PRODUCT_INTRA_COMMUNITY,
        ], true);

        if (strlen($item->tax_name1 ?? '') > 1 || $has_explicit_tax_category) {
            return [$this->getTaxType($item->tax_id ?? '2'), (float) $item->tax_rate1];
        }

        return [$this->tax_code ?? ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX, 0.0];
    }

    /**
     * @return array<string, float>
     */
    private function buildLineTaxGroupBases(): array
    {
        $groups = [];

        foreach ($this->document->line_items as $item) {
            [$tax_category, $tax_rate] = $this->invoiceLineTradeTaxClassification($item);
            $this->addDocumentTaxGroup(
                $groups,
                $tax_category,
                $tax_rate,
                $this->getLineNetTotalForZugferd($item)
            );
        }

        return array_column(array_values($groups), 'base_amount', 'key');
    }

    private function buildDocumentTaxGroups(): array
    {
        $groups = [];

        foreach ($this->document->line_items as $item) {
            [$tax_category, $tax_rate] = $this->invoiceLineTradeTaxClassification($item);
            $this->addDocumentTaxGroup(
                $groups,
                $tax_category,
                $tax_rate,
                $this->getLineNetTotalForZugferd($item)
            );
        }

        foreach ([1, 2, 3, 4] as $index) {
            $amount = (float) $this->document->{"custom_surcharge{$index}"};

            if ($amount <= 0) {
                continue;
            }

            [$tax_category, $tax_rate] = $this->documentSurchargeTaxClassification($index, $groups);
            $net_amount = $this->document->uses_inclusive_taxes && $tax_rate > 0
                ? round($amount / (1 + ($tax_rate / 100)), 2)
                : $amount;

            $this->addDocumentTaxGroup($groups, $tax_category, $tax_rate, $net_amount);
        }

        return array_values($groups);
    }

    /**
     * @param array<string, array{
     *     key: string,
     *     tax_category: string,
     *     tax_rate: float,
     *     base_amount: float
     * }> $groups
     */
    private function addDocumentTaxGroup(
        array &$groups,
        string $tax_category,
        float $tax_rate,
        float $base_amount
    ): void {
        $key = $tax_category . '|' . number_format($tax_rate, 6, '.', '');

        if (! isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'tax_category' => $tax_category,
                'tax_rate' => $tax_rate,
                'base_amount' => 0.0,
            ];
        }

        $groups[$key]['base_amount'] += $base_amount;
    }

    /**
     * @param array<int|string, array{
     *     key: string,
     *     tax_category: string,
     *     tax_rate: float,
     *     base_amount: float
     * }> $tax_groups
     * @return array{0: string, 1: float}
     */
    private function documentSurchargeTaxClassification(int $index, array $tax_groups): array
    {
        if (! (bool) $this->document->{"custom_surcharge_tax{$index}"}) {
            return [ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX, 0.0];
        }

        foreach ($tax_groups as $group) {
            if ($group['tax_rate'] > 0) {
                return [$group['tax_category'], $group['tax_rate']];
            }
        }

        return [
            $this->tax_code ?? ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX,
            0.0,
        ];
    }

    /**
     * @param array<int, array{
     *     key: string,
     *     tax_category: string,
     *     tax_rate: float,
     *     base_amount: float
     * }> $tax_groups
     * @return array<int, array{
     *     key: string,
     *     tax_category: string,
     *     tax_rate: float,
     *     base_amount: float
     * }>
     */
    private function reconcileDocumentTaxGroupsToTarget(array $tax_groups, float $target_net): array
    {
        if (empty($tax_groups)) {
            $duty = $this->tax_code ?? ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;

            return [[
                'key' => $duty . '|0.000000',
                'tax_category' => $duty,
                'tax_rate' => 0.0,
                'base_amount' => $target_net,
            ]];
        }

        $sum = round(array_sum(array_column($tax_groups, 'base_amount')), 2);
        $adjustment = round($target_net - $sum, 2);

        if (abs($adjustment) >= 0.009) {
            $largest_group_index = array_keys(
                array_column($tax_groups, 'base_amount'),
                max(array_column($tax_groups, 'base_amount')),
                true
            )[0];
            $tax_groups[$largest_group_index]['base_amount'] = round(
                $tax_groups[$largest_group_index]['base_amount'] + $adjustment,
                2
            );
        }

        return $tax_groups;
    }

    /**
     * @param array<int, array{
     *     key: string,
     *     tax_category: string,
     *     tax_rate: float,
     *     base_amount: float,
     *     tax_amount: float
     * }> $tax_groups
     * @return array<int, array{
     *     key: string,
     *     tax_category: string,
     *     tax_rate: float,
     *     base_amount: float,
     *     tax_amount: float
     * }>
     */
    private function reconcileDocumentTaxAmountsToTarget(array $tax_groups, float $target_tax): array
    {
        if (empty($tax_groups)) {
            return $tax_groups;
        }

        $adjustment = round($target_tax - array_sum(array_column($tax_groups, 'tax_amount')), 2);

        if (abs($adjustment) < 0.009 || abs($adjustment) > 1.0) {
            return $tax_groups;
        }

        $taxed_indexes = [];

        foreach ($tax_groups as $index => $group) {
            if ($group['tax_rate'] > 0) {
                $taxed_indexes[] = $index;
            }
        }

        if (empty($taxed_indexes)) {
            return $tax_groups;
        }

        $largest_group_index = $taxed_indexes[0];

        foreach ($taxed_indexes as $index) {
            if ($tax_groups[$index]['tax_amount'] > $tax_groups[$largest_group_index]['tax_amount']) {
                $largest_group_index = $index;
            }
        }

        $tax_groups[$largest_group_index]['tax_amount'] = round(
            $tax_groups[$largest_group_index]['tax_amount'] + $adjustment,
            2
        );

        return $tax_groups;
    }

    private function exemptionReasonCodeForDutyCategory(string $duty_category): ?string
    {
        return match ($duty_category) {
            ZugferdDutyTaxFeeCategories::FREE_EXPORT_ITEM_TAX_NOT_CHARGED => 'VATEX-EU-G',
            ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE => 'VATEX-EU-AE',
            ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES => 'VATEX-EU-IC',
            ZugferdDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX => 'VATEX-EU-O',
            ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX => $this->exemption_reason_code,
            default => null,
        };
    }

    private function exemptionReasonTextForDutyCategory(string $duty_category): ?string
    {
        if ($duty_category == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES) {
            return ctrans('texts.intracommunity_tax_info');
        }

        if ($duty_category == ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX) {
            return ctrans('texts.tax_exempt');
        }

        return null;
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
