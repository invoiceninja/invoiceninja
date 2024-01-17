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

use App\Models\Invoice;
use App\Services\AbstractService;
use SimpleXMLElement;

/*
<?xml version="1.0" encoding="UTF-8"?>
<FatturaElettronica versione="FPR12" xmlns="http://ivaservizi.agenziaentrate.gov.it/docs/xsd/fatture/v1.2">
    <FatturaElettronicaHeader>
        <DatiTrasmissione>
            <IdTrasmittente>
                <IdPaese>IT</IdPaese>
                <IdCodice>01234567890</IdCodice>
            </IdTrasmittente>
            <ProgressivoInvio>00001</ProgressivoInvio>
            <FormatoTrasmissione>FPR12</FormatoTrasmissione>
            <CodiceDestinatario>ABCDE1</CodiceDestinatario>
        </DatiTrasmissione>
        <CedentePrestatore>
            <!-- Company information of the sender (seller/provider) -->
        </CedentePrestatore>
        <CessionarioCommittente>
            <!-- Company information of the receiver (buyer) -->
        </CessionarioCommittente>
    </FatturaElettronicaHeader>
    <FatturaElettronicaBody>
        <DatiGenerali>
            <DatiGeneraliDocumento>
                <TipoDocumento>TD01</TipoDocumento>
                <Divisa>EUR</Divisa>
                <Data>2023-04-21</Data>
                <Numero>1</Numero>
                <!-- Add other information as needed -->
            </DatiGeneraliDocumento>
            <!-- Add other general data as needed -->
        </DatiGenerali>
        <DatiBeniServizi>
            <!-- List of items or services -->
        </DatiBeniServizi>
        <DatiPagamento>
            <!-- Payment details -->
        </DatiPagamento>
    </FatturaElettronicaBody>
</FatturaElettronica>
*/

class FatturaPA extends AbstractService
{
    private $xml;

    public function __construct(public Invoice $invoice)
    {
        $this->xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><FatturaElettronica></FatturaElettronica>');
    }

    public function run()
    {
        return $this->addHeader()->getXml();
    }

    public function addHeader()
    {
        $this->xml->addChild('FatturaElettronicaHeader');
        return $this;
    }

    public function addTrasmissioneData($idPaese, $idCodice, $progressivoInvio, $formatoTrasmissione, $codiceDestinatario)
    {
        $datiTrasmissione = $this->xml->FatturaElettronicaHeader->addChild('DatiTrasmissione');
        $idTrasmittente = $datiTrasmissione->addChild('IdTrasmittente');
        $idTrasmittente->addChild('IdPaese', $idPaese);
        $idTrasmittente->addChild('IdCodice', $idCodice);
        $datiTrasmissione->addChild('ProgressivoInvio', $progressivoInvio);
        $datiTrasmissione->addChild('FormatoTrasmissione', $formatoTrasmissione);
        $datiTrasmissione->addChild('CodiceDestinatario', $codiceDestinatario);
        return $this;
    }

    public function addCedentePrestatore($data)
    {
        // Add CedentePrestatore data
    }

    public function addCessionarioCommittente($data)
    {
        // Add CessionarioCommittente data
    }

    public function addBody()
    {
        $this->xml->addChild('FatturaElettronicaBody');
        return $this;
    }

    public function addDatiGenerali($data)
    {
        // Add DatiGenerali data
    }

    public function addLineItem($data)
    {
        if (!isset($this->xml->FatturaElettronicaBody->DatiBeniServizi)) {
            $this->xml->FatturaElettronicaBody->addChild('DatiBeniServizi');
        }
        $lineItem = $this->xml->FatturaElettronicaBody->DatiBeniServizi->addChild('DettaglioLinee');
        $lineItem->addChild('NumeroLinea', $data['NumeroLinea']);
        $lineItem->addChild('Descrizione', $data['notes']);
        $lineItem->addChild('Quantita', $data['quantity']);
        $lineItem->addChild('PrezzoUnitario', $data['cost']);
        $lineItem->addChild('PrezzoTotale', $data['line_total']);
        $lineItem->addChild('AliquotaIVA', $data['tax_rate1']);

        if (isset($data['UnitaMisura'])) {
            $lineItem->addChild('UnitaMisura', $data['UnitaMisura']);
        }

        return $this;
    }

    public function addDatiPagamento($data)
    {
        // Add DatiPagamento data
    }


    public function getXml()
    {
        return $this->xml->asXML();
    }
}

// $fattura = new FatturaPA();
// $fattura
//     ->addHeader()
//     ->addTrasmissioneData('IT', '01234567890', '00001', 'FPR12', 'ABCDE1');

// echo $fattura->getXml();
