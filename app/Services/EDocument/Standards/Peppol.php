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

namespace App\Services\EDocument\Standards;

use App\Models\Company;
use App\Models\Invoice;
use App\Helpers\Invoice\Taxer;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Services\EDocument\Standards\Peppol\RO;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentification;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\Party as PeppolParty;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use App\Services\EDocument\Standards\Settings\PropertyResolver;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotal as PeppolTaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxScheme as PeppolTaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomerAssignedAccountID;
use InvoiceNinja\EInvoice\Models\Peppol\LocationType\PhysicalLocation;

class Peppol extends AbstractService
{
    use Taxer;
    use NumberFormatter;

    /**
     * Assumptions:
     *
     * Line Item Taxes Only
     * Exclusive Taxes
     *
     *
     * used as a proxy for
     * the schemeID of partyidentification
     * property - for Storecove only:
     *
     * Used in the format key:value
     *
     * ie. IT:IVA / DE:VAT
     *
     * Note there are multiple options for the following countries:
     *
     * US (EIN/SSN) employer identification number / social security number
     * IT (CF/IVA) Codice Fiscale (person/company identifier) / company vat number
     *
     * @var array
     */
    private array $schemeIdIdentifiers = [
        'US' => 'EIN',
        'US' => 'SSN',
        'NZ' => 'GST',
        'CH' => 'VAT', // VAT number = CHE - 999999999 - MWST|IVA|VAT
        'IS' => 'VAT',
        'LI' => 'VAT',
        'NO' => 'VAT',
        'AD' => 'VAT',
        'AL' => 'VAT',
        'AT' => 'VAT', //Tested - Routing GOV + Business
        'BA' => 'VAT',
        'BE' => 'VAT',
        'BG' => 'VAT',
        'AU' => 'ABN', //Australia
        'CA' => 'CBN', //Canada
        'MX' => 'RFC', //Mexico
        'NZ' => 'GST', //Nuuu zulund
        'GB' => 'VAT', //Great Britain
        'SA' => 'TIN', //South Africa
        'CY' => 'VAT',
        'CZ' => 'VAT',
        'DE' => 'VAT', //tested - Requires Payment Means to be defined.
        'DK' => 'ERST',
        'EE' => 'VAT',
        'ES' => 'VAT', //tested - B2G pending
        'FI' => 'VAT',
        'FR' => 'VAT', //tested - Need to ensure Siren/Siret routing
        'GR' => 'VAT',
        'HR' => 'VAT',
        'HU' => 'VAT',
        'IE' => 'VAT',
        'IT' => 'IVA', //tested - Requires a Customer Party Identification (VAT number) - 'IT senders must first be provisioned in the partner system.' Cannot test currently
        'IT' => 'CF', //tested - Requires a Customer Party Identification (VAT number) - 'IT senders must first be provisioned in the partner system.' Cannot test currently
        'LT' => 'VAT',
        'LU' => 'VAT',
        'LV' => 'VAT',
        'MC' => 'VAT',
        'ME' => 'VAT',
        'MK' => 'VAT',
        'MT' => 'VAT',
        'NL' => 'VAT',
        'PL' => 'VAT',
        'PT' => 'VAT',
        'RO' => 'VAT',
        'RS' => 'VAT',
        'SE' => 'VAT',
        'SI' => 'VAT',
        'SK' => 'VAT',
        'SM' => 'VAT',
        'TR' => 'VAT',
        'VA' => 'VAT',
        'IN' => 'GSTIN',
        'JP' => 'IIN',
        'MY' => 'TIN',
        'SG' => 'GST',
        'GB' => 'VAT',
        'SA' => 'TIN',
    ];

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
        "896" => "Debit note related to self-billed invoice"
    ];

    //         0       1      2      3
    // ["Country" => ["B2X","Legal","Tax","Routing"],
    private array $routing_rules = [
        "US" => [
            ["B","DUNS, GLN, LEI","US:EIN","DUNS, GLN, LEI"],
            // ["B","DUNS, GLN, LEI","US:SSN","DUNS, GLN, LEI"],
        ],
        "CA" => ["B","CA:CBN",false,"CA:CBN"],
        "MX" => ["B","MX:RFC",false,"MX:RFC"],
        "AU" => ["B+G","AU:ABN",false,"AU:ABN"],
        "NZ" => ["B+G","GLN","NZ:GST","GLN"],
        "CH" => ["B+G","CH:UIDB","CH:VAT","CH:UIDB"],
        "IS" => ["B+G","IS:KTNR","IS:VAT","IS:KTNR"],
        "LI" => ["B+G","","LI:VAT","LI:VAT"],
        "NO" => ["B+G","NO:ORG","NO:VAT","NO:ORG"],
        "AD" => ["B+G","","AD:VAT","AD:VAT"],
        "AL" => ["B+G","","AL:VAT","AL:VAT"],
        "AT" => [
            ["G","AT:GOV",false,"9915:b"],
            ["B","","AT:VAT","AT:VAT"],
        ],
        "BA" => ["B+G","","BA:VAT","BA:VAT"],
        "BE" => ["B+G","BE:EN","BE:VAT","BE:EN"],
        "BG" => ["B+G","","BG:VAT","BG:VAT"],
        "CY" => ["B+G","","CY:VAT","CY:VAT"],
        "CZ" => ["B+G","","CZ:VAT","CZ:VAT"],
        "DE" => [
            ["G","DE:LWID",false,"DE:LWID"],
            ["B","","DE:VAT","DE:VAT"],
        ],
        "DK" => ["B+G","DK:DIGST","DK:ERST","DK:DIGST"],
        "EE" => ["B+G","EE:CC","EE:VAT","EE:CC"],
        "ES" => ["B","","ES:VAT","ES:VAT"],
        "FI" => ["B+G","FI:OVT","FI:VAT","FI:OVT"],
        "FR" => [
            ["G","FR:SIRET + customerAssignedAccountIdValue",false,"0009:11000201100044"],
            ["B","FR:SIRENE or FR:SIRET","FR:VAT","FR:SIRENE or FR:SIRET"],
        ],
        "GR" => ["B+G","","GR:VAT","GR:VAT"],
        "HR" => ["B+G","","HR:VAT","HR:VAT"],
        "HU" => ["B+G","","HU:VAT","HU:VAT"],
        "IE" => ["B+G","","IE:VAT","IE:VAT"],
        "IT" => [
            ["G","","IT:IVA","IT:CUUO"], // (Peppol)
            ["B","","IT:IVA","IT:CUUO"], // (SDI)
            // ["B","","IT:CF","IT:CUUO"], // (SDI)
            ["C","","IT:CF","Email"],// (SDI)
            ["G","","IT:IVA","IT:CUUO"],// (SDI)
        ],
        "LT" => ["B+G","LT:LEC","LT:VAT","LT:LEC"],
        "LU" => ["B+G","LU:MAT","LU:VAT","LU:VAT"],
        "LV" => ["B+G","","LV:VAT","LV:VAT"],
        "MC" => ["B+G","","MC:VAT","MC:VAT"],
        "ME" => ["B+G","","ME:VAT","ME:VAT"],
        "MK" => ["B+G","","MK:VAT","MK:VAT"],
        "MT" => ["B+G","","MT:VAT","MT:VAT"],
        "NL" => ["G","NL:OINO",false,"NL:OINO"],
        "NL" => ["B","NL:KVK","NL:VAT","NL:KVK or NL:VAT"],
        "PL" => ["G+B","","PL:VAT","PL:VAT"],
        "PT" => ["G+B","","PT:VAT","PT:VAT"],
        "RO" => ["G+B","","RO:VAT","RO:VAT"],
        "RS" => ["G+B","","RS:VAT","RS:VAT"],
        "SE" => ["G+B","SE:ORGNR","SE:VAT","SE:ORGNR"],
        "SI" => ["G+B","","SI:VAT","SI:VAT"],
        "SK" => ["G+B","","SK:VAT","SK:VAT"],
        "SM" => ["G+B","","SM:VAT","SM:VAT"],
        "TR" => ["G+B","","TR:VAT","TR:VAT"],
        "VA" => ["G+B","","VA:VAT","VA:VAT"],
        "IN" => ["B","","IN:GSTIN","Email"],
        "JP" => ["B","JP:SST","JP:IIN","JP:SST"],
        "MY" => ["B","MY:EIF","MY:TIN","MY:EIF"],
        "SG" => [
            ["G","SG:UEN",false,"0195:SGUENT08GA0028A"],
            ["B","SG:UEN","SG:GST","SG:UEN"],
        ],
        "GB" => ["B","","GB:VAT","GB:VAT"],
        "SA" => ["B","","SA:TIN","Email"],
        "Other" => ["B","DUNS, GLN, LEI",false,"DUNS, GLN, LEI"],
    ];

    private Company $company;

    private InvoiceSum | InvoiceSumInclusive $calc;

    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private EInvoice $e;

    private array $storecove_meta = [];

    /**
    * @param Invoice $invoice
    */
    public function __construct(public Invoice $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->e = new EInvoice();
        $this->setSettings()->setInvoice();
    }

    /**
     * Rehydrates an existing e invoice - or - scaffolds a new one
     *
     * @return self
     */
    private function setInvoice(): self
    {

        if($this->invoice->e_invoice) {

            $this->p_invoice = $this->e->decode('Peppol', json_encode($this->invoice->e_invoice->Invoice), 'json');

            return $this;

        }

        $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

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

    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice
    {
        //@todo - need to process this and remove null values
        return $this->p_invoice;

    }

    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->p_invoice, 'xml');

        $prefix = '<?xml version="1.0" encoding="utf-8"?>
    <Invoice
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';

        return str_ireplace(['\n','<?xml version="1.0"?>'], ['', $prefix], $xml);

    }

    public function toJson(): string
    {
        $e = new EInvoice();
        $json =  $e->encode($this->p_invoice, 'json');

        return $json;

    }

    public function toArray(): array
    {
        return json_decode($this->toJson(), true);
    }

    public function run()
    {
        $this->p_invoice->ID = $this->invoice->number;
        $this->p_invoice->IssueDate = new \DateTime($this->invoice->date);

        if($this->invoice->due_date) {
            $this->p_invoice->DueDate = new \DateTime($this->invoice->due_date);
        }

        $this->p_invoice->InvoiceTypeCode = 380; //
        $this->p_invoice->AccountingSupplierParty = $this->getAccountingSupplierParty();
        $this->p_invoice->AccountingCustomerParty = $this->getAccountingCustomerParty();
        $this->p_invoice->InvoiceLine = $this->getInvoiceLines();

        // $this->p_invoice->TaxTotal = $this->getTotalTaxes(); it only wants the aggregate here!!
        $this->p_invoice->LegalMonetaryTotal = $this->getLegalMonetaryTotal();

        $this->senderSpecificLevelMutators()
             ->receiverSpecificLevelMutators();

        return $this;

    }

    private function getLegalMonetaryTotal(): LegalMonetaryTotal
    {
        $taxable = $this->getTaxable();

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->currencyID = $this->invoice->client->currency()->code;
        $lea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes, 2) : $taxable;
        $lmt->LineExtensionAmount = $lea;

        $tea = new TaxExclusiveAmount();
        $tea->currencyID = $this->invoice->client->currency()->code;
        $tea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes, 2) : $taxable;
        $lmt->TaxExclusiveAmount = $tea;

        $tia = new TaxInclusiveAmount();
        $tia->currencyID = $this->invoice->client->currency()->code;
        $tia->amount = $this->invoice->amount;
        $lmt->TaxInclusiveAmount = $tia;

        $pa = new PayableAmount();
        $pa->currencyID = $this->invoice->client->currency()->code;
        $pa->amount = $this->invoice->amount;
        $lmt->PayableAmount = $pa;

        return $lmt;
    }

    private function getTotalTaxAmount(): float
    {
        if(!$this->invoice->total_taxes) {
            return 0;
        } elseif($this->invoice->uses_inclusive_taxes) {
            return $this->invoice->total_taxes;
        }

        return $this->calcAmountLineTax($this->invoice->tax_rate1, $this->invoice->amount) ?? 0;
    }

    private function getTotalTaxes(): array
    {
        $taxes = [];

        $type_id = $this->invoice->line_items[0]->type_id;

        // if(strlen($this->invoice->tax_name1 ?? '') > 1) {

        $tax_amount = new TaxAmount();
        $tax_amount->currencyID = $this->invoice->client->currency()->code;
        $tax_amount->amount = $this->getTotalTaxAmount();

        $tax_subtotal = new TaxSubtotal();
        $tax_subtotal->TaxAmount = $tax_amount;

        $taxable_amount = new TaxableAmount();
        $taxable_amount->currencyID = $this->invoice->client->currency()->code;
        $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->invoice->amount - $this->invoice->total_taxes : $this->invoice->amount;
        $tax_subtotal->TaxableAmount = $taxable_amount;

        $tc = new TaxCategory();
        $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
        $tc->Percent = $this->invoice->tax_rate1;
        $ts = new PeppolTaxScheme();
        $ts->ID = strlen($this->invoice->tax_name1 ?? '') > 1 ? $this->invoice->tax_name1 : '0';
        $tc->TaxScheme = $ts;
        $tax_subtotal->TaxCategory = $tc;

        $tax_total = new TaxTotal();
        $tax_total->TaxAmount = $tax_amount;
        $tax_total->TaxSubtotal[] = $tax_subtotal;

        $taxes[] = $tax_total;
        // }


        if(strlen($this->invoice->tax_name2 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($this->invoice->tax_rate2, $this->invoice->amount) : $this->calcAmountLineTax($this->invoice->tax_rate2, $this->invoice->amount);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->invoice->amount - $this->invoice->total_taxes : $this->invoice->amount;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $this->invoice->tax_rate2;
            $ts = new PeppolTaxScheme();
            $ts->ID = $this->invoice->tax_name2;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $taxes[] = $tax_total;

        }

        if(strlen($this->invoice->tax_name3 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($this->invoice->tax_rate3, $this->invoice->amount) : $this->calcAmountLineTax($this->invoice->tax_rate3, $this->invoice->amount);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->invoice->amount - $this->invoice->total_taxes : $this->invoice->amount;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $this->invoice->tax_rate3;
            $ts = new PeppolTaxScheme();
            $ts->ID = $this->invoice->tax_name3;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $taxes[] = $tax_total;

        }


        return $taxes;
    }

    private function getInvoiceLines(): array
    {
        $lines = [];

        foreach($this->invoice->line_items as $key => $item) {

            $_item = new Item();
            $_item->Name = $item->product_key;
            $_item->Description = $item->notes;

            $line = new InvoiceLine();
            $line->ID = $key + 1;
            $line->InvoicedQuantity = $item->quantity;

            $lea = new LineExtensionAmount();
            $lea->currencyID = $this->invoice->client->currency()->code;
            // $lea->amount = $item->line_total;
            $lea->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $item->line_total;
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            $item_taxes = $this->getItemTaxes($item);

            if(count($item_taxes) > 0) {
                $line->TaxTotal = $item_taxes;
            }
            // else {
            //     $line->TaxTotal = $this->zeroTaxAmount();
            // }

            $price = new Price();
            $pa = new PriceAmount();
            $pa->currencyID = $this->invoice->client->currency()->code;
            $pa->amount = $this->costWithDiscount($item) - ($this->invoice->uses_inclusive_taxes ? ($this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) / $item->quantity) : 0);
            $price->PriceAmount = $pa;

            $line->Price = $price;

            $lines[] = $line;
        }

        return $lines;
    }

    private function costWithDiscount($item)
    {
        $cost = $item->cost;

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $cost -= $item->discount / $item->quantity;
            } else {
                $cost -= $cost * $item->discount / 100;
            }
        }

        return $cost;
    }

    private function zeroTaxAmount(): array
    {
        $blank_tax = [];

        $tax_amount = new TaxAmount();
        $tax_amount->currencyID = $this->invoice->client->currency()->code;
        $tax_amount->amount = '0';
        $tax_subtotal = new TaxSubtotal();
        $tax_subtotal->TaxAmount = $tax_amount;

        $taxable_amount = new TaxableAmount();
        $taxable_amount->currencyID = $this->invoice->client->currency()->code;
        $taxable_amount->amount = '0';
        $tax_subtotal->TaxableAmount = $taxable_amount;
        $tc = new TaxCategory();
        $tc->ID = 'Z';
        $tc->Percent = 0;
        $ts = new PeppolTaxScheme();
        $ts->ID = '0';
        $tc->TaxScheme = $ts;
        $tax_subtotal->TaxCategory = $tc;

        $tax_total = new TaxTotal();
        $tax_total->TaxAmount = $tax_amount;
        $tax_total->TaxSubtotal[] = $tax_subtotal;
        $blank_tax[] = $tax_total;


        return $blank_tax;
    }

    private function getItemTaxes(object $item): array
    {
        $item_taxes = [];

        if(strlen($item->tax_name1 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $this->calcAmountLineTax($item->tax_rate1, $item->line_total);
            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $tax_amount->amount : $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;
            $tc = new TaxCategory();
            $tc->ID = $item->type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $item->tax_rate1;
            $ts = new PeppolTaxScheme();
            $ts->ID = $item->tax_name1;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;

        }


        if(strlen($item->tax_name2 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate2, $item->line_total) : $this->calcAmountLineTax($item->tax_rate2, $item->line_total);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $item->type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $item->tax_rate2;
            $ts = new PeppolTaxScheme();
            $ts->ID = $item->tax_name2;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;


            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;


        }


        if(strlen($item->tax_name3 ?? '') > 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;

            $tax_amount->amount = $this->invoice->uses_inclusive_taxes ? $this->calcInclusiveLineTax($item->tax_rate3, $item->line_total) : $this->calcAmountLineTax($item->tax_rate3, $item->line_total);

            $tax_subtotal = new TaxSubtotal();
            $tax_subtotal->TaxAmount = $tax_amount;

            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = $item->line_total;
            $tax_subtotal->TaxableAmount = $taxable_amount;


            $tc = new TaxCategory();
            $tc->ID = $item->type_id == '2' ? 'HUR' : 'C62';
            $tc->Percent = $item->tax_rate3;
            $ts = new PeppolTaxScheme();
            $ts->ID = $item->tax_name3;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;


        }

        return $item_taxes;
    }

    private function getAccountingSupplierParty(): AccountingSupplierParty
    {

        $asp = new AccountingSupplierParty();

        $party = new Party();
        $party_name = new PartyName();
        $party_name->Name = $this->invoice->company->present()->name();
        $party->PartyName[] = $party_name;

        $address = new Address();
        $address->CityName = $this->invoice->company->settings->city;
        $address->StreetName = $this->invoice->company->settings->address1;
        // $address->BuildingName = $this->invoice->company->settings->address2;
        $address->PostalZone = $this->invoice->company->settings->postal_code;
        $address->CountrySubentity = $this->invoice->company->settings->state;
        // $address->CountrySubentityCode = $this->invoice->company->settings->state;

        $country = new Country();
        $country->IdentificationCode = $this->invoice->company->country()->iso_3166_2;
        $address->Country = $country;

        $party->PostalAddress = $address;
        $party->PhysicalLocation = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->getSetting('Invoice.AccountingSupplierParty.Party.Contact') ?? $this->invoice->company->owner()->present()->email();
        $contact->Telephone = $this->getSetting('Invoice.AccountingSupplierParty.Party.Telephone') ?? $this->invoice->company->getSetting('phone');
        $contact->Name = $this->getSetting('Invoice.AccountingSupplierParty.Party.Name') ?? $this->invoice->company->owner()->present()->name();

        $party->Contact = $contact;

        $asp->Party = $party;

        return $asp;
    }

    private function resolveTaxScheme(): mixed
    {
        $rules = isset($this->routing_rules[$this->invoice->client->country->iso_3166_2]) ? $this->routing_rules[$this->invoice->client->country->iso_3166_2] : [false, false, false, false,];

        $code = false;

        match($this->invoice->client->classification) {
            "business" => $code = "B",
            "government" => $code = "G",
            "individual" => $code = "C",
            default => $code = false,
        };

        //single array
        if(is_array($rules) && !is_array($rules[0])) {
            return $rules[2];
        }

        foreach($rules as $rule) {
            if(stripos($rule[0], $code) !== false) {
                return $rule[2];
            }
        }

        return false;
    }

    private function getAccountingCustomerParty(): AccountingCustomerParty
    {

        $acp = new AccountingCustomerParty();

        $party = new Party();

        if(strlen($this->invoice->client->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();

            $vatID = new ID();

            if($scheme = $this->resolveTaxScheme()) {
                $vatID->schemeID = $scheme;
            }

            $vatID->value = $this->invoice->client->vat_number;
            $pi->ID = $vatID;

            $party->PartyIdentification[] = $pi;

        }

        $party_name = new PartyName();
        $party_name->Name = $this->invoice->client->present()->name();
        $party->PartyName[] = $party_name;

        $address = new Address();
        $address->CityName = $this->invoice->client->city;
        $address->StreetName = $this->invoice->client->address1;
        // $address->BuildingName = $this->invoice->client->address2;
        $address->PostalZone = $this->invoice->client->postal_code;
        $address->CountrySubentity = $this->invoice->client->state;
        // $address->CountrySubentityCode = $this->invoice->client->state;


        $country = new Country();
        $country->IdentificationCode = $this->invoice->client->country->iso_3166_2;
        $address->Country = $country;

        $party->PostalAddress = $address;

        $physical_location = new PhysicalLocation();
        $physical_location->Address = $address;

        $party->PhysicalLocation = $physical_location;
        ;

        $contact = new Contact();
        $contact->ElectronicMail = $this->invoice->client->present()->email();

        $party->Contact = $contact;

        $acp->Party = $party;

        return $acp;
    }

    private function getTaxable(): float
    {
        $total = 0;

        foreach ($this->invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($this->invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        if ($this->invoice->discount > 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $this->invoice->discount;
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if ($this->invoice->custom_surcharge1 && $this->invoice->custom_surcharge_tax1) {
            $total += $this->invoice->custom_surcharge1;
        }

        if ($this->invoice->custom_surcharge2 && $this->invoice->custom_surcharge_tax2) {
            $total += $this->invoice->custom_surcharge2;
        }

        if ($this->invoice->custom_surcharge3 && $this->invoice->custom_surcharge_tax3) {
            $total += $this->invoice->custom_surcharge3;
        }

        if ($this->invoice->custom_surcharge4 && $this->invoice->custom_surcharge_tax4) {
            $total += $this->invoice->custom_surcharge4;
        }

        return $total;
    }

    /////////////////  Helper Methods /////////////////////////

    private function getClientRoutingCode(): string
    {
        $receiver_identifiers = $this->routing_rules[$this->invoice->client->country->iso_3166_2];
        $client_classification = $this->invoice->client->classification == 'government' ? 'G' : 'B';

        if(count($receiver_identifiers) > 1) {

            foreach($receiver_identifiers as $ident) {
                if(str_contains($ident[0], $client_classification)) {
                    return $ident[3];
                }
            }

        } elseif(count($receiver_identifiers) == 1) {
            return $receiver_identifiers[3];
        }

        throw new \Exception("e-invoice generation halted:: Could not resolve the Tax Code for this client? {$this->invoice->client->hashed_id}");

    }

    /**
     * setInvoiceDefaults
     *
     * Stubs a default einvoice
     * @return self
     */
    public function setInvoiceDefaults(): self
    {
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
        foreach($settings as $prop => $visibility) {

            if($prop_value = $this->getSetting($prop)) {
                $this->p_invoice->{$prop} = $prop_value;
            }

        }

        return $this;
    }

    /**
     * getSetting
     *
     * Attempts to harvest and return a preconfigured prop from company / client / invoice settings
     *
     * @param  string $property_path
     * @return mixed
     */
    public function getSetting(string $property_path): mixed
    {

        if($prop_value = PropertyResolver::resolve($this->p_invoice, $property_path)) {
            return $prop_value;
        } elseif($prop_value = PropertyResolver::resolve($this->_client_settings, $property_path)) {
            return $prop_value;
        } elseif($prop_value = PropertyResolver::resolve($this->_company_settings, $property_path)) {
            return $prop_value;
        }
        return null;

    }

    private function getClientSetting(string $property_path): mixed
    {
        return PropertyResolver::resolve($this->_client_settings, $property_path);
    }

    private function getCompanySetting(string $property_path): mixed
    {
        return PropertyResolver::resolve($this->_company_settings, $property_path);
    }
    /**
     * senderSpecificLevelMutators
     *
     * Runs sender level specific requirements for the e-invoice,
     *
     * ie, mutations that are required by the senders country.
     *
     * @return self
     */
    private function senderSpecificLevelMutators(): self
    {

        if(method_exists($this, $this->invoice->company->country()->iso_3166_2)) {
            $this->{$this->invoice->company->country()->iso_3166_2}();
        }

        return $this;
    }

    /**
     * receiverSpecificLevelMutators
     *
     * Runs receiver level specific requirements for the e-invoice
     *
     * ie mutations that are required by the receiving country
     * @return self
     */
    private function receiverSpecificLevelMutators(): self
    {

        if(method_exists($this, "client_{$this->invoice->company->country()->iso_3166_2}")) {
            $this->{"client_{$this->invoice->company->country()->iso_3166_2}"}();
        }

        return $this;
    }


    /**
     * setPaymentMeans
     *
     * Sets the payment means - if it exists
     * @param  bool $required
     * @return self
     */
    private function setPaymentMeans(bool $required = false): self
    {

        if(isset($this->p_invoice->PaymentMeans)) {
            return $this;
        } elseif($paymentMeans = $this->getSetting('Invoice.PaymentMeans')) {
            $this->p_invoice->PaymentMeans = is_array($paymentMeans) ? $paymentMeans : [$paymentMeans];
            return $this;
        }

        return $this->checkRequired($required, "Payment Means");

    }

    /**
     * setOrderReference
     *
     * sets the order reference - if it exists (Never rely on settings for this)
     *
     * @param  bool $required
     * @return self
     */
    private function setOrderReference(bool $required = false): self
    {
        $this->p_invoice->BuyerReference = $this->invoice->po_number ?? '';

        if(strlen($this->invoice->po_number ?? '') > 1) {
            $order_reference = new OrderReference();
            $id = new ID();
            $id->value = $this->invoice->po_number;

            $order_reference->ID = $id;

            $this->p_invoice->OrderReference = $order_reference;

            // $this->setStorecoveMeta(["document" => [
            //                             "invoice" => [
            //                                 [
            //                                 "references" => [
            //                                     "documentType" => "purchase_order",
            //                                     "documentId" => $this->invoice->po_number,
            //                                 ],
            //                             ],
            //                         ],
            //                     ]
            //                 ]);

            return $this;
        }

        return $this->checkRequired($required, 'Order Reference');

    }

    /**
     * setCustomerAssignedAccountId
     *
     * Sets the client id_number CAN rely on settings
     *
     * @param  bool $required
     * @return self
     */
    private function setCustomerAssignedAccountId(bool $required = false): self
    {
        //@phpstan-ignore-next-line
        if(isset($this->p_invoice->AccountingCustomerParty->CustomerAssignedAccountID)) {
            return $this;
        } elseif($customer_assigned_account_id = $this->getSetting('Invoice.AccountingCustomerParty.CustomerAssignedAccountID')) {

            $this->p_invoice->AccountingCustomerParty->CustomerAssignedAccountID = $customer_assigned_account_id;
            return $this;
        } elseif(strlen($this->invoice->client->id_number ?? '') > 1) {

            $customer_assigned_account_id = new CustomerAssignedAccountID();
            $customer_assigned_account_id->value = $this->invoice->client->id_number;

            $this->p_invoice->AccountingCustomerParty->CustomerAssignedAccountID = $customer_assigned_account_id;
            return $this;
        }

        //@phpstan-ignore-next-line
        return $this->checkRequired($required, 'Client ID Number');

    }

    /**
     * Check Required
     *
     * Throws if a required field is missing.
     *
     * @param  bool $required
     * @param  string $section
     * @return self
     */
    private function checkRequired(bool $required, string $section): self
    {

        return $required ? throw new \Exception("e-invoice generation halted:: {$section} required") : $this;

    }


    /**
     * Builds the Routing object for StoreCove
     *
     * @param  array $identifiers
     * @return array
     */
    private function buildRouting(array $identifiers): array
    {

        return
        [
            "routing" => [
                "eIdentifiers" =>
                    $identifiers,

            ]
        ];
    }

    private function setEmailRouting(string $email): self
    {
        nlog($email);

        $meta = $this->getStorecoveMeta();

        if(isset($meta['routing']['emails'])) {
            $emails = $meta['routing']['emails'];
            array_push($emails, $email);
            $meta['routing']['emails'] = $emails;
        } else {
            $meta['routing']['emails'] = [$email];
        }

        $this->setStorecoveMeta($meta);

        return $this;
    }

    /**
     * setStorecoveMeta
     *
     * updates the storecove payload for sending documents
     *
     * @param  array $meta
     * @return self
     */
    private function setStorecoveMeta(array $meta): self
    {

        $this->storecove_meta = array_merge($this->storecove_meta, $meta);

        return $this;
    }

    public function getStorecoveMeta(): array
    {
        return $this->storecove_meta;
    }







    ////////////////////////// Country level mutators /////////////////////////////////////

    /**
     * DE
     *
     * @Completed
     * @Tested
     *
     * @return self
     */
    private function DE(): self
    {

        $this->setPaymentMeans(true);

        return $this;
    }

    /**
     * CH
     *
     * @Completed
     *
     * Completed - QR-Bill to be implemented at a later date.
     * @return self
     */
    private function CH(): self
    {
        return $this;
    }

    /**
     * AT
     *
     * @Pending
     *
     * Need to ensure when sending to government entities that we route appropriately
     * Also need to ensure customerAssignedAccountIdValue is set so that the sender can be resolved.
     *
     * Need a way to define if the client is a government entity.
     *
     * @return self
     */
    private function AT(): self
    {
        //special fields for sending to AT:GOV

        if($this->invoice->client->classification == 'government') {
            //routing "b" for production "test" for test environment
            $this->setStorecoveMeta($this->buildRouting(["scheme" => 'AT:GOV', "id" => 'b']));

            //for government clients this must be set.
            $this->setCustomerAssignedAccountId(true);
        }

        return $this;
    }

    private function AU(): self
    {

        //if payment means are included, they must be the same `type`
        return $this;
    }

    /**
     * ES
     *
     * @Pending
     * B2G configuration
     * B2G Testing
     *
     * testing. // routing identifier - 293098
     *
     * @return self
     */
    private function ES(): self
    {

        if(!isset($this->invoice->due_date)) {
            $this->p_invoice->DueDate = new \DateTime($this->invoice->date);
        }

        if($this->invoice->client->classification == 'business' && $this->invoice->company->getSetting('classification') == 'business') {
            //must have a paymentmeans as credit_transfer
            $this->setPaymentMeans(true);
        }

        // For B2G, provide three ES:FACE identifiers in the routing object,
        // as well as the ES:VAT tax identifier in the accountingCustomerParty.publicIdentifiers.
        // The invoice will then be routed through the FACe network. The three required ES:FACE identifiers are as follows:
        //   "routing": {
        //     "eIdentifiers":[
        //       {
        //         "scheme": "ES:FACE",
        //         "id": "L01234567",
        //         "role": "ES-01-FISCAL"
        //       },
        //       {
        //         "scheme": "ES:FACE",
        //         "id": "L01234567",
        //         "role": "ES-02-RECEPTOR"
        //       },
        //       {
        //         "scheme": "ES:FACE",
        //         "id": "L01234567",
        //         "role": "ES-03-PAGADOR"
        //       }
        //     ]
        //   }

        return $this;
    }

    private function FI(): self
    {

        // For Finvoice, provide an FI:OPID routing identifier and an FI:OVT legal identifier.
        // An FI:VAT is recommended. In many cases (depending on the sender/receiver country and the type of service/goods)
        // an FI:VAT is required. So we recommend always including this.

        return $this;
    }

    /**
     * FR
     * @Pending - clarification on codes needed
     *
     * @return self
     */
    private function FR(): self
    {

        // When sending invoices to the French government (Chorus Pro):
        // All invoices have to be routed to SIRET 0009:11000201100044. There is no test environment for sending to public entities.
        // The SIRET / 0009 identifier of the final recipient is to be included in the invoice.accountingCustomerParty.publicIdentifiers array.

        if($this->invoice->client->classification == 'government') {
            //route to SIRET 0009:11000201100044
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => '11000201100044']

                // ["scheme" => 'FR:SIRET', "id" => '0009:11000201100044']
            ]));

            // The SIRET / 0009 identifier of the final recipient is to be included in the invoice.accountingCustomerParty.publicIdentifiers array.
            $this->setCustomerAssignedAccountId(true);

        }

        if(strlen($this->invoice->client->id_number ?? '') == 9) {
            //SIREN
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => "{$this->invoice->client->id_number}"]

                // ["scheme" => 'FR:SIRET', "id" => "0002:{$this->invoice->client->id_number}"]
            ]));
        } else {
            //SIRET
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'FR:SIRET', "id" => "{$this->invoice->client->id_number}"]

                // ["scheme" => 'FR:SIRET', "id" => "0009:{$this->invoice->client->id_number}"]
            ]));
        }

        // Apparently this is not a special field according to support
        // sounds like it is optional
        // The service code must be sent in invoice.buyerReference (deprecated) or the invoice.references array (documentType buyer_reference)

        if(strlen($this->invoice->po_number ?? '') > 1) {
            $this->setOrderReference(false);
        }

        return $this;
    }

    private function IT(): self
    {

        // IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)
        if(in_array($this->invoice->client->classification, ['business','government']) && $this->invoice->company->country()->iso_3166_2 == 'IT') {

            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'IT:IVA', "id" => $this->invoice->client->vat_number],
                ["scheme" => 'IT:CUUO', "id" => $this->invoice->client->routing_id]
            ]));

            return $this;
        }

        // IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and the receiver IT:CUUO (codice destinatario)
        if($this->invoice->client->classification == 'individual' && $this->invoice->company->country()->iso_3166_2 == 'IT') {

            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => 'IT:CF', "id" => $this->invoice->client->vat_number],
                // ["scheme" => 'IT:CUUO', "id" => $this->invoice->client->routing_id]
            ]));

            $this->setEmailRouting($this->invoice->client->present()->email());

            return $this;
        }

        // IT Sender, non-IT Receiver
        // Provide the receiver tax identifier and any routing identifier applicable to the receiving country (see Receiver Identifiers).
        if($this->invoice->client->country->iso_3166_2 != 'IT' && $this->invoice->company->country()->iso_3166_2 == 'IT') {

            $code = $this->getClientRoutingCode();

            nlog("foreign receiver");
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => $code, "id" => $this->invoice->client->vat_number]
            ]));

            return $this;
        }

        return $this;
    }

    private function client_IT(): self
    {

        // non-IT Sender, IT Receiver, B2C
        // Provide the receiver IT:CF and an optional email. The invoice will be eReported and sent via email. Note that this cannot be a PEC email address.
        if(in_array($this->invoice->client->classification, ['individual']) && $this->invoice->company->country()->iso_3166_2 != 'IT') {

            return $this;
        }

        // non-IT Sender, IT Receiver, B2B/B2G
        // Provide the receiver IT:VAT and the receiver IT:CUUO (codice destinatario)

        return $this;

    }

    private function MY(): self
    {
        //way too much to digest here, delayed.
        return $this;
    }

    private function NL(): self
    {

        // When sending to public entities, the invoice.accountingSupplierParty.party.contact.email is mandatory.

        // Dutch senders and receivers require a legal identifier. For companies, this is NL:KVK, for public entities this is NL:OINO.

        return $this;
    }

    private function NZ(): self
    {
        // New Zealand uses a GLN to identify businesses. In addition, when sending invoices to a New Zealand customer, make sure you include the pseudo identifier NZ:GST as their tax identifier.
        return $this;
    }

    private function PL(): self
    {

        // Because using this network is not yet mandatory, the default workflow is to not use this network. Therefore, you have to force its use, as follows:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "PL:VAT",
        //         "id": "PL0101010101"
        //     }
        //   ],
        //   "networks": [
        //     {
        //       "application": "pl-ksef",
        //       "settings": {
        //         "enabled": true
        //       }
        //     }
        //   ]
        // }
        // Note this will only work if your LegalEntity has been setup for this network.

        return $this;
    }

    private function RO(): self
    {
        // Because using this network is not yet mandatory, the default workflow is to not use this network. Therefore, you have to force its use, as follows:
        $meta = ["networks" => [
                    [
                        "application" => "ro-anaf",
                        "settings" => [
                            "enabled" => true
                        ],
                    ],
                ]];

        $this->setStorecoveMeta($meta);

        $this->setStorecoveMeta($this->buildRouting([
               ["scheme" => 'RO:VAT', "id" => $this->invoice->client->vat_number],
           ]));

        $ro = new RO($this->invoice);

        $client_state = $this->getClientSetting('Invoice.AccountingSupplierParty.Party.PostalAddress.Address.CountrySubentity');
        $client_city = $this->getClientSetting('Invoice.AccountingCustomerParty.Party.PostalAddress.Address.CityName');

        $resolved_state = $ro->getStateCode($client_state);
        $resolved_city = $ro->getSectorCode($client_city);

        $this->p_invoice->AccountingCustomerParty->Party->PostalAddress->CountrySubentity = $resolved_state;
        $this->p_invoice->AccountingCustomerParty->Party->PostalAddress->CityName = $resolved_city;
        $this->p_invoice->AccountingCustomerParty->Party->PhysicalLocation->Address->CountrySubentity = $resolved_state;
        $this->p_invoice->AccountingCustomerParty->Party->PhysicalLocation->Address->CityName = $resolved_city;

        return $this;
    }

    private function SG(): self
    {
        //delayed  - stage 2
        return $this;
    }

    //Sweden
    private function SE(): self
    {
        // Deliver invoices to the "Svefaktura" co-operation of local Swedish service providers.
        // Routing is through the SE:ORGNR together with a network specification:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "SE:ORGNR",
        //         "id": "0012345678"
        //     }
        //   ],
        //   "networks": [
        //     {
        //       "application": "svefaktura",
        //       "settings": {
        //         "enabled": true
        //       }
        //     }
        //   ]
        // }
        // Use of the "Svefaktura" co-operation can also be induced by specifying an operator id, as follows:

        // "routing": {
        //   "eIdentifiers": [
        //     {
        //         "scheme": "SE:ORGNR",
        //         "id": "0012345678"
        //     },
        //     {
        //         "scheme": "SE:OPID",
        //         "id": "1234567890"
        //     }
        //   ]
        // }
        return $this;
    }
}
