<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use Invoiceninja\Einvoice\Models\FatturaPA\FatturaElettronica;

/**
 * @test
 */
class FatturaPATest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

    }

    private array $payload = [
        'FatturaElettronicaHeader' => [
        'DatiTrasmissione' => [
            'IdTrasmittente' => [
            'IdPaese' => 'IT',
            'IdCodice' => '01234567890',
            ],
            'ProgressivoInvio' => '00001',
            'FormatoTrasmissione' => 'FPA12',
            'CodiceDestinatario' => 'AAAAAA',
        ],
        'CedentePrestatore' => [
            'DatiAnagrafici' => [
            'IdFiscaleIVA' => [
                'IdPaese' => 'IT',
                'IdCodice' => '01234567890',
            ],
            'Anagrafica' => [
                'Denominazione' => 'ALPHA SRL',
            ],
            'RegimeFiscale' => 'RF19',
            ],
            'Sede' => [
            'Indirizzo' => 'VIALE ROMA 543',
            'CAP' => '07100',
            'Comune' => 'SASSARI',
            'Provincia' => 'SS',
            'Nazione' => 'IT',
            ],
        ],
        'CessionarioCommittente' => [
            'DatiAnagrafici' => [
            'CodiceFiscale' => '09876543210',
            'Anagrafica' => [
                'Denominazione' => 'AMMINISTRAZIONE BETA',
            ],
            ],
            'Sede' => [
            'Indirizzo' => 'VIA TORINO 38-B',
            'CAP' => '00145',
            'Comune' => 'ROMA',
            'Provincia' => 'RM',
            'Nazione' => 'IT',
            ],
        ],
        ],
        'FatturaElettronicaBody' => [
        'DatiGenerali' => [
            'DatiGeneraliDocumento' => [
            'TipoDocumento' => 'TD01',
            'Divisa' => 'EUR',
            'Data' => '2017-01-18',
            'Numero' => '123',
            'Causale' => [
                0 => 'LA FATTURA FA RIFERIMENTO ',
                1 => 'SEGUE DESCRIZIONE CAUSALE NEL CASO IN CUI NON SIANO STATI SUFFICIENTI 200
                            CARATTERI AAAAAAAAAAA BBBBBBBBBBBBBBBBB',
            ],
            ],
            'DatiOrdineAcquisto' => [
            'RiferimentoNumeroLinea' => '1',
            'IdDocumento' => '66685',
            'NumItem' => '1',
            'CodiceCUP' => '123abc',
            'CodiceCIG' => '456def',
            ],
            'DatiContratto' => [
            'RiferimentoNumeroLinea' => '1',
            'IdDocumento' => '123',
            'Data' => '2016-09-01',
            'NumItem' => '5',
            'CodiceCUP' => '123abc',
            'CodiceCIG' => '456def',
            ],
            'DatiConvenzione' => [
            'RiferimentoNumeroLinea' => '1',
            'IdDocumento' => '456',
            'NumItem' => '5',
            'CodiceCUP' => '123abc',
            'CodiceCIG' => '456def',
            ],
            'DatiRicezione' => [
            'RiferimentoNumeroLinea' => '1',
            'IdDocumento' => '789',
            'NumItem' => '5',
            'CodiceCUP' => '123abc',
            'CodiceCIG' => '456def',
            ],
            'DatiTrasporto' => [
            'DatiAnagraficiVettore' => [
                'IdFiscaleIVA' => [
                'IdPaese' => 'IT',
                'IdCodice' => '24681012141',
                ],
                'Anagrafica' => [
                'Denominazione' => 'Trasporto spa',
                ],
            ],
            'DataOraConsegna' => '2017-01-10T16:46:12.000+02:00',
            ],
        ],
        'DatiBeniServizi' => [
            'DettaglioLinee' => [
            'NumeroLinea' => '1',
            'Descrizione' => 'DESCRIZIONE DELLA FORNITURA',
            'Quantita' => '5.00',
            'PrezzoUnitario' => '1.00',
            'PrezzoTotale' => '5.00',
            'AliquotaIVA' => '22.00',
            ],
            'DatiRiepilogo' => [
            'AliquotaIVA' => '22.00',
            'ImponibileImporto' => '5.00',
            'Imposta' => '1.10',
            'EsigibilitaIVA' => 'I',
            ],
        ],
        'DatiPagamento' => [
            'CondizioniPagamento' => 'TP01',
            'DettaglioPagamento' => [
            'ModalitaPagamento' => 'MP01',
            'DataScadenzaPagamento' => '2017-02-18',
            'ImportoPagamento' => '6.10',
            ],
        ],
        ],
    ];

    public function testBulkValidation()
    {

        $files = [
            'tests/Integration/Einvoice/samples/fatturapa1.xml',
            'tests/Integration/Einvoice/samples/fatturapa2.xml',
            'tests/Integration/Einvoice/samples/fatturapa3.xml',
            'tests/Integration/Einvoice/samples/fatturapa4.xml',
            'tests/Integration/Einvoice/samples/fatturapa5.xml',
            'tests/Integration/Einvoice/samples/fatturapa6.xml',
        ];

        foreach($files as $f)
        {
            
            // nlog("File => {$f}");

            $xmlstring = file_get_contents($f);

            $xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
            $json = json_encode($xml);
            $payload = json_decode($json, true);

            $validation_array = false;
            try {
                $rules = FatturaElettronica::getValidationRules($this->payload);
                // nlog($rules);
                
                $this->assertIsArray($rules);

                $payload = FatturaElettronica::from($payload)->toArray();
                // nlog($payload);
                $this->assertIsArray($payload);

                $validation_array = FatturaElettronica::validate($payload);
                
                $this->assertIsArray($validation_array);

            } catch(\Illuminate\Validation\ValidationException $e) {

                nlog($e->errors());
            }

            $this->assertIsArray($validation_array);

        }
    }

    public function testLaravelDataValidation()
    {


        $rules = FatturaElettronica::getValidationRules($this->payload);
        // nlog($rules);


        $this->assertIsArray($rules);

        // $validation_array = false;

        try {
            $validation_array = FatturaElettronica::validate($this->payload);
        }
        catch(\Illuminate\Validation\ValidationException $e) {

            nlog($e->errors());

        }

        $this->assertIsArray($validation_array);

        // try{
        $array = FatturaElettronica::from($this->payload)->toArray();
        // }
        // catch(\Exception $e){

        // echo $e->errors();
            // $errors = $e->getErrors();

        // echo $e->getMessage().PHP_EOL;
        // }

        // $this->assertIsArray($array);
    }


}
