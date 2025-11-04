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

use App\Models\Invoice;
use App\Services\AbstractService;
use App\Services\EDocument\Standards\FatturaPA\Enums\ModalitaPagamento;
use InvoiceNinja\EInvoice\EInvoice;
use InvoiceNinja\EInvoice\Models\FatturaPA\AltriDatiGestionaliType\AltriDatiGestionali;
use InvoiceNinja\EInvoice\Models\FatturaPA\ContattiType\Contatti;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiBolloType\DatiBollo;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronica;
use InvoiceNinja\EInvoice\Models\FatturaPA\IndirizzoType\Sede;
use InvoiceNinja\EInvoice\Models\FatturaPA\AnagraficaType\Anagrafica;
use InvoiceNinja\EInvoice\Models\FatturaPA\IdFiscaleType\IdFiscaleIVA;
use InvoiceNinja\EInvoice\Models\FatturaPA\IdFiscaleType\IdTrasmittente;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiGeneraliType\DatiGenerali;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiPagamentoType\DatiPagamento;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiRiepilogoType\DatiRiepilogo;
use InvoiceNinja\EInvoice\Models\FatturaPA\DettaglioLineeType\DettaglioLinee;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiBeniServiziType\DatiBeniServizi;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiTrasmissioneType\DatiTrasmissione;
use InvoiceNinja\EInvoice\Models\FatturaPA\CedentePrestatoreType\CedentePrestatore;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiAnagraficiCedenteType\DatiAnagrafici;
use InvoiceNinja\EInvoice\Models\FatturaPA\DettaglioPagamentoType\DettaglioPagamento;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiGeneraliDocumentoType\DatiGeneraliDocumento;
use InvoiceNinja\EInvoice\Models\FatturaPA\CessionarioCommittenteType\CessionarioCommittente;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronicaBodyType\FatturaElettronicaBody;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronicaHeaderType\FatturaElettronicaHeader;

class FatturaPANew extends AbstractService
{
    const IMPORTO_BOLLO = 2.00;

    private FatturaElettronica $FatturaElettronica;
    private FatturaElettronicaBody $FatturaElettronicaBody;
    private FatturaElettronicaHeader $FatturaElettronicaHeader;
    private DatiTrasmissione $DatiTrasmissione;
    private IdTrasmittente $IdTrasmittente;
    private CedentePrestatore $CedentePrestatore;
    private DatiAnagrafici $DatiAnagrafici;
    private IdFiscaleIVA $IdFiscaleIVA;
    private Anagrafica $Anagrafica;
    private DatiGeneraliDocumento $DatiGeneraliDocumento;
    private DatiGenerali $DatiGenerali;
    private DettaglioPagamento $DettaglioPagamento;
    /**
     * @param Invoice $invoice
     */
    public function __construct(public Invoice $invoice)
    {
    }

    public function run()
    {
        $this->init()
             ->setIdTrasmittente() //order of execution matters.
             ->setDatiTrasmissione()
             ->setIdFiscaleIVA()
             ->setAnagrafica()
             ->setDatiAnagrafici()
             ->setCedentePrestatore()
             ->setClientDetails()
             ->setDatiGeneraliDocumento()
             ->setDatiGenerali()
             ->setLineItems()
             ->setDettaglioPagamento()
             ->setFatturaElettronica();

        return $this;
    }

    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->getFatturaElettronica(), 'xml');

        $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<p:FatturaElettronica xmlns:ds="http://www.w3.org/2000/09/xmldsig#" 
xmlns:p="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2" 
xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" versione="FPR12" 
xsi:schemaLocation="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2 
http://www.fatturapa.gov.it/export/fatturazione/sdi/fatturapa/v1.2/Schema_del_file_xml_FatturaPA_versione_1.2.xsd">';

        $suffix = '</p:FatturaElettronica>';

        $xml = str_ireplace(['\n', '<?xml version="1.0"?>'], ['', $prefix], $xml);
        $xml .= $suffix;
        return $xml;

    }

    public function getFatturaElettronica(): FatturaElettronica
    {
        return $this->FatturaElettronica;
    }

    private function setDatiTrasmissione(): self
    {

        $this->DatiTrasmissione->FormatoTrasmissione = "FPR12";
        $this->DatiTrasmissione->CodiceDestinatario = $this->invoice->client->routing_id;
        $this->DatiTrasmissione->ProgressivoInvio = $this->invoice->number;

        $this->DatiTrasmissione->IdTrasmittente = $this->IdTrasmittente;

        $contatti = new Contatti();
        $contatti->Email = $this->invoice->company->settings->email;
        $this->DatiTrasmissione->ContattiTrasmittente = $contatti;

        $this->FatturaElettronicaHeader->DatiTrasmissione = $this->DatiTrasmissione;

        return $this;
    }


    private function setIdTrasmittente(): self
    {
        $this->IdTrasmittente->IdPaese = $this->invoice->company->country()->iso_3166_2;
        $this->IdTrasmittente->IdCodice = ltrim($this->invoice->company->settings->vat_number, 'IT');

        return $this;
    }

    private function setCedentePrestatore(): self
    {
        $this->CedentePrestatore->DatiAnagrafici = $this->DatiAnagrafici;

        $sede = new Sede();
        $sede->Indirizzo = $this->invoice->company->settings->address1;
        $sede->CAP = (int)$this->invoice->company->settings->postal_code;
        $sede->Comune = $this->invoice->company->settings->city;
        $sede->Provincia = $this->invoice->company->settings->state;
        $sede->Nazione = $this->invoice->company->country()->iso_3166_2;
        $this->CedentePrestatore->Sede = $sede;

        $contatti = new Contatti();
        $contatti->Email = $this->invoice->company->settings->email;
        $this->CedentePrestatore->Contatti = $contatti;

        $this->FatturaElettronicaHeader->CedentePrestatore = $this->CedentePrestatore;

        return $this;
    }

    private function setDatiAnagrafici(): self
    {
        $this->DatiAnagrafici->RegimeFiscale = "RF01";
        $this->DatiAnagrafici->Anagrafica = $this->Anagrafica;
        $this->DatiAnagrafici->IdFiscaleIVA = $this->IdFiscaleIVA;
        $this->DatiAnagrafici->CodiceFiscale = $this->invoice->company->settings->id_number;

        return $this;
    }

    private function setClientDetails(): self
    {

        $datiAnagrafici = new DatiAnagrafici();
        $anagrafica = new Anagrafica();
        $anagrafica->Denominazione =  $this->invoice->client->present()->name();
        $datiAnagrafici->Anagrafica = $anagrafica;

        $idFiscale = new IdFiscaleIVA();
        $idFiscale->IdCodice = ltrim($this->invoice->client->vat_number, 'IT');
        $idFiscale->IdPaese = $this->invoice->client->country->iso_3166_2;

        $datiAnagrafici->IdFiscaleIVA = $idFiscale;

        $sede = new Sede();
        $sede->Indirizzo =  $this->invoice->client->address1;
        $sede->CAP =  (int)$this->invoice->client->postal_code;
        $sede->Comune =  $this->invoice->client->city;
        $sede->Provincia =  $this->invoice->client->state;
        $sede->Nazione = $this->invoice->client->country->iso_3166_2;

        $cessionarioCommittente = new CessionarioCommittente();
        $cessionarioCommittente->DatiAnagrafici = $datiAnagrafici;
        $cessionarioCommittente->Sede = $sede;

        $this->FatturaElettronicaHeader->CessionarioCommittente = $cessionarioCommittente;

        return $this;
    }

    private function clientNeedsInvCont()
    {
        return $this->invoice->client->country->iso_3166_2 != 'IT';
    }

    private function setIdFiscaleIVA(): self
    {

        $this->IdFiscaleIVA->IdPaese = $this->invoice->company->country()->iso_3166_2;
        $this->IdFiscaleIVA->IdCodice = ltrim($this->invoice->company->settings->vat_number, 'IT');

        return $this;
    }

    //this is a choice, need to switch based on values here.
    private function setAnagrafica(): self
    {
        $this->Anagrafica->Denominazione = $this->invoice->company->present()->name();

        return $this;
    }

    private function setDatiGeneraliDocumento(): self
    {
        $total = $this->invoice->total;

        if ($this->clientNeedsInvCont()) {
            $total += self::IMPORTO_BOLLO;
            $datiBollo = new DatiBollo();
            $datiBollo->BolloVirtuale = "SI";
            $datiBollo->ImportoBollo = sprintf('%0.2f', self::IMPORTO_BOLLO);
            $this->DatiGeneraliDocumento->DatiBollo = $datiBollo;
        }

        $this->DatiGeneraliDocumento->TipoDocumento = "TD01";
        $this->DatiGeneraliDocumento->Divisa = $this->invoice->client->currency()->code;
        $this->DatiGeneraliDocumento->Data = new \DateTime($this->invoice->date);
        $this->DatiGeneraliDocumento->Numero = $this->invoice->number;
        $this->DatiGeneraliDocumento->Causale[] = substr($this->invoice->terms ?? '', 0, 200); //unsure..
        $this->DatiGeneraliDocumento->ImportoTotaleDocumento = sprintf('%0.2f', $total);

        return $this;
    }

    private function setDatiGenerali(): self
    {
        $this->DatiGenerali->DatiGeneraliDocumento = $this->DatiGeneraliDocumento;

        $this->FatturaElettronicaBody->DatiGenerali = $this->DatiGenerali;

        return $this;
    }

    private function setDettaglioPagamento(): self
    {
        $total = $this->invoice->balance + ($this->clientNeedsInvCont() ? self::IMPORTO_BOLLO : 0);
        $paymentTypeId = (int)$this->invoice->company->settings->payment_type_id;
        $modalitaPagamento = ModalitaPagamento::getByPaymentType($paymentTypeId) ?? ModalitaPagamento::MP01_CASH;

        $this->DettaglioPagamento->ModalitaPagamento =  $modalitaPagamento->value;
        $this->DettaglioPagamento->DataScadenzaPagamento =  new \DateTime($this->invoice->due_date ?? $this->invoice->date);
        $this->DettaglioPagamento->ImportoPagamento =  (string) sprintf('%0.2f', $total);

        $this->setPaymentMeans();

        $DatiPagamento = new DatiPagamento();
        $DatiPagamento->CondizioniPagamento = "TP02";
        $DatiPagamento->DettaglioPagamento[] = $this->DettaglioPagamento;

        $this->FatturaElettronicaBody->DatiPagamento[] = $DatiPagamento;

        return $this;
    }

    private function setPaymentMeans(): self
    {
        $paymentMean = false;

        /**Check if the e_invoice object is populated */
        if (isset($this->invoice->company->e_invoice->Invoice->PaymentMeans)) {
            $paymentMean = $this->invoice->company->e_invoice->Invoice->PaymentMeans[0] ?? false;
        }

        switch ($paymentMean?->PaymentMeansCode?->value) {
            case '31':
                $this->DettaglioPagamento->IBAN = $paymentMean->PayeeFinancialAccount->ID->value;
                $this->DettaglioPagamento->BIC = $paymentMean->PayeeFinancialAccount->FinancialInstitutionBranch->FinancialInstitution->ID->value ?? '';
                break;

            default:
                # code...
                break;
        }
        return $this;
    }

    private function setLineItems(): self
    {

        $calc = $this->invoice->calc();
        $isInvCont = $this->clientNeedsInvCont();
        $lineItems = $this->invoice->line_items;

        if ($isInvCont) {
            $lineItems[] = (object)[
                'notes' => 'Imposta di bollo assolta in modo virtuale (art. 13 Tariffa DPR 642/72)',
                'quantity' => 1,
                'cost' => self::IMPORTO_BOLLO,
                'line_total' => self::IMPORTO_BOLLO,
                'tax_rate1' => 0,
                'natura' => 'N1',
            ];
        }

        $datiBeniServizi = new DatiBeniServizi();
        $tax_rate_level = sprintf('%0.2f', 0);

        //line items
        foreach ($lineItems as $key => $item) {

            $numero = $key + 1;
            $dettaglioLinee = new DettaglioLinee();
            $dettaglioLinee->NumeroLinea =  "{$numero}";
            $dettaglioLinee->Descrizione =  $item->notes ?? 'Descrizione';
            $dettaglioLinee->Quantita =  sprintf('%0.2f', $item->quantity);
            $dettaglioLinee->PrezzoUnitario =  sprintf('%0.2f', $item->cost);
            $dettaglioLinee->PrezzoTotale =  sprintf('%0.2f', $item->line_total);
            $dettaglioLinee->AliquotaIVA =  sprintf('%0.2f', $item->tax_rate1);

            if ($isInvCont) {
                $dettaglioLinee->Natura = $item->natura ?? "N2.1"; // Non soggette ad IVA ai sensi degli art. da 7 a 7-septies del DPR 633/72

                $altriDatiGestionali = new AltriDatiGestionali();
                // Operazioni Comunitarie
                // Per informare il cliente comunitario che dovrà provvedere all'Inversione contabile,
                // secondo l’articolo 21 comma 6 bis lettera a) del d.P.R. n. 633/72.
                $altriDatiGestionali->TipoDato = "INVCONT";

                $dettaglioLinee->AltriDatiGestionali[] = $altriDatiGestionali;
            }

            $datiBeniServizi->DettaglioLinee[] = $dettaglioLinee;

            if ($item->tax_rate1 > $tax_rate_level) {
                $tax_rate_level = sprintf('%0.2f', $item->tax_rate1);
            }

        }

        //totals
        if ($this->invoice->tax_rate1 > $tax_rate_level) {
            $tax_rate_level = sprintf('%0.2f', $this->invoice->tax_rate1);
        }

        $subtotal = sprintf('%0.2f', $calc->getSubTotal());
        $taxes = sprintf('%0.2f', $calc->getTotalTaxes());

        $datiRiepilogo = new DatiRiepilogo();
        $datiRiepilogo->AliquotaIVA = "{$tax_rate_level}";
        $datiRiepilogo->ImponibileImporto = "{$subtotal}";
        $datiRiepilogo->Imposta = "{$taxes}";

        if ($isInvCont) {
            $datiRiepilogo->Natura = "N2.1"; // Non soggette ad IVA ai sensi degli art. da 7 a 7-septies del DPR 633/72
            $datiRiepilogo->RiferimentoNormativo = '"inversione contabile" art. 7-ter DPR 633/72';
        } else {
            $datiRiepilogo->EsigibilitaIVA = "I";
        }

        $datiBeniServizi->DatiRiepilogo[] = $datiRiepilogo;

        if ($isInvCont) {
            $datiRiepilogo = new DatiRiepilogo();
            $datiRiepilogo->AliquotaIVA = sprintf('%0.2f', 0);
            $datiRiepilogo->Imposta = sprintf('%0.2f', 0);
            $datiRiepilogo->ImponibileImporto = sprintf('%0.2f', self::IMPORTO_BOLLO);
            $datiRiepilogo->Natura = "N1"; // Non soggette ad IVA ai sensi degli art. da 7 a 7-septies del DPR 633/72
            $datiRiepilogo->RiferimentoNormativo = 'Operazione esclusa ex art. 15 DPR 633/72 - Imposta di bollo assolta in modo virtuale';

            $datiBeniServizi->DatiRiepilogo[] = $datiRiepilogo;
        }

        $this->FatturaElettronicaBody->DatiBeniServizi = $datiBeniServizi;

        return $this;
    }

    private function setFatturaElettronica(): self
    {

        $this->FatturaElettronica->FatturaElettronicaBody[] = $this->FatturaElettronicaBody;
        $this->FatturaElettronica->FatturaElettronicaHeader = $this->FatturaElettronicaHeader;

        return $this;
    }

    private function init(): self
    {

        $this->FatturaElettronica = new FatturaElettronica();
        $this->FatturaElettronicaBody = new FatturaElettronicaBody();
        $this->FatturaElettronicaHeader = new FatturaElettronicaHeader();
        $this->DatiTrasmissione = new DatiTrasmissione();
        $this->IdTrasmittente = new IdTrasmittente();
        $this->CedentePrestatore = new CedentePrestatore();
        $this->DatiAnagrafici = new DatiAnagrafici();
        $this->IdFiscaleIVA = new IdFiscaleIVA();
        $this->Anagrafica = new Anagrafica();
        $this->DatiGeneraliDocumento = new DatiGeneraliDocumento();
        $this->DatiGenerali = new DatiGenerali();
        $this->DettaglioPagamento = new DettaglioPagamento();

        return $this;

    }
}
