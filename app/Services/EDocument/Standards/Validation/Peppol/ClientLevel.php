<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Validation\Peppol;

use DateTime;
use InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge;
use InvoiceNinja\EInvoice\Models\Peppol\BillingReferenceType\BillingReference;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\AccountingCostCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\DocumentCurrencyCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\InvoiceTypeCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentAlternativeCurrencyCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentCurrencyCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\PricingCurrencyCode;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\TaxCurrencyCode;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\BuyerCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\DeliveryTermsType\DeliveryTerms;
use InvoiceNinja\EInvoice\Models\Peppol\DeliveryType\Delivery;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\AdditionalDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\ContractDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\DespatchDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\OriginatorDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\ReceiptDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\DocumentReferenceType\StatementDocumentReference;
use InvoiceNinja\EInvoice\Models\Peppol\ExchangeRateType\PaymentAlternativeExchangeRate;
use InvoiceNinja\EInvoice\Models\Peppol\ExchangeRateType\PaymentExchangeRate;
use InvoiceNinja\EInvoice\Models\Peppol\ExchangeRateType\PricingExchangeRate;
use InvoiceNinja\EInvoice\Models\Peppol\ExchangeRateType\TaxExchangeRate;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomizationID;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ProfileExecutionID;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ProfileID;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\UBLVersionID;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\UUID;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\NumericType\LineCountNumeric;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\PayeeParty;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\TaxRepresentativeParty;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeansType\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentTermsType\PaymentTerms;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentType\PrepaidPayment;
use InvoiceNinja\EInvoice\Models\Peppol\PeriodType\InvoicePeriod;
use InvoiceNinja\EInvoice\Models\Peppol\ProjectReferenceType\ProjectReference;
use InvoiceNinja\EInvoice\Models\Peppol\SignatureType\Signature;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\SellerSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\WithholdingTaxTotal;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Valid;

class ClientLevel
{
    // /** @var UBLVersionID */
    // #[SerializedName('cbc:UBLVersionID')]
    // public $UBLVersionID;

    // /** @var CustomizationID */
    // #[SerializedName('cbc:CustomizationID')]
    // public $CustomizationID;

    // /** @var ProfileID */
    // #[SerializedName('cbc:ProfileID')]
    // public $ProfileID;

    // /** @var ProfileExecutionID */
    // #[SerializedName('cbc:ProfileExecutionID')]
    // public $ProfileExecutionID;

    // /** @var ID */
    // #[NotNull]
    // #[NotBlank]
    // #[Valid]
    // #[SerializedName('cbc:ID')]
    // public $ID;

    /** @var bool */
    #[SerializedName('cbc:CopyIndicator')]
    public bool $CopyIndicator;

    /** @var UUID */
    #[SerializedName('cbc:UUID')]
    public $UUID;

    // /** @var ?\DateTime */
    // #[NotNull]
    // #[NotBlank]
    // #[Valid]
    // #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    // #[SerializedName('cbc:IssueDate')]
    // public ?DateTime $IssueDate;

    // /** @var ?\DateTime */
    // #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s.uP'])]
    // #[SerializedName('cbc:IssueTime')]
    // public ?DateTime $IssueTime;

    // /** @var ?\DateTime */
    // #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    // #[SerializedName('cbc:DueDate')]
    // public ?DateTime $DueDate;

    /** @var InvoiceTypeCode */
    #[SerializedName('cbc:InvoiceTypeCode')]
    public $InvoiceTypeCode;

    /** @var string */
    #[SerializedName('cbc:Note')]
    public string $Note;

    /** @var ?\DateTime */
    #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[SerializedName('cbc:TaxPointDate')]
    public ?DateTime $TaxPointDate;

    /** @var DocumentCurrencyCode */
    #[SerializedName('cbc:DocumentCurrencyCode')]
    public $DocumentCurrencyCode;

    /** @var TaxCurrencyCode */
    #[SerializedName('cbc:TaxCurrencyCode')]
    public $TaxCurrencyCode;

    /** @var PricingCurrencyCode */
    #[SerializedName('cbc:PricingCurrencyCode')]
    public $PricingCurrencyCode;

    /** @var PaymentCurrencyCode */
    #[SerializedName('cbc:PaymentCurrencyCode')]
    public $PaymentCurrencyCode;

    /** @var PaymentAlternativeCurrencyCode */
    #[SerializedName('cbc:PaymentAlternativeCurrencyCode')]
    public $PaymentAlternativeCurrencyCode;

    // /** @var AccountingCostCode */
    // #[SerializedName('cbc:AccountingCostCode')]
    // public $AccountingCostCode;

    /** @var string */
    #[SerializedName('cbc:AccountingCost')]
    public string $AccountingCost;

    // /** @var LineCountNumeric */
    // #[SerializedName('cbc:LineCountNumeric')]
    // public $LineCountNumeric;

    // /** @var string */
    // #[SerializedName('cbc:BuyerReference')]
    // public string $BuyerReference;

    // /** @var InvoicePeriod[] */
    // #[SerializedName('cac:InvoicePeriod')]
    // public array $InvoicePeriod;

    // /** @var OrderReference */
    // #[SerializedName('cac:OrderReference')]
    // public $OrderReference;

    // /** @var BillingReference[] */
    // #[SerializedName('cac:BillingReference')]
    // public array $BillingReference;

    /** @var DespatchDocumentReference[] */
    #[SerializedName('cac:DespatchDocumentReference')]
    public array $DespatchDocumentReference;

    /** @var ReceiptDocumentReference[] */
    #[SerializedName('cac:ReceiptDocumentReference')]
    public array $ReceiptDocumentReference;

    /** @var StatementDocumentReference[] */
    #[SerializedName('cac:StatementDocumentReference')]
    public array $StatementDocumentReference;

    /** @var OriginatorDocumentReference[] */
    #[SerializedName('cac:OriginatorDocumentReference')]
    public array $OriginatorDocumentReference;

    /** @var ContractDocumentReference[] */
    #[SerializedName('cac:ContractDocumentReference')]
    public array $ContractDocumentReference;

    /** @var AdditionalDocumentReference[] */
    #[SerializedName('cac:AdditionalDocumentReference')]
    public array $AdditionalDocumentReference;

    /** @var ProjectReference[] */
    #[SerializedName('cac:ProjectReference')]
    public array $ProjectReference;

    // /** @var Signature[] */
    // #[SerializedName('cac:Signature')]
    // public array $Signature;

    // /** @var AccountingSupplierParty */
    // #[NotNull]
    // #[NotBlank]
    // #[Valid]
    // #[SerializedName('cac:AccountingSupplierParty')]
    // public $AccountingSupplierParty;

    // /** @var AccountingCustomerParty */
    // #[NotNull]
    // #[NotBlank]
    // #[Valid]
    // #[SerializedName('cac:AccountingCustomerParty')]
    // public $AccountingCustomerParty;

    /** @var PayeeParty */
    #[SerializedName('cac:PayeeParty')]
    public $PayeeParty;

    /** @var BuyerCustomerParty */
    #[SerializedName('cac:BuyerCustomerParty')]
    public $BuyerCustomerParty;

    // /** @var SellerSupplierParty */
    // #[SerializedName('cac:SellerSupplierParty')]
    // public $SellerSupplierParty;

    /** @var TaxRepresentativeParty */
    #[SerializedName('cac:TaxRepresentativeParty')]
    public $TaxRepresentativeParty;

    /** @var Delivery[] */
    #[SerializedName('cac:Delivery')]
    public array $Delivery;

    /** @var DeliveryTerms */
    #[SerializedName('cac:DeliveryTerms')]
    public $DeliveryTerms;

    /** @var PaymentMeans[] */
    #[SerializedName('cac:PaymentMeans')]
    public array $PaymentMeans;

    /** @var PaymentTerms[] */
    #[SerializedName('cac:PaymentTerms')]
    public array $PaymentTerms;

    // /** @var PrepaidPayment[] */
    // #[SerializedName('cac:PrepaidPayment')]
    // public array $PrepaidPayment;

    // /** @var AllowanceCharge[] */
    // #[SerializedName('cac:AllowanceCharge')]
    // public array $AllowanceCharge;

    // /** @var TaxExchangeRate */
    // #[SerializedName('cac:TaxExchangeRate')]
    // public $TaxExchangeRate;

    // /** @var PricingExchangeRate */
    // #[SerializedName('cac:PricingExchangeRate')]
    // public $PricingExchangeRate;

    // /** @var PaymentExchangeRate */
    // #[SerializedName('cac:PaymentExchangeRate')]
    // public $PaymentExchangeRate;

    // /** @var PaymentAlternativeExchangeRate */
    // #[SerializedName('cac:PaymentAlternativeExchangeRate')]
    // public $PaymentAlternativeExchangeRate;

    // /** @var TaxTotal[] */
    // #[SerializedName('cac:TaxTotal')]
    // public array $TaxTotal;

    // /** @var WithholdingTaxTotal[] */
    // #[SerializedName('cac:WithholdingTaxTotal')]
    // public array $WithholdingTaxTotal;

    // /** @var LegalMonetaryTotal */
    // #[NotNull]
    // #[NotBlank]
    // #[Valid]
    // #[SerializedName('cac:LegalMonetaryTotal')]
    // public $LegalMonetaryTotal;

    // /** @var InvoiceLine[] */
    // #[NotNull]
    // #[NotBlank]
    // #[Valid]
    // #[SerializedName('cac:InvoiceLine')]
    // public array $InvoiceLine;
}
