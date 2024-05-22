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
use App\DataMapper\EDoc\FatturaPA\FatturaElettronica;
use Tests\MockAccountData;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 */
class FatturaPATest extends TestCase
{

    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        // $this->withoutExceptionHandling();
    }

    private string $sample_request = '{"FatturaElettronicaHeader":{"DatiTrasmissione":{"IdTrasmittente":{"IdPaese":"","IdCodice":""},"ProgressivoInvio":"","FormatoTrasmissione":"","CodiceDestinatario":"","ContattiTrasmittente":{"Telefono":"","Email":""},"PECDestinatario":""},"CedentePrestatore":{"DatiAnagrafici":{"IdFiscaleIVA":{"IdPaese":"","IdCodice":""},"CodiceFiscale":"","Anagrafica":{"Denominazione":"","Nome":"","Cognome":"","Titolo":"","CodEORI":""},"AlboProfessionale":"","ProvinciaAlbo":"","NumeroIscrizioneAlbo":"","DataIscrizioneAlbo":"","RegimeFiscale":"RF01"},"Sede":{"Indirizzo":"","NumeroCivico":"","CAP":"","Comune":"","Provincia":"","Nazione":""},"StabileOrganizzazione":{"Indirizzo":"","NumeroCivico":"","CAP":"","Comune":"","Provincia":"","Nazione":""},"IscrizioneREA":{"Ufficio":"","NumeroREA":"","CapitaleSociale":0,"SocioUnico":"","StatoLiquidazione":""},"Contatti":{"Telefono":"","Fax":"","Email":""},"RiferimentoAmministrazione":""},"RappresentanteFiscale":{"DatiAnagrafici":{"IdFiscaleIVA":{"IdPaese":"","IdCodice":""},"CodiceFiscale":"","Anagrafica":{"Denominazione":"","Nome":"","Cognome":"","Titolo":"","CodEORI":""}}},"CessionarioCommittente":{"DatiAnagrafici":{"IdFiscaleIVA":{"IdPaese":"","IdCodice":""},"CodiceFiscale":"","Anagrafica":{"Denominazione":"","Nome":"","Cognome":"","Titolo":"","CodEORI":""}},"Sede":{"Indirizzo":"","NumeroCivico":"","CAP":"","Comune":"","Provincia":"","Nazione":""},"StabileOrganizzazione":{"Indirizzo":"","NumeroCivico":"","CAP":"","Comune":"","Provincia":"","Nazione":""},"RappresentanteFiscale":{"Denominazione":"","Nome":"","Cognome":"","IdFiscaleIVA":{"IdPaese":"","IdCodice":""}}},"TerzoIntermediarioOSoggettoEmittente":{"DatiAnagrafici":{"IdFiscaleIVA":{"IdPaese":"","IdCodice":""},"CodiceFiscale":"","Anagrafica":{"Denominazione":"","Nome":"","Cognome":"","Titolo":"","CodEORI":""}}},"SoggettoEmittente":""},"FatturaElettronicaBody":{"DatiGenerali":{"DatiGeneraliDocumento":{"TipoDocumento":"TD01","Divisa":"","Data":"","Numero":"","DatiRitenuta":{"TipoRitenuta":"","ImportoRitenuta":0,"AliquotaRitenuta":0,"CausalePagamento":""},"DatiBollo":{"BolloVirtuale":"","ImportoBollo":0},"DatiCassaPrevidenziale":{"TipoCassa":"","AlCassa":0,"ImportoContributoCassa":0,"ImponibileCassa":0,"AliquotaIVA":0,"Ritenuta":"","Natura":"","RiferimentoAmministrazione":""},"ScontoMaggiorazione":{"Tipo":"","Percentuale":0,"Importo":0},"ImportoTotaleDocumento":0,"Arrotondamento":0,"Causale":"","Art73":""},"DatiOrdineAcquisto":{"RiferimentoNumeroLinea":"","IdDocumento":"","Data":"","NumItem":"","CodiceCommessaConvenzione":"","CodiceCUP":"","CodiceCIG":""},"DatiContratto":{"RiferimentoNumeroLinea":"","IdDocumento":"","Data":"","NumItem":"","CodiceCommessaConvenzione":"","CodiceCUP":"","CodiceCIG":""},"DatiConvenzione":{"RiferimentoNumeroLinea":"","IdDocumento":"","Data":"","NumItem":"","CodiceCommessaConvenzione":"","CodiceCUP":"","CodiceCIG":""},"DatiRicezione":{"RiferimentoNumeroLinea":"","IdDocumento":"","Data":"","NumItem":"","CodiceCommessaConvenzione":"","CodiceCUP":"","CodiceCIG":""},"DatiFattureCollegate":{"RiferimentoNumeroLinea":"","IdDocumento":"","Data":"","NumItem":"","CodiceCommessaConvenzione":"","CodiceCUP":"","CodiceCIG":""},"DatiSAL":{"RiferimentoFase":""},"DatiDDT":{"NumeroDDT":"","DataDDT":"","RiferimentoNumeroLinea":""},"DatiTrasporto":{"DatiAnagraficiVettore":{"IdFiscaleIVA":{"IdPaese":"","IdCodice":""},"CodiceFiscale":"","NumeroLicenzaGuida":""},"MezzoTrasporto":"","CausaleTrasporto":"","NumeroColli":"","Descrizione":"","UnitaMisuraPeso":"","PesoLordo":0,"PesoNetto":0,"DataOraRitiro":"","DataInizioTrasporto":"","TipoResa":"","IndirizzoResa":{"Indirizzo":"","NumeroCivico":"","CAP":"","Comune":"","Provincia":"","Nazione":""},"DataOraConsegna":""},"FatturaPrincipale":{"NumeroFatturaPrincipale":"","DataFatturaPrincipale":""}},"DatiBeniServizi":{"DettaglioLinee":{"NumeroLinea":"","TipoCessionePrestazione":"","CodiceArticolo":{"CodiceTipo":"","CodiceValore":""},"Descrizione":"","Quantita":0,"UnitaMisura":"","DataInizioPeriodo":"","DataFinePeriodo":"","PrezzoUnitario":0,"ScontoMaggiorazione":{"Tipo":"","Percentuale":0,"Importo":0},"PrezzoTotale":0,"AliquotaIVA":0,"Ritenuta":"","Natura":"","RiferimentoAmministrazione":"","AltriDatiGestionali":{"TipoDato":"","RiferimentoTesto":"","RiferimentoNumero":0,"RiferimentoData":""}},"DatiRiepilogo":{"AliquotaIVA":0,"Natura":"","SpeseAccessorie":0,"Arrotondamento":0,"ImponibileImporto":0,"Imposta":0,"EsigibilitaIVA":"","RiferimentoNormativo":""}},"DatiVeicoli":{"Data":"","TotalePercorso":""},"DatiPagamento":{"CondizioniPagamento":"TP02","DettaglioPagamento":{"Beneficiario":"","ModalitaPagamento":"MP01","DataRiferimentoTerminiPagamento":"","GiorniTerminiPagamento":"","DataScadenzaPagamento":"","ImportoPagamento":0,"CodUfficioPostale":"","CognomeQuietanzante":"","NomeQuietanzante":"","CFQuietanzante":"","TitoloQuietanzante":"","IstitutoFinanziario":"","IBAN":"","ABI":"","CAB":"","BIC":"","ScontoPagamentoAnticipato":0,"DataLimitePagamentoAnticipato":"","PenalitaPagamentiRitardati":0,"DataDecorrenzaPenale":"","CodicePagamento":""}},"Allegati":{"NomeAttachment":"","AlgoritmoCompressione":"","FormatoAttachment":"","DescrizioneAttachment":"","Attachment":""}}}';

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

    private array $bad_payload = [
        'FatturaElettronicaHeader' => [
        'DatiTrasmissione' => [
            'IdTrasmittente' => [
            'IdPaese' => '',
            'IdCodice' => '01234567890',
            ],
            'ProgressivoInvio' => '',
            'FormatoTrasmissione' => '',
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

    // public function testValidateSampleRequest()
    // {
    //     $response = json_decode($this->sample_request, 1);
        
        
    //     $rules = FatturaElettronica::getValidationRules($response);
    //     nlog($rules);

    //     try{
    //     $validation_array = FatturaElettronica::validate($response);
    //     }
    //     catch(\Illuminate\Validation\ValidationException $e) {

    //         nlog($e->errors());
    //     }
    //     $payload = FatturaElettronica::from($response)->toArray();
    //     nlog($payload);
    //     $this->assertIsArray($payload);

        
    // }
//     public function testBulkValidationX()
//     {
    
// $files = [
//     'tests/Integration/Einvoice/samples/fatturapa0.xml',
// ];

// foreach($files as $f) {

//     $xmlstring = file_get_contents($f);

//     $xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
//     $json = json_encode($xml);
//     $payload = json_decode($json, true);

//     nlog($payload);

//     $validation_array = false;
    
//         nlog($f);

//         $rules = FatturaElettronica::getValidationRules($this->payload);
//         nlog($rules);

//         $this->assertIsArray($rules);

//         $payload = FatturaElettronica::from($payload)->toArray();
//         // nlog($payload);

//         $this->assertIsArray($payload);

//         $validation_array = FatturaElettronica::validate($payload);

//         $this->assertIsArray($validation_array);

//     // } catch(\Illuminate\Validation\ValidationException $e) {

//     //     nlog($e->errors());
//     // }

//     $this->assertIsArray($validation_array);

// }

//     }

    // public function testBulkValidation()
    // {

    //     $files = [
    //         'tests/Integration/Einvoice/samples/fatturapa0.xml',
    //         'tests/Integration/Einvoice/samples/fatturapa1.xml',
    //         'tests/Integration/Einvoice/samples/fatturapa2.xml',
    //         'tests/Integration/Einvoice/samples/fatturapa3.xml',
    //         'tests/Integration/Einvoice/samples/fatturapa4.xml',
    //         'tests/Integration/Einvoice/samples/fatturapa5.xml',
    //         'tests/Integration/Einvoice/samples/fatturapa6.xml',
    //     ];

    //     foreach($files as $f)
    //     {

    //         $xmlstring = file_get_contents($f);

    //         $xml = simplexml_load_string($xmlstring, "SimpleXMLElement", LIBXML_NOCDATA);
    //         $json = json_encode($xml);
    //         $payload = json_decode($json, true);

    //         nlog($payload);

    //         $validation_array = false;
    //         try {
    //             nlog($f);

    //             $rules = FatturaElettronica::getValidationRules($this->payload);
    //             nlog($rules);
                
    //             $this->assertIsArray($rules);

    //             $payload = FatturaElettronica::from($payload)->toArray();
    //             nlog($payload);

    //             $this->assertIsArray($payload);

    //             $validation_array = FatturaElettronica::validate($payload);
                
    //             $this->assertIsArray($validation_array);

    //         } catch(\Illuminate\Validation\ValidationException $e) {

    //             nlog($e->errors());
    //         }

    //         $this->assertIsArray($validation_array);

    //     }
    // }

//     public function testUpdateProps()
//     {
//         $update = [
//             'e_invoice' => $this->payload
//         ];

//         $response = $this->withHeaders([
//             'X-API-SECRET' => config('ninja.api_secret'),
//             'X-API-TOKEN' => $this->token,
//         ])->putJson('/api/v1/companies/'.$this->company->hashed_id, $update);

//         $response->assertStatus(200);

//         $arr = $response->json();

//         $this->assertNotNull($arr['data']['e_invoice']);
//     }


//     public function testUpdateBadProps()
//     {
//         $update = [
//             'e_invoice' => $this->bad_payload
//         ];

//         $response = $this->withHeaders([
//             'X-API-SECRET' => config('ninja.api_secret'),
//             'X-API-TOKEN' => $this->token,
//         ])->putJson('/api/v1/companies/'.$this->company->hashed_id, $update);

//         $response->assertStatus(200);

//         $arr = $response->json();

//         $this->assertNotNull($arr['data']['e_invoice']);
//     }


//     public function testLaravelDataValidation()
//     {


//         $rules = FatturaElettronica::getValidationRules($this->payload);
//         // nlog($rules);


//         $this->assertIsArray($rules);

//         // $validation_array = false;

//         try {
//             $validation_array = FatturaElettronica::validate($this->payload);
//         }
//         catch(\Illuminate\Validation\ValidationException $e) {

//             nlog($e->errors());

//         }

//         $this->assertIsArray($validation_array);

//         // try{
//         $array = FatturaElettronica::from($this->payload)->toArray();
//         // }
//         // catch(\Exception $e){

//         // echo $e->errors();
//             // $errors = $e->getErrors();

//         // echo $e->getMessage().PHP_EOL;
//         // }

//         // $this->assertIsArray($array);
//     }


}
