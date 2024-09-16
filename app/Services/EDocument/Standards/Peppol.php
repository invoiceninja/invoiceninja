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
use App\Models\Product;
use App\Helpers\Invoice\Taxer;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Standards\Peppol\RO;
use App\Http\Requests\Client\StoreClientRequest;
use App\Services\EDocument\Gateway\Qvalia\Qvalia;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentification;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\Party as PeppolParty;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use App\Services\EDocument\Standards\Settings\PropertyResolver;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PeriodType\InvoicePeriod;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotal as PeppolTaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\IdentificationCode;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\LocationType\PhysicalLocation;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomerAssignedAccountID;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;
use InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount;

class Peppol extends AbstractService
{
    use Taxer;
    use NumberFormatter;


    //@todo - refactor and move storecove specific logic to the Storecove class

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

    private array $tax_codes = [
        'AE' => [
            'name' => 'Vat Reverse Charge',
            'description' => 'Code specifying that the standard VAT rate is levied from the invoicee.'
        ],
        'E' => [
            'name' => 'Exempt from Tax',
            'description' => 'Code specifying that taxes are not applicable.'
        ],
        'S' => [
            'name' => 'Standard rate',
            'description' => 'Code specifying the standard rate.'
        ],
        'Z' => [
            'name' => 'Zero rated goods',
            'description' => 'Code specifying that the goods are at a zero rate.'
        ],
        'G' => [
            'name' => 'Free export item, VAT not charged',
            'description' => 'Code specifying that the item is free export and taxes are not charged.'
        ],
        'O' => [
            'name' => 'Services outside scope of tax',
            'description' => 'Code specifying that taxes are not applicable to the services.'
        ],
        'K' => [
            'name' => 'VAT exempt for EEA intra-community supply of goods and services',
            'description' => 'A tax category code indicating the item is VAT exempt due to an intra-community supply in the European Economic Area.'
        ],
        'L' => [
            'name' => 'Canary Islands general indirect tax',
            'description' => 'Impuesto General Indirecto Canario (IGIC) is an indirect tax levied on goods and services supplied in the Canary Islands (Spain) by traders and professionals, as well as on import of goods.'
        ],
        'M' => [
            'name' => 'Tax for production, services and importation in Ceuta and Melilla',
            'description' => 'Impuesto sobre la Producción, los Servicios y la Importación (IPSI) is an indirect municipal tax, levied on the production, processing and import of all kinds of movable tangible property, the supply of services and the transfer of immovable property located in the cities of Ceuta and Melilla.'
        ],
        'B' => [
            'name' => 'Transferred (VAT), In Italy',
            'description' => 'VAT not to be paid to the issuer of the invoice but directly to relevant tax authority. This code is allowed in the EN 16931 for Italy only based on the Italian A-deviation.'
        ]
    ];

    private Company $company;

    private InvoiceSum | InvoiceSumInclusive $calc;

    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private EInvoice $e;

    private string $api_network = Qvalia::class; // Storecove::class; // Qvalia::class;
    
    public Qvalia | Storecove $gateway;

    /**
    * @param Invoice $invoice
    */
    public function __construct(public Invoice $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->e = new EInvoice();
        $this->gateway = new $this->api_network;
        $this->setSettings()->setInvoice();
    }
    
    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {
        $this->p_invoice->ID = $this->invoice->number;
        $this->p_invoice->IssueDate = new \DateTime($this->invoice->date);

        if($this->invoice->due_date) 
            $this->p_invoice->DueDate = new \DateTime($this->invoice->due_date);

        if(strlen($this->invoice->public_notes ?? '') > 0)
            $this->p_invoice->Note = $this->invoice->public_notes;

        $this->p_invoice->DocumentCurrencyCode = $this->invoice->client->currency()->code;


        if ($this->invoice->date && $this->invoice->due_date) {
            $ip = new InvoicePeriod();
            $ip->StartDate = new \DateTime($this->invoice->date);
            $ip->EndDate = new \DateTime($this->invoice->due_date);
            $this->p_invoice->InvoicePeriod[] = $ip;
        }
        
        if ($this->invoice->project_id) {
            $pr = new \InvoiceNinja\EInvoice\Models\Peppol\ProjectReferenceType\ProjectReference();
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
            $id->value = $this->invoice->project->number;
            $pr->ID = $id;
            $this->p_invoice->ProjectReference[] = $pr;
        }


        $this->p_invoice->InvoiceTypeCode = ($this->invoice->amount >= 0) ? 380 : 381; //
        $this->p_invoice->AccountingSupplierParty = $this->getAccountingSupplierParty();
        $this->p_invoice->AccountingCustomerParty = $this->getAccountingCustomerParty();
        $this->p_invoice->InvoiceLine = $this->getInvoiceLines();

        $this->p_invoice->LegalMonetaryTotal = $this->getLegalMonetaryTotal();

        $this->setOrderReference();

        $this->p_invoice = $this->gateway
                                ->mutator
                                ->senderSpecificLevelMutators()
                                ->receiverSpecificLevelMutators()
                                ->getPeppol();

        if(strlen($this->invoice->backup ?? '') == 0)
        {
            $this->invoice->e_invoice = $this->toObject();
            $this->invoice->save();
        }

        return $this;

    }

    /**
     * Rehydrates an existing e invoice - or - scaffolds a new one
     *
     * @return self
     */
    private function setInvoice(): self
    {

        if($this->invoice->e_invoice && isset($this->invoice->e_invoice->Invoice)) {

            $this->p_invoice = $this->e->decode('Peppol', json_encode($this->invoice->e_invoice->Invoice), 'json');


        $this->gateway
            ->mutator
            ->setInvoice($this->invoice)
            ->setPeppol($this->p_invoice)
            ->setClientSettings($this->_client_settings)
            ->setCompanySettings($this->_company_settings);

            return $this;

        }

        $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

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
     * getInvoice
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice
    {
        //@todo - need to process this and remove null values
        return $this->p_invoice;

    }
    
    /**
     * toXml
     *
     * @return string
     */
    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->p_invoice, 'xml');

        $prefix = '<?xml version="1.0" encoding="utf-8"?>
    <Invoice
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';

    nlog($xml);

        return str_ireplace(['\n','<?xml version="1.0"?>'], ['', $prefix], $xml);

    }
    
    /**
     * toJson
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
     * @return mixed
     */
    public function toObject(): mixed
    {

        $invoice = new \stdClass;
        $invoice->Invoice = json_decode($this->toJson());
        return $invoice;

    }
    
    /**
     * toArray
     *
     * @return array
     */
    public function toArray(): array
    {
        return ['Invoice' => json_decode($this->toJson(), true)];
    }
    

    private function setOrderReference(): self
    {

        $this->p_invoice->BuyerReference = $this->invoice->po_number ?? '';

        if (strlen($this->invoice->po_number ?? '') > 1) {
            $order_reference = new OrderReference();
            $id = new ID();
            $id->value = $this->invoice->po_number;

            $order_reference->ID = $id;

            $this->p_invoice->OrderReference = $order_reference;

           
        }

        return $this;

    }

    /**
     * getLegalMonetaryTotal
     *
     * @return LegalMonetaryTotal
     */
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
    
    /**
     * getTaxType
     *
     * @param  string $tax_id
     * @return string
     */
    private function getTaxType(string $tax_id = ''): string
    {
        $tax_type = null;
        
        switch ($tax_id) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $tax_type = 'S';
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  'E';
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = 'Z';
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = 'AE';
                break;
        }

        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "ES-CE", "ES-ML", "ES-CN", "SE", "IS", "LI", "NO", "CH"];
        
        if (empty($tax_type)) {
            if ((in_array($this->company->country()->iso_3166_2, $eu_states) && in_array($this->invoice->client->country->iso_3166_2, $eu_states)) && $this->invoice->company->country()->iso_3166_2 != $this->invoice->client->country->iso_3166_2) {
                $tax_type = 'K'; //EEA Exempt
            } elseif (!in_array($this->invoice->client->country->iso_3166_2, $eu_states)) {
                $tax_type = 'G'; //Free export item, VAT not charged
            } else {
                $tax_type = 'S'; //Standard rate
            }
        }

        if(in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML", "ES-CN"]) && $tax_type == 'S') {
            
            if ($this->invoice->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = 'L'; //Canary Islands general indirect tax
            } elseif (in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = 'M'; //Tax for production, services and importation in Ceuta and Melilla
            }

        }

        return $tax_type;
    }
   
    private function getInvoiceLines(): array
    {
        $lines = [];

        foreach($this->invoice->line_items as $key => $item) {

            $_item = new Item();
            $_item->Name = $item->product_key;
            $_item->Description = $item->notes;


            if($item->tax_rate1 > 0)
            {
            $ctc = new ClassifiedTaxCategory();
            $ctc->ID = new ID();
            $ctc->ID->value = $this->getTaxType($item->tax_id);
            $ctc->Percent = $item->tax_rate1;
            
            $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate2 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $this->getTaxType($item->tax_id);
                $ctc->Percent = $item->tax_rate2;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate3 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $this->getTaxType($item->tax_id);
                $ctc->Percent = $item->tax_rate3;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            $line = new InvoiceLine();
            
            $id = new ID();
            $id->value = (string) ($key+1);
            $line->ID = $id;
            $line->InvoicedQuantity = $item->quantity;

            $lea = new LineExtensionAmount();
            $lea->currencyID = $this->invoice->client->currency()->code;
            $lea->amount = $this->invoice->uses_inclusive_taxes ? $item->line_total - $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) : $item->line_total;
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            $item_taxes = $this->getItemTaxes($item);

            if(count($item_taxes) > 0) {
                $line->TaxTotal = $item_taxes;
            }

            $price = new Price();
            $pa = new PriceAmount();
            $pa->currencyID = $this->invoice->client->currency()->code;
            $pa->amount = (string) ($this->costWithDiscount($item) - ($this->invoice->uses_inclusive_taxes ? ($this->calcInclusiveLineTax($item->tax_rate1, $item->line_total) / $item->quantity) : 0));
            $price->PriceAmount = $pa;

            $line->Price = $price;

            $lines[] = $line;
        }

        return $lines;
    }
    
    
    /**
     * costWithDiscount
     *
     * @param  mixed $item
     * @return float
     */
    private function costWithDiscount($item): float
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
    
    /**
     * zeroTaxAmount
     *
     * @return array
     */
    // private function zeroTaxAmount(): array
    // {
    //     $blank_tax = [];

    //     $tax_amount = new TaxAmount();
    //     $tax_amount->currencyID = $this->invoice->client->currency()->code;
    //     $tax_amount->amount = '0';
    //     $tax_subtotal = new TaxSubtotal();
    //     $tax_subtotal->TaxAmount = $tax_amount;

    //     $taxable_amount = new TaxableAmount();
    //     $taxable_amount->currencyID = $this->invoice->client->currency()->code;
    //     $taxable_amount->amount = '0';
    //     $tax_subtotal->TaxableAmount = $taxable_amount;

    //     $tc = new TaxCategory();
    //     $id = new ID();
    //     $id->value = 'Z';
    //     $tc->ID = $id;
    //     $tc->Percent = '0';
    //     $ts = new TaxScheme();
        
    //     $id = new ID();
    //     $id->value = '0';
    //     $ts->ID = $id;
    //     $tc->TaxScheme = $ts;
    //     $tax_subtotal->TaxCategory = $tc;

    //     $tax_total = new TaxTotal();
    //     $tax_total->TaxAmount = $tax_amount;
    //     $tax_total->TaxSubtotal[] = $tax_subtotal;
    //     $blank_tax[] = $tax_total;


    //     return $blank_tax;
    // }
    
    /**
     * getItemTaxes
     *
     * @param  object $item
     * @return array
     */
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
            
            $id = new ID();
            $id->value = $this->getTaxType($item->tax_id);
            
            $tc->ID = $id;
            $tc->Percent = $item->tax_rate1;
            $ts = new TaxScheme();

            $id = new ID();
            $id->value = $item->tax_name1;

            $ts->ID = $id;
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
            
            $id = new ID();
            $id->value = $this->getTaxType($item->tax_id);

            $tc->ID = $id;
            $tc->Percent = $item->tax_rate2;
            $ts = new TaxScheme();

            $id = new ID();
            $id->value = $item->tax_name2;

            $ts->ID = $id;
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

            $id = new ID();
            $id->value = $this->getTaxType($item->tax_id);

            $tc->ID = $id;
            $tc->Percent = $item->tax_rate3;
            $ts = new TaxScheme();

            $id = new ID();
            $id->value = $item->tax_name3;

            $ts->ID = $id;
            $tc->TaxScheme = $ts;
            $tax_subtotal->TaxCategory = $tc;

            $tax_total = new TaxTotal();
            $tax_total->TaxAmount = $tax_amount;
            $tax_total->TaxSubtotal[] = $tax_subtotal;
            $item_taxes[] = $tax_total;


        }

        return $item_taxes;
    }
    
    /**
     * getAccountingSupplierParty
     *
     * @return AccountingSupplierParty
     */
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

        $ic = new IdentificationCode();
        $ic->value = substr($this->invoice->company->country()->iso_3166_2, 0, 2);
        $country->IdentificationCode = $ic;
        
        $address->Country = $country;

        $party->PostalAddress = $address;
        $party->PhysicalLocation = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Contact') ?? $this->invoice->company->owner()->present()->email();
        $contact->Telephone = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Telephone') ?? $this->invoice->company->getSetting('phone');
        $contact->Name = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Name') ?? $this->invoice->company->owner()->present()->name();

        $party->Contact = $contact;

        $asp->Party = $party;

        return $asp;
    }
    
    /**
     * resolveTaxScheme
     *
     * @return string
     */
    private function resolveTaxScheme(): string
    {
        return (new StorecoveRouter())->resolveTaxScheme($this->invoice->client->country->iso_3166_2, $this->invoice->client->classification);
    }
    
    /**
     * getAccountingCustomerParty
     *
     * @return AccountingCustomerParty
     */
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

        $ic = new IdentificationCode();
        $ic->value = substr($this->invoice->client->country->iso_3166_2, 0, 2);
        
        $country->IdentificationCode = $ic;
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
    
    /**
     * getTaxable
     *
     * @return float
     */
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
    

    /**
     * setInvoiceDefaults
     *
     * Stubs a default einvoice
     * @return self
     */
    public function setInvoiceDefaults(): self
    {

            // Stub new invoice with company settings.
            if($this->_company_settings)
            {
                foreach(get_object_vars($this->_company_settings) as $prop => $value){
                    $this->p_invoice->{$prop} = $value;
                }
            }

            // Overwrite with any client level settings
            if($this->_client_settings)
            {
                foreach (get_object_vars($this->_client_settings) as $prop => $value) {
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
            foreach($settings as $prop => $visibility) {

                if($prop_value = $this->gateway->mutator->getSetting($prop)) {
                    $this->p_invoice->{$prop} = $prop_value;
                }

            }

            return $this;
    }

}
