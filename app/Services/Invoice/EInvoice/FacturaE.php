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

use App\Models\Client;
use App\Models\Invoice;
use josemmo\Facturae\Facturae;
use App\Services\AbstractService;
use josemmo\Facturae\FacturaeItem;
use josemmo\Facturae\FacturaeParty;

class Facturae extends AbstractService
{

    // Facturae::SCHEMA_3_2	Invoice Format 3.2
    // Facturae::SCHEMA_3_2_1	Invoice Format 3.2.1
    // Facturae::SCHEMA_3_2_2	Invoice Format 3.2.2

// Facturae::ISSUER_SELLER	Provider (issuer)
// Facturae::ISSUER_BUYER	Recipient (receiver)
// Facturae::ISSUER_THIRD_PARTY	Third

// Facturae::PRECISION_LINE	Line level accuracy
// Facturae::PRECISION_INVOICE	Invoice level accuracy

// CorrectiveDetails::METHOD_FULL	Full rectification
// CorrectiveDetails::METHOD_DIFFERENCES	Rectification for differences
// CorrectiveDetails::METHOD_VOLUME_DISCOUNT	Rectification for discount by volume of operations during a period
// CorrectiveDetails::METHOD_AEAT_AUTHORIZED	Authorized by the Tax Agency


// FacturaePayment::TYPE_CASH	Cash
// FacturaePayment::TYPE_DEBIT	Domiciled receipt
// FacturaePayment::TYPE_RECEIPT	Receipt
// FacturaePayment::TYPE_TRANSFER	Transfer
// FacturaePayment::TYPE_ACCEPTED_BILL_OF_EXCHANGE	Letter Accepted
// FacturaePayment::TYPE_DOCUMENTARY_CREDIT	Letter of credit
// FacturaePayment::TYPE_CONTRACT_AWARD	contract award
// FacturaePayment::TYPE_BILL_OF_EXCHANGE	Bill of exchange
// FacturaePayment::TYPE_TRANSFERABLE_IOU	I will pay to order
// FacturaePayment::TYPE_IOU	I Will Pay Not To Order
// FacturaePayment::TYPE_CHEQUE	Check
// FacturaePayment::TYPE_REIMBURSEMENT	Replacement
// FacturaePayment::TYPE_SPECIAL	specials
// FacturaePayment::TYPE_SETOFF	Compensation
// FacturaePayment::TYPE_POSTGIRO	Money order
// FacturaePayment::TYPE_CERTIFIED_CHEQUE	conformed check
// FacturaePayment::TYPE_BANKERS_DRAFT	Bank check
// FacturaePayment::TYPE_CASH_ON_DELIVERY	Cash on delivery
// FacturaePayment::TYPE_CARD	Payment by card

// Facturae::TAX_IVA	Value Added Tax
// Facturae::TAX_IPSI	Tax on production, services and imports
// Facturae::TAX_IGIC	General indirect tax of the Canary Islands
// Facturae::TAX_IRPF	Personal Income Tax
// Facturae::TAX_OTHER	Other
// Facturae::TAX_ITPAJD	Transfer tax and stamp duty
// Facturae::TAX_IE	Special taxes
// Facturae::TAX_RA	Customs rent
// Facturae::TAX_IGTECM	General tax on business traffic that is applied in Ceuta and Melilla
// Facturae::TAX_IECDPCAC	Special tax on fuels derived from petroleum in the Autonomous Community of the Canary Islands
// Facturae::TAX_IIIMAB	Tax on facilities that affect the environment in the Balearic Islands
// Facturae::TAX_ICIO	Tax on constructions, facilities and works
// Facturae::TAX_IMVDN	Municipal tax on unoccupied homes in Navarra
// Facturae::TAX_IMSN	Municipal tax on plots in Navarra
// Facturae::TAX_IMGSN	Municipal tax on luxury expenses in Navarra
// Facturae::TAX_IMPN	Municipal tax on advertising in Navarra
// Facturae::TAX_REIVA	Special VAT regime for travel agencies
// Facturae::TAX_REIGIC	Special IGIC regime: for travel agencies
// Facturae::TAX_REIPSI	Special IPSI regime for travel agencies
// Facturae::TAX_IPS	Taxes on insurance premiums
// Facturae::TAX_RLEA	Surcharge intended to finance the liquidation functions of insurance entities
// Facturae::TAX_IVPEE	Tax on the value of electricity production
// Facturae::TAX_IPCNG	Tax on the production of spent nuclear fuel and radioactive waste resulting from the generation of nuclear power
// Facturae::TAX_IACNG	Tax on the storage of spent nuclear fuel and radioactive waste in centralized facilities
// Facturae::TAX_IDEC	Tax on Deposits in Credit Institutions
// Facturae::TAX_ILTCAC	Tax on tobacco products in the Autonomous Community of the Canary Islands
// Facturae::TAX_IGFEI	Tax on Fluorinated Greenhouse Gases
// Facturae::TAX_IRNR	Non-Resident Income Tax
// Facturae::TAX_ISS	Corporate tax

// FacturaeItem::SPECIAL_TAXABLE_EVENT_EXEMPT	Subject and exempt operation
// FacturaeItem::SPECIAL_TAXABLE_EVENT_NON_SUBJECT	Operation not subject

// FacturaeCentre::ROLE_CONTABLE
// eitherFacturaeCentre::ROLE_FISCAL	Accounting Office
// FacturaeCentre::ROLE_GESTOR
// eitherFacturaeCentre::ROLE_RECEPTOR	managing body
// FacturaeCentre::ROLE_TRAMITADOR
// eitherFacturaeCentre::ROLE_PAGADOR	processing unit
// FacturaeCentre::ROLE_PROPONENTE	proposing body
// FacturaeCentre::ROLE_B2B_FISCAL	Fiscal receiver in FACeB2B
// FacturaeCentre::ROLE_B2B_PAYER	Payer in FACeB2B
// FacturaeCentre::ROLE_B2B_BUYER	Buyer at FACeB2B
// FacturaeCentre::ROLE_B2B_COLLECTOR	Collector at FACeB2B
// FacturaeCentre::ROLE_B2B_SELLER	Seller at FACeB2B
// FacturaeCentre::ROLE_B2B_PAYMENT_RECEIVER	Payment recipient in FACeB2B
// FacturaeCentre::ROLE_B2B_COLLECTION_RECEIVER	Collection receiver in FACeB2B
// FacturaeCentre::ROLE_B2B_ISSUER	Issuer in FACeB2B


    public function __construct(public Invoice $invoice)
    {
    }

    public function run()
    {

        $fac = new Facturae();
        $fac->setNumber('', $this->invoice->number);
        $fac->setIssueDate($this->invoice->date);
        $fac->setBuyer($this->buildBuyer());
        $fac->setSeller($this->buildSeller());
        $fac = $this->buildItems($fac);
        
    }

    private function buildItems(Facturae $fac): Facturae
    {

        foreach($this->invoice->line_items as $item)
        {
            $fac->addItem(new FacturaeItem([
                'description' => $item->notes,
                'quantity' => $item->quantity,
                'unitPrice' => $item->cost,
                'discountsAndRebates' => $item->discount,
                'charges' => 0,
                'taxes' => $this->buildRatesForItem($item),
                'specialTaxableEvent' => FacturaeItem::SPECIAL_TAXABLE_EVENT_NON_SUBJECT,
                'specialTaxableEventReason' => '',
                'specialTaxableEventReasonDescription' => '',
            ]));
            
        }
    
        return $fac;
    
    }

    private function buildRatesForItem(\stdClass $item): array
    {
        $data = [];

        if (strlen($item->tax_name1) > 1) {
        
            $data[] = [$this->resolveTaxCode($item->tax_name1) => $item->tax_rate1];
        
        }

        if (strlen($item->tax_name2) > 1) {
                
            $data[] = [$this->resolveTaxCode($item->tax_name2) => $item->tax_rate2];
                
        }

        if (strlen($item->tax_name3) > 1) {
                
            $data[] = [$this->resolveTaxCode($item->tax_name3) => $item->tax_rate3];
                
        }

        return $data;
    }

    private function resolveTaxCode(string $tax_name)
    {
        return match (strtoupper($tax_name)) {
            'IVA' => Facturae::TAX_IVA,
            'IPSI' => Facturae::TAX_IPSI,
            'IGIC' => Facturae::TAX_IGIC,
            'IRPF' => Facturae::TAX_IRPF,
            'IRNR' => Facturae::TAX_IRNR,
            'ISS' => Facturae::TAX_ISS,
            'REIVA' => Facturae::TAX_REIVA,
            'REIGIC' => Facturae::TAX_REIGIC,
            'REIPSI' => Facturae::TAX_REIPSI,
            'IPS' => Facturae::TAX_IPS,
            'RLEA' => Facturae::TAX_RLEA,
            'IVPEE' => Facturae::TAX_IVPEE,
            'IPCNG' => Facturae::TAX_IPCNG,
            'IACNG' => Facturae::TAX_IACNG,
            'IDEC' => Facturae::TAX_IDEC,
            'ILTCAC' => Facturae::TAX_ILTCAC,
            'IGFEI' => Facturae::TAX_IGFEI,
            'ISS' => Facturae::TAX_ISS,
            'IMGSN' => Facturae::TAX_IMGSN,
            'IMSN' => Facturae::TAX_IMSN,
            'IMPN' => Facturae::TAX_IMPN,
            'IIIMAB' => Facturae::TAX_IIIMAB,
            'ICIO' => Facturae::TAX_ICIO,
            'IECDPCAC' => Facturae::TAX_IECDPCAC,
            'IGTECM' => Facturae::TAX_IGTECM,
            'IE' => Facturae::TAX_IE,
            'RA' => Facturae::TAX_RA,
            'ITPAJD' => Facturae::TAX_ITPAJD,
            'OTHER' => Facturae::TAX_OTHER,
            'IMVDN' => Facturae::TAX_IMVDN,
            default => Facturae::TAX_IVA,

        };
    }

    private function buildSeller(): FacturaeParty
    {
        $company = $this->invoice->company;

        $seller = new FacturaeParty([
        "isLegalEntity" => true, // Se asume true si se omite
        "taxNumber"     => $company->settings->vat_number,
        "name"          => $company->present()->name(),
        "address"       => $company->settings->address1,
        "postCode"      => $company->settings->postal_code,
        "town"          => $company->settings->city,
        "province"      => $company->settings->state,
        "countryCode"   => $company->country()->iso_3166_3,  // Se asume España si se omite
        "book"             => "0",  // Libro
        "merchantRegister" => "RG", // Registro Mercantil
        "sheet"            => "1",  // Hoja
        "folio"            => "2",  // Folio
        "section"          => "3",  // Sección
        "volume"           => "4",  // Tomo
        "email"   => $company->settings->email,
        "phone"   => $company->settings->phone,
        "fax"     => "",
        "website" => $company->settings->website,
        "contactPeople" => $company->owner()->present()->name(),
        // "cnoCnae" => "04647", // Clasif. Nacional de Act. Económicas
        // "ineTownCode" => "280796" // Cód. de municipio del INE
        ]);

        return $seller;
    }

    private function buildBuyer(): FacturaeParty
    {

        $buyer = new FacturaeParty([
        "isLegalEntity" => $this->invoice->client->has_valid_vat_number,
        "taxNumber"     => $this->invoice->client->vat_number,
        "name"          => $this->invoice->client->present()->name(),
        "firstSurname"  => $this->invoice->client->present()->first_name(),
        "lastSurname"   => $this->invoice->client->present()->last_name(),
        "address"       => $this->invoice->client->address1,
        "postCode"      => $this->invoice->client->postal_code,
        "town"          => $this->invoice->client->city,
        "province"      => $this->invoice->client->state,
        "countryCode"   => $this->invoice->client->country->iso_3166_3,  // Se asume España si se omite
        "email"   => $this->invoice->client->present()->email(),
        "phone"   => $this->invoice->client->present()->phone(),
        "fax"     => "",
        "website" => $this->invoice->client->present()->website(),
        "contactPeople" => $this->invoice->client->present()->first_name()." ".$this->invoice->client->present()->last_name(),
        // "cnoCnae" => "04791", // Clasif. Nacional de Act. Económicas
        // "ineTownCode" => "280796" // Cód. de municipio del INE
        ]);

        return $buyer;
    }

}