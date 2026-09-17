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
use App\Helpers\Invoice\Taxer;
use App\Utils\Traits\MakesHash;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Services\EDocument\UblDocumentKind;
use App\Services\EDocument\UblXmlEncoder;
use App\Services\EDocument\Standards\Peppol\PeppolLineBuilder;
use App\Services\EDocument\Standards\Peppol\PeppolTaxCalculator;
use App\Services\EDocument\Standards\Peppol\PeppolPartyBuilder;
use App\Services\EDocument\Standards\Peppol\PeppolAttachmentBuilder;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\MutatorInterface;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Standards\Peppol\CountryFactory;
use App\Services\EDocument\Standards\Settings\PropertyResolver;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\BillingReferenceType\BillingReference;
use App\Services\EDocument\Standards\Peppol\CountryHandler;

class Peppol extends AbstractService implements MutatorInterface
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

    /**
     *
     * If the company has a VAT number in the client's country, this will be used instead of the company's VAT number.
     * @var string $override_vat_number
     *
     **/
    private string $override_vat_number = '';

    private string $override_country_code = '';

    /** @var Company $company */
    private Company $company;

    /** @var InvoiceSum|InvoiceSumInclusive $calc */
    private InvoiceSum|InvoiceSumInclusive $calc;

    /** @var \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote */
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice;

    /** @var ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings */
    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    /** @var ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings */
    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    /** @var EInvoice $e */
    private EInvoice $e;

    /**
     * PEPPOL UBL document kind (380 invoice / 381 credit note).
     *
     * @var UblDocumentKind
     */
    private UblDocumentKind $documentKind;

    /**
     *
     * Router for resolving ISO 6523 schemes and routing rules.
     *
     * @var StorecoveRouter $router
     **/
    public StorecoveRouter $router;

    /**
     *
     * @var string $customizationID
     *
     **/
    private string $customizationID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    /** 
     * 
     * @var string $profileID
     * 
     **/
    private string $profileID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** 
     * 
     * @var float $allowance_total
     * 
     **/
    private float $allowance_total = 0;

    /** 
     * 
     * @var array $globalTaxCategories
     * 
     **/
    private $globalTaxCategories;

    /** 
     * 
     * @var string $tax_category_id
     * 
     **/
    private string $tax_category_id = 'S';

    /** 
     * 
     * @var bool $has_category_O
     * 
     **/
    private bool $has_category_O = false;

    /** 
     * 
     * @var array $errors
     * 
     **/
    private array $errors = [];

    /** 
     * 
     * @var PeppolTaxCalculator
     * 
     **/
    private PeppolTaxCalculator $taxCalculator;

    /** 
     * 
     * @var PeppolLineBuilder
     * 
     **/
    private PeppolLineBuilder $lineBuilder;

    /** 
     * 
     * @var PeppolPartyBuilder
     * 
     **/
    private PeppolPartyBuilder $partyBuilder;

    /** 
     * 
     * @var PeppolAttachmentBuilder
     * 
     **/
    private PeppolAttachmentBuilder $attachmentBuilder;

    private CountryHandler $countryHandler;
    /** 
     * 
     * @param Invoice|Credit $invoice
     * 
     **/
    public function __construct(public Invoice|Credit $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->e = new EInvoice();
        $this->router = new StorecoveRouter();
        $this->documentKind = UblDocumentKind::fromEntity($this->invoice);

        $this->taxCalculator = new PeppolTaxCalculator($this);
        $this->lineBuilder = new PeppolLineBuilder($this);
        $this->partyBuilder = new PeppolPartyBuilder($this);
        $this->attachmentBuilder = new PeppolAttachmentBuilder($this);

        $this->setSettings()->initDocument();

        $this->countryHandler = CountryFactory::make($this->company->country()->iso_3166_2);

    }

    /**
     * Document-level sign for credit-note monetary fields.
     *
     * Money only — never quantity. One multiplier from the document total,
     * never abs(). Per-field abs() collapses an internal offset line into
     * another credit and breaks BR-CO-10 / BR-CO-13 / PEPPOL-EN16931-R120.
     *
     * @param float|int|string $amount
     * @return float
     */
    public function normalizeAmount(float|int|string $amount): float
    {
        $value = (float) $amount;

        if (!$this->documentKind->isCreditNote()) {
            return $value;
        }

        $sign = ((float) $this->invoice->amount) < 0 ? -1 : 1;

        return $value * $sign;
    }

    /**
     * Credit-note UBL line signs.
     *
     * Price is always >= 0 (BR-27). Quantity carries sign so price × qty
     * (+/- line allowances) reconciles with LineExtensionAmount
     * (PEPPOL-EN16931-R120). Discounted lines project commercial sign from
     * cost × quantity in the document frame; allowance vs charge is chosen in
     * PeppolLineBuilder from LEA − (qty × price).
     *
     * @return array{quantity: float, price: float, line_total: float}
     */
    public function normalizeCreditNoteLine(
        float $quantity,
        float $cost,
        float $lineTotal,
        bool $hasLineAllowance = false,
    ): array {
        $sign = ((float) $this->invoice->amount) < 0 ? -1.0 : 1.0;
        $price = abs($cost);
        $normalizedLine = $this->normalizedCreditLineExtension($lineTotal, $sign, $quantity, $cost);

        if ($sign < 0) {
            return $this->normalizeNegativeTotalCreditLineQuantity(
                $quantity,
                $cost,
                $price,
                $normalizedLine,
            );
        }

        if ($hasLineAllowance) {
            $economicSign = ((float) $cost * (float) $quantity) < 0 ? -1.0 : 1.0;

            return [
                'quantity' => $economicSign * abs($quantity),
                'price' => $price,
                'line_total' => $normalizedLine,
            ];
        }

        return [
            'quantity' => $price > 0.0 ? $normalizedLine / $price : $quantity * $sign,
            'price' => $price,
            'line_total' => $normalizedLine,
        ];
    }

    /**
     * LineExtensionAmount in the credit-note frame before quantity projection.
     */
    private function normalizedCreditLineExtension(
        float $lineTotal,
        float $documentSign,
        float $quantity,
        float $cost,
    ): float {
        if ($documentSign > 0) {
            return $lineTotal;
        }

        if ((float) $cost * (float) $quantity > 0) {
            return $lineTotal * $documentSign;
        }

        return abs($lineTotal);
    }

    /**
     * CreditedQuantity on negative-total credit notes (incl. negative invoices as 381).
     *
     * @return array{quantity: float, price: float, line_total: float}
     */
    private function normalizeNegativeTotalCreditLineQuantity(
        float $quantity,
        float $cost,
        float $price,
        float $normalizedLine,
    ): array {
        if ((float) $cost * (float) $quantity > 0) {
            return [
                'quantity' => -abs($quantity),
                'price' => $price,
                'line_total' => $normalizedLine,
            ];
        }

        return [
            'quantity' => abs($quantity),
            'price' => $price,
            'line_total' => $normalizedLine,
        ];
    }

    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {
        try {
            $this->taxCalculator->setJurisdiction(); //Sets the nexus object into the Peppol document.
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

            if ($this->invoice->due_date && !$this->documentKind->isCreditNote()) {
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

            /** Set type code and line items based on document kind */
            if ($this->documentKind->isCreditNote()) {
                $this->p_invoice->CreditNoteTypeCode = $this->documentKind->typeCode();
                $this->p_invoice->CreditNoteLine = $this->lineBuilder->getCreditNoteLines();
            } else {
                $this->p_invoice->InvoiceTypeCode = $this->documentKind->typeCode();
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


            // Apply country-specific UBL mutations directly via CountryFactory
            $mutatorUtil = new MutatorUtil($this);

            $senderHandler = CountryFactory::make($this->invoice->company->country()->iso_3166_2);
            $this->p_invoice = $senderHandler->senderMutations($this->p_invoice, $this->invoice, $mutatorUtil);

            $receiverHandler = CountryFactory::make($this->invoice->client->country->iso_3166_2);
            $this->p_invoice = $receiverHandler->receiverMutations($this->p_invoice, $this->invoice, $mutatorUtil);

        } catch (\Throwable $th) {
            nlog("Unable to create Peppol Invoice - " . $th->getMessage() . ' at ' . $th->getFile() . ':' . $th->getLine());
            nlog($th->getTraceAsString());
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
    private function initDocument(): self
    {
        /** Handle Existing CreditNote Document */
        if ($this->documentKind->isCreditNote() && $this->invoice->e_invoice && isset($this->invoice->e_invoice->CreditNote) && isset($this->invoice->e_invoice->CreditNote->ID)) {
            $this->decode($this->invoice->e_invoice->CreditNote, 'CreditNote');
            return $this;
        }

        /** Handle Existing Invoice Document */
        if (!$this->documentKind->isCreditNote() && $this->invoice->e_invoice && isset($this->invoice->e_invoice->Invoice) && isset($this->invoice->e_invoice->Invoice->ID)) {
            $this->decode($this->invoice->e_invoice->Invoice, 'Invoice');
            return $this;
        }

        /** Scaffold new document based on type */
        if ($this->documentKind->isCreditNote()) {
            $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\CreditNote();
        } else {
            $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        }

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
    /**
     * @deprecated Use getDocument() instead.
     */
    public function getPeppolInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
    {
        return $this->p_invoice;
    }

    public function getDocumentKind(): UblDocumentKind
    {
        return $this->documentKind;
    }

    /**
     * Whether this document is emitted as PEPPOL UBL 381 (CreditNote).
     */
    public function isCreditNote(): bool
    {
        return $this->documentKind->isCreditNote();
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

        return UblXmlEncoder::wrap($e->encode($this->p_invoice, 'xml'), $this->documentKind);
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

        if ($this->documentKind->isCreditNote()) {
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
        $key = $this->documentKind->isCreditNote() ? 'CreditNote' : 'Invoice';
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

        // Invoice-level discount — split by line VAT rate for BR-S-08 / R041.
        if ($this->invoice->discount > 0) {
            if (!$this->invoice->is_amount_discount) {
                $totalBase = abs($this->normalizeAmount($this->calc->getSubtotal()));
                $totalDiscount = round($totalBase * ($this->invoice->discount / 100), 2);
                $splits = $this->allocateDocumentAmountByTaxRate($totalDiscount);
                $bucketNets = $this->lineNetByTaxRate();

                foreach ($splits as $rate => $splitAmount) {
                    $bucketNet = abs((float) ($bucketNets[$rate] ?? 0));
                    $allowanceCharge = $this->makeDocumentAllowanceCharge(false);
                    $allowanceCharge->Amount->amount = number_format($splitAmount, 2, '.', '');
                    $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                    $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
                    $allowanceCharge->BaseAmount->amount = number_format($bucketNet, 2, '.', '');
                    $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                    $mfn->value = number_format(round((float) $this->invoice->discount, 2), 2, '.', '');
                    $allowanceCharge->MultiplierFactorNumeric = $mfn;
                    $allowanceCharge->TaxCategory[] = $this->taxCategoryForRate((string) $rate);
                    $allowances[] = $allowanceCharge;
                }
            } else {
                $totalDiscount = abs($this->normalizeAmount($this->calc->getTotalDiscount()));
                foreach ($this->allocateDocumentAmountByTaxRate($totalDiscount) as $rate => $splitAmount) {
                    $allowanceCharge = $this->makeDocumentAllowanceCharge(false);
                    $allowanceCharge->Amount->amount = number_format($splitAmount, 2, '.', '');
                    $allowanceCharge->TaxCategory[] = $this->taxCategoryForRate((string) $rate);
                    $allowances[] = $allowanceCharge;
                }
            }
        }

        $totalSurcharges = (float) $this->calc->getTotalSurcharges();
        if ($totalSurcharges > 0) {
            foreach ($this->allocateDocumentAmountByTaxRate($totalSurcharges) as $rate => $splitAmount) {
                $allowanceCharge = $this->makeDocumentAllowanceCharge(true);
                $allowanceCharge->Amount->amount = number_format($splitAmount, 2, '.', '');
                $allowanceCharge->TaxCategory[] = $this->taxCategoryForRate((string) $rate);
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
        $totalDiscount = abs($this->normalizeAmount($this->calc->getTotalDiscount()));
        $totalSurcharges = abs((float) $this->calc->getTotalSurcharges());
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
        // BT-107: document-level allowance totals are unsigned (BR-CO-11).
        $am->amount = number_format(abs($this->normalizeAmount($this->calc->getTotalDiscount())), 2, '.', '');
        $lmt->AllowanceTotalAmount = $am;

        $cta = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\ChargeTotalAmount();
        $cta->currencyID = $this->invoice->client->currency()->code;
        // BT-108: document-level charge totals are unsigned (BR-CO-12), including credit notes.
        $cta->amount = number_format(abs($this->calc->getTotalSurcharges()), 2, '.', '');
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

        $skipProps = $this->documentKind->isCreditNote()
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

        $existingData = $this->documentKind->isCreditNote()
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

            if ($prop_value = $this->getSetting($prop)) {
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
        if (!isset($this->p_invoice->PaymentMeans)) {
            return $this;
        }

        foreach ($this->p_invoice->PaymentMeans as &$pm) {
            unset($pm->PayeeFinancialAccount->FinancialInstitutionBranch);
        }
        unset($pm);

        return $this;
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

    public function getRouter(): StorecoveRouter
    {
        return $this->router;
    }

    // ── MutatorInterface implementation ──

    public function receiverSpecificLevelMutators(): self
    {
        return $this;
    }

    public function senderSpecificLevelMutators(): self
    {
        return $this;
    }

    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function setPeppol($p_invoice): self
    {
        $this->p_invoice = $p_invoice;
        return $this;
    }

    public function getPeppol(): mixed
    {
        return $this->p_invoice;
    }

    public function setClientSettings($client_settings): self
    {
        $this->_client_settings = $client_settings;
        return $this;
    }

    public function setCompanySettings($company_settings): self
    {
        $this->_company_settings = $company_settings;
        return $this;
    }

    public function getClientSettings(): mixed
    {
        return $this->_client_settings;
    }

    public function getCompanySettings(): mixed
    {
        return $this->_company_settings;
    }

    public function getInvoice(): mixed
    {
        return $this->invoice;
    }

    public function getSetting(string $property_path): mixed
    {
        if ($prop_value = PropertyResolver::resolve($this->p_invoice, $property_path)) {
            return $prop_value;
        } elseif ($prop_value = PropertyResolver::resolve($this->_client_settings, $property_path)) {
            return $prop_value;
        } elseif ($prop_value = PropertyResolver::resolve($this->_company_settings, $property_path)) {
            return $prop_value;
        }
        return null;
    }

    public function setOverrideVatNumber(string $vat_number, string $country_code): self
    {
        $this->override_vat_number = preg_replace("/[^a-zA-Z0-9]/", "", $vat_number);
        $this->override_country_code = $country_code;
        return $this;
    }

    public function getOverrideCountryCode(): string
    {
        return $this->override_country_code;
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

    public function addToAllowanceTotal(float $amount): void
    {
        $this->allowance_total += $amount;
    }

    public function getTaxCalculator(): PeppolTaxCalculator
    {
        return $this->taxCalculator;
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

    /**
     * PEPPOL-EN16931-R041 / BR-S-08: taxable base per VAT rate from emitted lines
     * plus document-level charges minus document-level allowances for that rate.
     *
     * @return array<string, float> keyed by Percent string
     */
    public function brs08TaxableAmountsByRate(): array
    {
        $lineSum = [];
        $lines = $this->documentKind->isCreditNote()
            ? ($this->p_invoice->CreditNoteLine ?? [])
            : ($this->p_invoice->InvoiceLine ?? []);

        foreach ($lines as $line) {
            $categories = $line->Item->ClassifiedTaxCategory ?? [];
            $category = is_array($categories) ? ($categories[0] ?? null) : $categories;
            $rate = (string) ($category->Percent ?? '0');
            $lea = (float) ($line->LineExtensionAmount->amount ?? 0);
            $lineSum[$rate] = ($lineSum[$rate] ?? 0) + $lea;
        }

        $allowances = [];
        $charges = [];
        foreach ($this->p_invoice->AllowanceCharge ?? [] as $ac) {
            $categories = $ac->TaxCategory ?? [];
            if (! is_array($categories)) { //@phpstan-ignore-line
                $categories = [$categories];
            }
            $category = $categories[0] ?? null;
            $rate = (string) ($category->Percent ?? '0');
            $amount = (float) ($ac->Amount->amount ?? 0);
            if (($ac->ChargeIndicator ?? 'false') === 'true') {
                $charges[$rate] = ($charges[$rate] ?? 0) + $amount;
            } else {
                $allowances[$rate] = ($allowances[$rate] ?? 0) + $amount;
            }
        }

        $rates = array_unique(array_merge(array_keys($lineSum), array_keys($allowances), array_keys($charges)));
        $result = [];
        foreach ($rates as $rate) {
            $result[$rate] = round(
                ($lineSum[$rate] ?? 0) + ($charges[$rate] ?? 0) - ($allowances[$rate] ?? 0),
                2
            );
        }

        return $result;
    }

    /**
     * @return array<string, float>
     */
    private function lineNetByTaxRate(): array
    {
        $buckets = [];
        foreach ($this->invoice->line_items as $item) {
            if (strlen($item->tax_name1 ?? '') <= 1) {
                continue;
            }
            $rate = (string) round((float) $item->tax_rate1, 2);
            $buckets[$rate] = ($buckets[$rate] ?? 0) + (float) $item->line_total;
        }

        return $buckets;
    }

    /**
     * @return array<string, float>
     */
    private function allocateDocumentAmountByTaxRate(float $amount): array
    {
        $buckets = $this->lineNetByTaxRate();
        $weights = [];
        foreach ($buckets as $rate => $net) {
            $weights[$rate] = abs((float) $net);
        }
        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0 || $amount <= 0) {
            return [];
        }

        $rates = array_keys($weights);
        $allocated = [];
        $remaining = round($amount, 2);

        foreach ($rates as $index => $rate) {
            if ($index === count($rates) - 1) {
                $allocated[$rate] = max(0.0, round($remaining, 2));
                break;
            }

            $share = round($amount * ($weights[$rate] / $weightTotal), 2);
            $allocated[$rate] = $share;
            $remaining -= $share;
        }

        return array_filter($allocated, fn ($value) => $value > 0);
    }

    private function taxCategoryForRate(string $rate): \InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory
    {
        foreach ($this->globalTaxCategories as $category) {
            if ((string) ($category->Percent ?? '') === $rate) {
                $tc = clone $category;
                unset($tc->TaxExemptionReasonCode);
                unset($tc->TaxExemptionReason);

                return $tc;
            }
        }

        $tc = clone $this->globalTaxCategories[0];
        unset($tc->TaxExemptionReasonCode);
        unset($tc->TaxExemptionReason);

        return $tc;
    }

    private function makeDocumentAllowanceCharge(bool $isCharge): \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge
    {
        $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
        $allowanceCharge->ChargeIndicator = $isCharge ? 'true' : 'false';
        $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
        $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
        $allowanceCharge->AllowanceChargeReason = ctrans($isCharge ? 'texts.surcharge' : 'texts.discount');

        return $allowanceCharge;
    }

    public function getJurisdiction()
    {
        return $this->taxCalculator->getJurisdiction();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getCountryHandler(): CountryHandler
    {
        return $this->countryHandler;
    }

    /////////////////  End Accessor Methods /////////////////////////

}
