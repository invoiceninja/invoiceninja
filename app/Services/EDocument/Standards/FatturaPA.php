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

namespace App\Services\EDocument\Standards;

use Sabre\Xml\Service;
use App\Models\Invoice;
use App\Services\AbstractService;
use CleverIt\UBL\Invoice\FatturaPA\common\Sede;
use CleverIt\UBL\Invoice\FatturaPA\common\Anagrafica;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiGenerali;
use CleverIt\UBL\Invoice\FatturaPA\common\IdFiscaleIVA;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiContratto;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiPagamento;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiRicezione;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiRiepilogo;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiTrasporto;
use CleverIt\UBL\Invoice\FatturaPA\common\RegimeFiscale;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiAnagrafici;
use CleverIt\UBL\Invoice\FatturaPA\common\DettaglioLinee;
use CleverIt\UBL\Invoice\FatturaPA\common\IdTrasmittente;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiBeniServizi;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiTrasmissione;
use CleverIt\UBL\Invoice\FatturaPA\common\CedentePrestatore;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiOrdineAcquisto;
use CleverIt\UBL\Invoice\FatturaPA\common\DettaglioPagamento;
use CleverIt\UBL\Invoice\FatturaPA\common\FatturaElettronica;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiAnagraficiVettore;
use CleverIt\UBL\Invoice\FatturaPA\common\DatiGeneraliDocumento;
use CleverIt\UBL\Invoice\FatturaPA\common\CessionarioCommittente;
use CleverIt\UBL\Invoice\FatturaPA\common\FatturaElettronicaBody;
use CleverIt\UBL\Invoice\FatturaPA\common\FatturaElettronicaHeader;

class FatturaPA extends AbstractService
{
    private $xml;

    //urn:cen.eu:en16931:2017#compliant#urn:fatturapa.gov.it:CIUS-IT:2.0.0
    //<cbc:EndpointID schemeID=" 0201 ">UFF001</cbc:EndpointID>

    /**
    * 	   File Types
    * 
    * 	   EI01 => FILE VUOTO
    *      EI02 => SERVIZIO NON DISPONIBILE
    *      EI03 => UTENTE NON ABILITATO
    */
    
    /** Regime Fiscale
    *  
    *   //@todo - custom ui settings
    *
    *  private array $types = [
    *      "RF01" => "Regime ordinario",
    *      "RF02" => "Regime dei contribuenti minimi (art. 1,c.96-117, L. 244/2007)",
    *      "RF04" => "Agricoltura e attività connesse e pesca (artt. 34 e 34-bis, D.P.R. 633/1972)",
    *      "RF05" => "Vendita sali e tabacchi (art. 74, c.1, D.P.R. 633/1972)",
    *      "RF06" => "Commercio dei fiammiferi (art. 74, c.1, D.P.R. 633/1972)",
    *      "RF07" => "Editoria (art. 74, c.1, D.P.R. 633/1972)",
    *      "RF08" => "Gestione di servizi di telefonia pubblica (art. 74, c.1, D.P.R. 633/1972)" ,
    *      "RF09" => "Rivendita di documenti di trasporto pubblico e di sosta (art. 74, c.1, D.P.R. 633/1972)" ,
    *      "RF10" => "Intrattenimenti, giochi e altre attività di cui alla tariffa allegata al D.P.R. 640/72 (art. 74, c.6, D.P.R. 633/1972)" ,
    *      "RF11" => "Agenzie di viaggi e turismo (art. 74-ter, D.P.R. 633/1972)" ,
    *      "RF12" => "Agriturismo (art. 5, c.2, L. 413/1991)" ,
    *      "RF13" => "Vendite a domicilio (art. 25-bis, c.6, D.P.R. 600/1973)" ,
    *      "RF14" => "Rivendita di beni usati, di oggetti d’arte, d’antiquariato o da collezione (art. 36, D.L. 41/1995)" ,
    *      "RF15" => "Agenzie di vendite all’asta di oggetti d’arte, antiquariato o da collezione (art. 40-bis, D.L. 41/1995)" ,
    *      "RF16" => "IVA per cassa P.A. (art. 6, c.5, D.P.R. 633/1972)" ,
    *      "RF17" => "IVA per cassa (art. 32-bis, D.L. 83/2012)" ,
    *      "RF19" => "Regime forfettario" ,
    *      "RF18" => "Altro"
    *  ];
    */

    /** Formato Trasmissione
     *     FPA12: This is the format used for FatturaPA version 1.2.
     *     FPR12: This format is used for FatturaPA version 1.2 in cases where the invoice is destined for the Public Administration.
     *     FPA1.2: This format is used for FatturaPA version 1.2.
     *     FPR1.2: This format is used for FatturaPA version 1.2 in cases where the invoice is destined for the Public Administration.
     *     FPR10: This format is used for FatturaPA version 1.0 in cases where the invoice is destined for the Public Administration.
     *     FPA10: This format is used for FatturaPA version 1.0.
     */

    /** Tipo Documento
    * 
    *  TD01 Fattura
    *  TD02 Acconto/Anticipo su fattura
    *  TD03 Acconto/Anticipo su parcella
    *  TD04 Nota di Credito
    *  TD05 Nota di Debito
    *  TD06 Parcella
    *  TD16 Integrazione fattura reverse charge interno
    *  TD17 Integrazione/autofattura per acquisto servizi dall’estero
    *  TD18 Integrazione per acquisto di beni intracomunitari
    *  TD19 Integrazione/autofattura per acquisto di beni ex art.17 c.2 DPR 633/72
    *  TD20 Autofattura per regolarizzazione e integrazione delle
    *  fatture (ex art.6 c.8 e 9-bis d.lgs. 471/97 o art.46 c.5 D.L. 331/93)
    *  TD21 Autofattura per splafonamento
    *  TD22 Estrazione beni da Deposito IVA
    *  TD23 Estrazione beni da Deposito IVA con versamento dell’IVA
    *  TD24 Fattura differita di cui all’art.21, comma 4, lett. a)
    *  TD25 Fattura differita di cui all’art.21, comma 4, terzo periodo lett. b)
    *  TD26 Cessione di beni ammortizzabili e per passaggi interni (ex art.36 DPR 633/72)
    *  TD27 Fattura per autoconsumo o per cessioni gratuite senza rivalsa
    */

    /** Esigibilità IVA
     * "I" (Immediata): VAT is due and payable immediately upon issuance of the invoice.
     * "D" (Differita): VAT is due at a later date, typically when payment for the goods or services is received.
     * "S" (Soggetta): VAT is due under the reverse charge mechanism, where the recipient of the goods or services is responsible for accounting for the VAT.
     */
    
    /**
    * MP01 contanti //cash
    * MP02 assegno //check
    * MP03 assegno circolare //cashier's check
    * MP04 contanti presso Tesoreria //cash at treasury
    * MP05 bonifico //bank transfer
    * MP06 vaglia cambiario //bill of exchange
    * MP07 bollettino bancario //bank bulletin
    * MP08 carta di pagamento //payment card
    * MP09 RID //RID
    * MP10 RID utenze //RID utilities
    * MP11 RID veloce //fast RID
    * MP12 Riba //Riba
    * MP13 MAV //MAV
    * MP14 quietanza erario stato //state treasury receipt
    * MP15 giroconto su conti di contabilità speciale //transfer to special accounting accounts
    * MP16 domiciliazione bancaria //bank domiciliation
    * MP17 domiciliazione postale //postal domiciliation
    * MP18 bollettino di c/c postale //postal giro account
    * MP19 SEPA Direct Debit //SEPA Direct Debit
    * MP20 SEPA Direct Debit CORE //SEPA Direct Debit CORE
    * MP21 SEPA Direct Debit B2B //SEPA Direct Debit B2B
    * MP22 Trattenuta su somme già riscosse //Withholding on sums already collected
    * MP23 PagoPA //PagoPA
     */

     /**
      * TP01 pagamento a rate //payment in installments
      * TP02 pagamento completo //full payment
      * TP03 anticipo //advance
      */    

    /**
     * @param Invoice $invoice
     */
    public function __construct(public Invoice $invoice)
    {
    }

    public function run()
    {

    $fatturaHeader = new FatturaElettronicaHeader();

    $datiTrasmissione = new DatiTrasmissione();
    $datiTrasmissione->setFormatoTrasmissione("FPR12");
    $datiTrasmissione->setCodiceDestinatario($this->invoice->client->routing_id);
    $datiTrasmissione->setProgressivoInvio($this->invoice->number);

    $idPaese = new IdTrasmittente();
    $idPaese->setIdPaese($this->invoice->company->country()->iso_3166_2);
    $idPaese->setIdCodice($this->invoice->company->settings->vat_number);

    $datiTrasmissione->setIdTrasmittente($idPaese);
    $fatturaHeader->setDatiTrasmissione($datiTrasmissione);

    $cedentePrestatore = new CedentePrestatore();
    $datiAnagrafici = new DatiAnagrafici();
    $idFiscaleIVA = new IdFiscaleIVA(IdPaese: $this->invoice->company->country()->iso_3166_2, IdCodice: $this->invoice->company->settings->vat_number);
    $datiAnagrafici->setIdFiscaleIVA($idFiscaleIVA);

    $anagrafica = new Anagrafica(Denominazione: $this->invoice->company->present()->name());
    $datiAnagrafici->setAnagrafica($anagrafica);
    $datiAnagrafici->setRegimeFiscale("RF01");  //swap this out with the custom settings.
    $cedentePrestatore->setDatiAnagrafici($datiAnagrafici);

    $sede = new Sede(Indirizzo: $this->invoice->company->settings->address1, 
                    CAP: (int)$this->invoice->company->settings->postal_code, 
                    Comune: $this->invoice->company->settings->city, 
                    Provincia: $this->invoice->company->settings->state);

    $cedentePrestatore->setSede($sede);
    $fatturaHeader->setCedentePrestatore($cedentePrestatore);

    //client details
    $datiAnagrafici = new DatiAnagrafici();

//for some reason the validation does not like codice fiscale for the client?
//perhaps it may need IdFiscaleIVA?
// $datiAnagrafici->setCodiceFiscale("09876543210");

    $anagrafica = new Anagrafica(Denominazione: $this->invoice->client->present()->name());
    $datiAnagrafici->setAnagrafica($anagrafica);

    $sede = new Sede(Indirizzo: $this->invoice->client->address1, 
                    CAP: $this->invoice->client->postal_code, 
                    Comune: $this->invoice->client->city, 
                    Provincia: $this->invoice->client->state);

    $cessionarioCommittente = new CessionarioCommittente($datiAnagrafici, $sede);

    $fatturaHeader->setCessionarioCommittente($cessionarioCommittente);

////////////////// Fattura Body //////////////////
$fatturaBody = new FatturaElettronicaBody();

$datiGeneraliDocument = new DatiGeneraliDocumento();
$datiGeneraliDocument->setTipoDocumento("TD01")
                     ->setDivisa($this->invoice->client->currency()->code)
                     ->setData($this->invoice->date)
                     ->setNumero($this->invoice->number)
                     ->setCausale($this->invoice->public_notes ?? ''); //unsure...

/**PO information 
$datiOrdineAcquisto = new DatiOrdineAcquisto();
$datiOrdineAcquisto->setRiferimentoNumeroLinea(1)
                   ->setIdDocumento($this->invoice->po_number)
                   ->setNumItem(1)
                   ->setCodiceCIG("123abc")  // special invoice props
                   ->setCodiceCUP("456def"); // special invoice props
*/

/**Contract data 
$datiContratto = new DatiContratto(
    RiferimentoNumeroLinea: 1,
    IdDocumento: 6685,
    Data: "2024-01-01",
    NumItem: 5,
    CodiceCUP: "123abc",
    CodiceCIG: "456def",
);
*/

/**Shipping/Delivery Data
$datiRicezione = new DatiRicezione(
    RiferimentoNumeroLinea: 1,
    IdDocumento: 6685,
    Data: "2024-01-01",
    NumItem: 5,
    CodiceCUP: "123abc",
    CodiceCIG: "456def",
);
 */

 /**Shippers details
$datiAnagraficiVettore = new DatiAnagraficiVettore();
$idFiscaleIVA = new IdFiscaleIVA("IT", "24681012141");
$anagrafica = new Anagrafica("Trasport SPA");

$datiAnagraficiVettore->setIdFiscaleIVA($idFiscaleIVA)
                      ->setAnagrafica($anagrafica);

$datiTrasporto = new DatiTrasporto();
$datiTrasporto->setDatiAnagraficiVettore($datiAnagraficiVettore)
              ->setDataOraConsegna("2017-01-10T16:46:12.000+02:00");
*/

$datiGenerali = new DatiGenerali();
$datiGenerali->setDatiGeneraliDocumento($datiGeneraliDocument);
            //  ->setDatiOrdineAcquisto($datiOrdineAcquisto)
            //  ->setDatiContratto($datiContratto)
            //  ->setDatiRicezione($datiRicezione);


    $datiBeniServizi  = new DatiBeniServizi();
    $tax_rate_level = 0;
    //line items
    foreach ($this->invoice->line_items as $key => $item) 
    {

        $numero = $key+1;
        $dettaglioLinee = new DettaglioLinee(
            NumeroLinea: "{$numero}",
            Descrizione: $item->notes ?? 'Descrizione',
            Quantita: sprintf('%0.2f', $item->quantity),
            PrezzoUnitario: sprintf('%0.2f', $item->cost),
            PrezzoTotale: sprintf('%0.2f', $item->line_total),
            AliquotaIVA: sprintf('%0.2f', $item->tax_rate1),
        );

        $datiBeniServizi->setDettaglioLinee($dettaglioLinee);

        if ($item->tax_rate1 > $tax_rate_level) {
            $tax_rate_level = sprintf('%0.2f', $item->tax_rate1);
        }

    }
    
    //totals
    if($this->invoice->tax_rate1 > $tax_rate_level) {
        $tax_rate_level = sprintf('%0.2f', $this->invoice->tax_rate1);
    }

    $calc = $this->invoice->calc();
    $subtotal = sprintf('%0.2f', $calc->getSubTotal());
    $taxes = sprintf('%0.2f', $calc->getTotalTaxes());

    $datiRiepilogo = new DatiRiepilogo(
        AliquotaIVA: "{$tax_rate_level}",
        ImponibileImporto: "{$subtotal}",
        Imposta: "{$taxes}",
        EsigibilitaIVA: "I",
    );

    $datiBeniServizi->setDatiRiepilogo($datiRiepilogo);

    $dettalioPagament = new DettaglioPagamento(
        ModalitaPagamento: "MP01", //String
        DataScadenzaPagamento: (string) $this->invoice->due_date ?? $this->invoice->date,
        ImportoPagamento: (string) sprintf('%0.2f', $this->invoice->balance),
    );

    $datiPagamento = new DatiPagamento();
    $datiPagamento->setCondizioniPagamento("TP02")
                ->setDettaglioPagamento($dettalioPagament);

    $fatturaBody->setDatiGenerali($datiGenerali)
                ->setDatiBeniServizi($datiBeniServizi)
                ->setDatiPagamento($datiPagamento);

    ////////////////////////////////////
    $xmlService = new Service();

    $xml = $xmlService->write('p:FatturaElettronica', new FatturaElettronica($fatturaHeader, $fatturaBody));

    return $xml;

    }

}