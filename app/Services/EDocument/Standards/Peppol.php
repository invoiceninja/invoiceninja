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

use App\Models\Credit;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Helpers\Invoice\Taxer;
use App\Utils\Traits\MakesHash;
use App\DataMapper\Tax\BaseRule;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Services\EDocument\Standards\Peppol\PeppolLineBuilder;
use App\Services\EDocument\Standards\Peppol\PeppolTaxCalculator;
use App\Services\EDocument\Standards\Peppol\PeppolPartyBuilder;
use App\Services\EDocument\Standards\Peppol\PeppolAttachmentBuilder;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\BillingReferenceType\BillingReference;

class Peppol extends AbstractService
{
    use Taxer;
    use NumberFormatter;
    use MakesHash;

    /**
     * Assumptions:
     *
     * Line Item Taxes Only
     * Exclusive Taxes
     *
     */
    public int $max_attachment_size = 2000000;

    private string $override_vat_number = '';

    /** @var array $InvoiceTypeCodes */
    private array $InvoiceTypeCodes = [
        "380" => "Commercial invoice",
        "381" => "Credit note",
        "383" => "Corrected invoice",
        "384" => "Prepayment invoice",
        "386" => "Proforma invoice",
        "875" => "Self-billed invoice",
        "976" => "Factored invoice",
        "84" => "Invoice for cross border services",
        "82" => "Simplified invoice",
        "80" => "Debit note",
        "875" => "Self-billed credit note",
        "896" => "Debit note related to self-billed invoice",
    ];

    /** @var array $tax_codes */
    private array $tax_codes = [
        'AE' => [
            'name' => 'Vat Reverse Charge',
            'description' => 'Code specifying that the standard VAT rate is levied from the invoicee.',
        ],
        'E' => [
            'name' => 'Exempt from Tax',
            'description' => 'Code specifying that taxes are not applicable.',
        ],
        'S' => [
            'name' => 'Standard rate',
            'description' => 'Code specifying the standard rate.',
        ],
        'Z' => [
            'name' => 'Zero rated goods',
            'description' => 'Code specifying that the goods are at a zero rate.',
        ],
        'G' => [
            'name' => 'Free export item, VAT not charged',
            'description' => 'Code specifying that the item is free export and taxes are not charged.',
        ],
        'O' => [
            'name' => 'Services outside scope of tax',
            'description' => 'Code specifying that taxes are not applicable to the services.',
        ],
        'K' => [
            'name' => 'VAT exempt for EEA intra-community supply of goods and services',
            'description' => 'A tax category code indicating the item is VAT exempt due to an intra-community supply in the European Economic Area.',
        ],
        'L' => [
            'name' => 'Canary Islands general indirect tax',
            'description' => 'Impuesto General Indirecto Canario (IGIC) is an indirect tax levied on goods and services supplied in the Canary Islands (Spain) by traders and professionals, as well as on import of goods.',
        ],
        'M' => [
            'name' => 'Tax for production, services and importation in Ceuta and Melilla',
            'description' => 'Impuesto sobre la Producción, los Servicios y la Importación (IPSI) is an indirect municipal tax, levied on the production, processing and import of all kinds of movable tangible property, the supply of services and the transfer of immovable property located in the cities of Ceuta and Melilla.',
        ],
        'B' => [
            'name' => 'Transferred (VAT), In Italy',
            'description' => 'VAT not to be paid to the issuer of the invoice but directly to relevant tax authority. This code is allowed in the EN 16931 for Italy only based on the Italian A-deviation.',
        ],
    ];

    private Company $company;

    private InvoiceSum|InvoiceSumInclusive $calc;

    /** @var \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote */
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private EInvoice $e;

    /** @var bool Flag to indicate if document is a Credit Note */
    private bool $isCreditNote = false;

    private string $api_network = Storecove::class; // Storecove::class;

    public Storecove $gateway;

    private string $customizationID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private string $profileID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    private array $tax_map = [];

    private float $allowance_total = 0;

    private $globalTaxCategories;

    private string $tax_category_id = 'S';

    private bool $has_category_O = false;

    private array $errors = [];

    /** @var PeppolTaxCalculator */
    private PeppolTaxCalculator $taxCalculator;

    /** @var PeppolLineBuilder */
    private PeppolLineBuilder $lineBuilder;

    /** @var PeppolPartyBuilder */
    private PeppolPartyBuilder $partyBuilder;

    /** @var PeppolAttachmentBuilder */
    private PeppolAttachmentBuilder $attachmentBuilder;

    public function __construct(public Invoice|Credit $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->e = new EInvoice();
        $this->gateway = new $this->api_network();
        $this->isCreditNote = $this->shouldBeCreditNote();

        $this->taxCalculator = new PeppolTaxCalculator($this);
        $this->lineBuilder = new PeppolLineBuilder($this);
        $this->partyBuilder = new PeppolPartyBuilder($this);
        $this->attachmentBuilder = new PeppolAttachmentBuilder($this);

        $this->setSettings()->setInvoice();
    }

    /////////////////  Accessor Methods for Builders /////////////////////////

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function getInvoiceModel(): Invoice|Credit
    {
        return $this->invoice;
    }

    public function getCalc(): InvoiceSum|InvoiceSumInclusive
    {
        return $this->calc;
    }

    public function getPeppolDocument(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
    {
        return $this->p_invoice;
    }

    public function setPeppolDocument($doc): void
    {
        $this->p_invoice = $doc;
    }

    public function getGateway(): Storecove
    {
        return $this->gateway;
    }

    public function getGlobalTaxCategories()
    {
        return $this->globalTaxCategories;
    }

    public function setGlobalTaxCategories($cats): void
    {
        $this->globalTaxCategories = $cats;
    }

    public function getTaxCategoryId(): string
    {
        return $this->tax_category_id;
    }

    public function setTaxCategoryId($id): void
    {
        $this->tax_category_id = $id;

        if ($id === 'O') {
            $this->has_category_O = true;
        }
    }

    public function hasCategoryO(): bool
    {
        return $this->has_category_O;
    }

    public function getOverrideVatNumber(): string
    {
        return $this->override_vat_number;
    }

    public function setOverrideVatNumber($vat): void
    {
        $this->override_vat_number = $vat;
    }

    public function getTaxMap(): array
    {
        return $this->tax_map;
    }

    public function addToTaxMap(array $entry): void
    {
        $this->tax_map[] = $entry;
    }

    public function addToAllowanceTotal(float $amount): void
    {
        $this->allowance_total += $amount;
    }

    public function isCreditNoteDocument(): bool
    {
        return $this->isCreditNote;
    }

    public function getTaxCalculator(): PeppolTaxCalculator
    {
        return $this->taxCalculator;
    }

    /////////////////  End Accessor Methods /////////////////////////

    /**
     * Determine if the document should be a Credit Note
     *
     * Credit Note is used when:
     * - The entity is a Credit model
     * - The entity is an Invoice with a negative amount
     *
     * @return bool
     */
    private function shouldBeCreditNote(): bool
    {
        // Credit model = always credit note
        if ($this->invoice instanceof Credit) {
            return true;
        }

        // Negative invoice = credit note
        if ($this->invoice instanceof Invoice && $this->invoice->amount < 0) {
            return true;
        }

        return false;
    }

    /**
     * Normalize amount for credit notes
     *
     * Credit notes must have positive values - the document type
     * itself indicates it's a credit. This method ensures all
     * amounts are positive when building a credit note.
     *
     * @param float|int|string $amount
     * @return float
     */
    public function normalizeAmount(float|int|string $amount): float
    {
        $value = (float) $amount;
        return $this->isCreditNote ? abs($value) : $value;
    }

    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {
        try {
            $this->taxCalculator->getJurisdiction(); //Sets the nexus object into the Peppol document.
            $this->taxCalculator->getAllUsedTaxes(); //Maps all used line item taxes

            /** Invoice Level Props */
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomizationID();
            $id->value = $this->customizationID;
            $this->p_invoice->CustomizationID = $id;

            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ProfileID();
            $id->value = $this->profileID;
            $this->p_invoice->ProfileID = $id;

            // Set ID - for CreditNote it expects an ID object
            $docId = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
            $docId->value = $this->invoice->number;
            $this->p_invoice->ID = $docId;

            // $this->p_invoice->ID = $this->invoice->number;

            $this->p_invoice->IssueDate = new \DateTime($this->invoice->date);

            if ($this->invoice->due_date && !$this->isCreditNote) {
                $this->p_invoice->DueDate = new \DateTime($this->invoice->due_date);
            }

            if (strlen($this->invoice->public_notes ?? '') > 0) {
                $this->p_invoice->Note = strip_tags($this->invoice->public_notes);
            }

            $this->p_invoice->DocumentCurrencyCode = $this->invoice->client->currency()->code;

            if ($this->invoice->project_id) {
                $pr = new \InvoiceNinja\EInvoice\Models\Peppol\ProjectReferenceType\ProjectReference();
                $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
                $id->value = $this->invoice->project->number;
                $pr->ID = $id;
                $this->p_invoice->ProjectReference = [$pr];
            }

            /** Set type code and line items based on document type */
            if ($this->isCreditNote) {
                $this->p_invoice->CreditNoteTypeCode = 381;
                $this->p_invoice->CreditNoteLine = $this->lineBuilder->getCreditNoteLines();
            } else {
                $this->p_invoice->InvoiceTypeCode = 380;
                $this->p_invoice->InvoiceLine = $this->lineBuilder->getInvoiceLines();
            }

            $this->p_invoice->AccountingSupplierParty = $this->partyBuilder->getAccountingSupplierParty();
            $this->p_invoice->AccountingCustomerParty = $this->partyBuilder->getAccountingCustomerParty();
            $this->p_invoice->AllowanceCharge = $this->getAllowanceCharges();
            $this->p_invoice->LegalMonetaryTotal = $this->getLegalMonetaryTotal();
            $this->p_invoice->Delivery = $this->partyBuilder->getDelivery();

            $this->setOrderReference()

                 ->setTaxBreakdown()
                 ->setPaymentTerms()
                 ->addAttachments()
                 ->addThirdPartyAttachments()
                 ->standardPeppolRules()
                 ->setDocumentReference();


            //isolate this class to only peppol changes
            if (strlen($this->override_vat_number) > 1) {
                $this->gateway->mutator->setOverrideVatNumber($this->override_vat_number);
            }

            $this->p_invoice = $this->gateway
                                    ->mutator
                                    ->senderSpecificLevelMutators()
                                    ->receiverSpecificLevelMutators()
                                    ->getPeppol();

        } catch (\Throwable $th) {
            nlog("Unable to create Peppol Invoice - " . $th->getMessage());
            $this->errors[] = $th->getMessage();
        }

        return $this;

    }

    /**
     * Transforms a stdClass document to Peppol\Invoice or Peppol\CreditNote
     *
     * @param  mixed $document
     * @param  string $type 'Invoice' or 'CreditNote'
     * @return self
     */
    public function decode(mixed $document, string $type = 'Invoice'): self
    {
        $peppolType = $type === 'CreditNote' ? 'Peppol_CreditNote' : 'Peppol';
        $this->p_invoice = $this->e->decode($peppolType, json_encode($document), 'json');

        return $this;
    }

    /**
     * Rehydrates an existing e invoice - or - scaffolds a new one
     *
     * @return self
     */
    private function setInvoice(): self
    {
        /** Handle Existing CreditNote Document */
        if ($this->isCreditNote && $this->invoice->e_invoice && isset($this->invoice->e_invoice->CreditNote) && isset($this->invoice->e_invoice->CreditNote->ID)) {

            $this->decode($this->invoice->e_invoice->CreditNote, 'CreditNote');

            $this->gateway
                ->mutator
                ->setInvoice($this->invoice)
                ->setPeppol($this->p_invoice)
                ->setClientSettings($this->_client_settings)
                ->setCompanySettings($this->_company_settings);

            return $this;
        }

        /** Handle Existing Invoice Document */
        if (!$this->isCreditNote && $this->invoice->e_invoice && isset($this->invoice->e_invoice->Invoice) && isset($this->invoice->e_invoice->Invoice->ID)) {

            $this->decode($this->invoice->e_invoice->Invoice, 'Invoice');

            $this->gateway
                ->mutator
                ->setInvoice($this->invoice)
                ->setPeppol($this->p_invoice)
                ->setClientSettings($this->_client_settings)
                ->setCompanySettings($this->_company_settings);

            return $this;
        }

        /** Scaffold new document based on type */
        if ($this->isCreditNote) {
            $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\CreditNote();
        } else {
            $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        }

        /** Set Props */
        $this->gateway
            ->mutator
            ->setInvoice($this->invoice)
            ->setPeppol($this->p_invoice)
            ->setClientSettings($this->_client_settings)
            ->setCompanySettings($this->_company_settings);

        $this->setInvoiceDefaults();

        return $this;
    }

    /**
     * Transforms the settings props into usable models we can merge.
     *
     * @return self
     */
    private function setSettings(): self
    {
        $this->_client_settings = isset($this->invoice->client->e_invoice->Invoice) ? $this->e->decode('Peppol', json_encode($this->invoice->client->e_invoice->Invoice), 'json') : null;

        $this->_company_settings = isset($this->invoice->company->e_invoice->Invoice) ? $this->e->decode('Peppol', json_encode($this->invoice->company->e_invoice->Invoice), 'json') : null;

        return $this;
    }

    /**
     * getDocument
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getDocument(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
    {
        return $this->p_invoice;
    }

    /**
     * getInvoice
     *
     * @deprecated Use getDocument() instead
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
    {
        return $this->p_invoice;
    }

    /**
     * Check if the document is a Credit Note
     *
     * @return bool
     */
    public function isCreditNote(): bool
    {
        return $this->isCreditNote;
    }

    /**
     * toXml
     *
     * Builds a full Peppol XML document including tags
     *
     * @return string
     */
    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->p_invoice, 'xml');

        if ($this->isCreditNote) {
            $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<CreditNote xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2">';
            $suffix = '</CreditNote>';
        } else {
            $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';
            $suffix = '</Invoice>';
        }

        $xml = str_ireplace(['\n','<?xml version="1.0"?>'], ['', $prefix], $xml);
        $xml .= $suffix;

        return $xml;
    }

    /**
     * toJson
     *
     * Returns the peppol invoice in json format
     *
     * @return string
     */
    public function toJson(): string
    {
        $e = new EInvoice();
        $json =  $e->encode($this->p_invoice, 'json');

        return $json;

    }

    /**
     * toObject
     *
     * returns the Peppol document in object format.
     *
     * @return mixed
     */
    public function toObject(): mixed
    {
        $document = new \stdClass();

        if ($this->isCreditNote) {
            $document->CreditNote = json_decode($this->toJson());
        } else {
            $document->Invoice = json_decode($this->toJson());
        }

        return $document;
    }

    /**
     * toArray
     *
     * Returns the peppol document in Array format
     *
     * @return array
     */
    public function toArray(): array
    {
        $key = $this->isCreditNote ? 'CreditNote' : 'Invoice';
        return [$key => json_decode($this->toJson(), true)];
    }

    /**
     * Set the reference for this document,
     * ie: for a credit note, this reference would be the invoice it is referencing. Will always be stored on the e_invoice object.
     *
     * @return self
     */
    private function setDocumentReference(): self
    {
        // InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType

        if ($this->isCreditNote() && isset($this->invoice->e_invoice->CreditNote->BillingReference) && isset($this->invoice->e_invoice->CreditNote->BillingReference[0]->InvoiceDocumentReference)) {

            $document_reference = new \InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\InvoiceDocumentReference();

            $_idr = reset($this->invoice->e_invoice->CreditNote->BillingReference);

            $d_id = new ID();
            $d_id->value = $_idr->InvoiceDocumentReference->ID;

            $document_reference->ID = $d_id;

            if (isset($_idr->InvoiceDocumentReference->IssueDate)) {
                $issue_date = new \DateTime($_idr->InvoiceDocumentReference->IssueDate);
                $document_reference->IssueDate = $issue_date;
            }

            $billing_reference = new BillingReference();
            $billing_reference->InvoiceDocumentReference = $document_reference;

            $this->p_invoice->BillingReference = [$billing_reference];

            return $this;
        }


        // We should only need to pull this in from the already stored object.
        return $this;
    }

    /**
     * setOrderReference
     *
     * Sets the order reference - if it exists - on the document.
     * @return self
     */
    private function setOrderReference(): self
    {

        $this->p_invoice->BuyerReference = $this->invoice->po_number ?? '';

        $order_reference = new OrderReference();
        $id = new ID();
        $id->value = strlen($this->invoice->po_number ?? '') > 1 ? $this->invoice->po_number : $this->invoice->number;

        $order_reference->ID = $id;
        $this->p_invoice->OrderReference = $order_reference;

        return $this;

    }

    private function addThirdPartyAttachments(): self
    {
        $this->attachmentBuilder->addThirdPartyAttachments();

        return $this;
    }

    private function addAttachments(): self
    {
        $this->attachmentBuilder->addPrimaryAttachment();

        return $this;
    }

    /**
     * getAllowanceCharges
     *
     * Allowance charges are discounts / fees that are
     * applied to line or invoice level items
     *
     * ChargeIndicator flags whether the item is a discount 'false'
     * this prop is ONLY set for discounts. Fees are inferred.
     *
     * @return array
     */
    private function getAllowanceCharges(): array
    {
        $allowances = [];

        //Invoice Level discount
        if ($this->invoice->discount > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->ChargeIndicator = 'false'; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = number_format($this->normalizeAmount($this->calc->getTotalDiscount()), 2, '.', '');

            // Add percentage if available
            if ($this->invoice->discount > 0 && !$this->invoice->is_amount_discount) {

                $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
                $allowanceCharge->BaseAmount->amount = number_format($this->normalizeAmount($this->calc->getSubtotalWithSurcharges()), 2, '.', '');

                $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                $mfn->value = number_format(round(($this->invoice->discount), 2), 2, '.', '');  // Format to always show 2 decimals
                $allowanceCharge->MultiplierFactorNumeric = $mfn; // Convert percentage to decimal
            }

            $tc = clone $this->globalTaxCategories[0];
            // $tc->Percent = '0';
            unset($tc->TaxExemptionReasonCode);
            unset($tc->TaxExemptionReason);

            $allowanceCharge->TaxCategory[] = $tc;
            $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');
            $allowances[] = $allowanceCharge;
        }

        //Invoice level surcharges
        foreach (['custom_surcharge1', 'custom_surcharge2', 'custom_surcharge3', 'custom_surcharge4'] as $surcharge) {

            $surchargeAmount = $this->invoice->{$surcharge};

            if ($surchargeAmount > 0) {
                $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
                $allowanceCharge->ChargeIndicator = 'true';
                $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
                $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
                $allowanceCharge->Amount->amount = number_format($surchargeAmount, 2, '.', '');

                $this->taxCalculator->calculateTaxMap($surchargeAmount);

                $allowanceCharge->TaxCategory = $this->globalTaxCategories;
                $allowanceCharge->AllowanceChargeReason = ctrans('texts.surcharge');
                $allowances[] = $allowanceCharge;
            }

        }

        return $allowances;

    }

    /**
     * getLegalMonetaryTotal
     *
     * @return LegalMonetaryTotal
     */
    private function getLegalMonetaryTotal(): LegalMonetaryTotal
    {
        $taxable = $this->taxCalculator->getTaxable();

        // Normalize amounts for credit notes (ensure positive values)
        $amount = $this->normalizeAmount($this->invoice->amount);
        $totalTaxes = $this->normalizeAmount($this->invoice->total_taxes);
        $subtotal = $this->normalizeAmount($this->calc->getSubtotal());

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->currencyID = $this->invoice->client->currency()->code;
        $lea->amount = $this->invoice->uses_inclusive_taxes ? (string) round($amount - $totalTaxes, 2) : (string) $subtotal;
        $lmt->LineExtensionAmount = $lea;

        $tea = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount();
        $tea->currencyID = $this->invoice->client->currency()->code;

        /**
         * 2026-03-03 - The tax exclusive amount is the amount of the invoice before taxes.
         * Very important to understand the logic here and not change this without undertsanding
         * the implications.
         */
        $totalDiscount = $this->normalizeAmount($this->calc->getTotalDiscount());
        $totalSurcharges = $this->normalizeAmount($this->calc->getTotalSurcharges());
        $tea->amount = $this->invoice->uses_inclusive_taxes
            ? (string) round($amount - $totalTaxes, 2)
            : (string) round($subtotal - $totalDiscount + $totalSurcharges, 2);
        $lmt->TaxExclusiveAmount = $tea;

        $tia = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount();
        $tia->currencyID = $this->invoice->client->currency()->code;
        $tia->amount = $amount;
        $lmt->TaxInclusiveAmount = $tia;

        $pa = new PayableAmount();
        $pa->currencyID = $this->invoice->client->currency()->code;
        $pa->amount = $amount;
        $lmt->PayableAmount = $pa;

        $am = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\AllowanceTotalAmount();
        $am->currencyID = $this->invoice->client->currency()->code;
        $am->amount = number_format($this->normalizeAmount($this->calc->getTotalDiscount()), 2, '.', '');
        $lmt->AllowanceTotalAmount = $am;

        $cta = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\ChargeTotalAmount();
        $cta->currencyID = $this->invoice->client->currency()->code;
        $cta->amount = number_format($this->normalizeAmount($this->calc->getTotalSurcharges()), 2, '.', '');
        $lmt->ChargeTotalAmount = $cta;

        return $lmt;
    }

    /////////////////  Helper Methods /////////////////////////

    /**
     * Merges properties from a settings source onto the Peppol document,
     * skipping properties that belong to the wrong document type.
     */
    private function mergeSettingsInto(?object $settings): void
    {
        if (!$settings) {
            return;
        }

        $skipProps = $this->isCreditNote
            ? ['InvoiceTypeCode', 'InvoiceLine', 'InvoicePeriod']
            : ['CreditNoteTypeCode', 'CreditNoteLine'];

        foreach (get_object_vars($settings) as $prop => $value) {
            if (!in_array($prop, $skipProps)) {
                $this->p_invoice->{$prop} = $value;
            }
        }
    }

    /**
     * setInvoiceDefaults
     *
     * Stubs a default einvoice
     * @return self
     */
    public function setInvoiceDefaults(): self
    {
        // Merge company settings, then client settings (client wins), then existing e_invoice data
        $this->mergeSettingsInto($this->_company_settings);
        $this->mergeSettingsInto($this->_client_settings);

        $existingData = $this->isCreditNote
            ? ($this->invoice->e_invoice->CreditNote ?? null)
            : ($this->invoice->e_invoice->Invoice ?? null);

        if ($existingData) {
            foreach (get_object_vars($existingData) as $prop => $value) {
                $this->p_invoice->{$prop} = $value;
            }
        }

        // Plucks special overriding properties scanning the correct settings level
        $settings = [
            'AccountingCostCode' => 7,
            'AccountingCost' => 7,
            'BuyerReference' => 6,
            'AccountingSupplierParty' => 1,
            'AccountingCustomerParty' => 2,
            'PayeeParty' => 1,
            'BuyerCustomerParty' => 2,
            'SellerSupplierParty' => 1,
            'TaxRepresentativeParty' => 1,
            'Delivery' => 1,
            'DeliveryTerms' => 7,
            'PaymentMeans' => 7,
            'PaymentTerms' => 7,
        ];

        //only scans for top level props
        foreach ($settings as $prop => $visibility) {

            if ($prop_value = $this->gateway->mutator->getSetting($prop)) {
                $this->p_invoice->{$prop} = $prop_value;
            }

        }

        if (isset($this->invoice->e_invoice->Invoice->InvoicePeriod[0])
       && isset($this->invoice->e_invoice->Invoice->InvoicePeriod[0]->StartDate)
       && isset($this->invoice->e_invoice->Invoice->InvoicePeriod[0]->EndDate)) {

            $start_date = $this->invoice->e_invoice->Invoice->InvoicePeriod[0]->StartDate->date ?? $this->invoice->e_invoice->Invoice->InvoicePeriod[0]->StartDate;
            $end_date = $this->invoice->e_invoice->Invoice->InvoicePeriod[0]->EndDate->date ?? $this->invoice->e_invoice->Invoice->InvoicePeriod[0]->EndDate;

            $ip = new \InvoiceNinja\EInvoice\Models\Peppol\PeriodType\InvoicePeriod();
            $ip->StartDate = new \DateTime($start_date);
            $ip->EndDate = new \DateTime($end_date);
            $this->p_invoice->InvoicePeriod = [$ip];
        }

        return $this;
    }

    /**
     * standardPeppolRules
     *
     * Transform UBL => Peppol rules
     *
     * 1. FinancialInstitutionBranch - remove
     * 2. FinancialInstituion - remove
     *
     * @return self
     */
    private function standardPeppolRules(): self
    {
        foreach ($this->p_invoice->PaymentMeans as &$pm) {
            unset($pm->PayeeFinancialAccount->FinancialInstitutionBranch);
        }
        unset($pm);

        return $this;
    }

    /**
     * setPaymentTerms
     *
     * If payment terms are defined, we should include these
     * on the invoice.
     *
     * @return self
     */
    private function setPaymentTerms(): self
    {
        $terms_string = $this->invoice->client->getSetting('payment_terms');

        if (strlen($terms_string) > 1) {
            $terms = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentTermsType\PaymentTerms();
            $terms->Note = trans('texts.count_days', ['count' => $terms_string]);

            $this->p_invoice->PaymentTerms[] = $terms;

        }

        return $this;
    }

    public function setTaxBreakdown(): self
    {
        $this->taxCalculator->setTaxBreakdown();

        return $this;
    }

    public function getJurisdiction()
    {
        return $this->taxCalculator->getJurisdiction();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

}
