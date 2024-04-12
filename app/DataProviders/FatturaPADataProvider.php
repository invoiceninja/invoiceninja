<?php
/**
* Invoice Ninja (https://invoiceninja.com).
*
* @link https://github.com/invoiceninja/invoiceninja source repository
*
* @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
*
* @license https://www.elastic.co/licensing/elastic-license
*/

namespace App\DataProviders;

class FatturaPADataProvider
{
    public array $regime_fiscale = [
        "RF01" => "Regime ordinario",
        "RF02" => "Regime dei contribuenti minimi (art. 1,c.96-117, L. 244/2007)",
        "RF04" => "Agricoltura e attività connesse e pesca (artt. 34 e 34-bis, D.P.R. 633/1972)",
        "RF05" => "Vendita sali e tabacchi (art. 74, c.1, D.P.R. 633/1972)",
        "RF06" => "Commercio dei fiammiferi (art. 74, c.1, D.P.R. 633/1972)",
        "RF07" => "Editoria (art. 74, c.1, D.P.R. 633/1972)",
        "RF08" => "Gestione di servizi di telefonia pubblica (art. 74, c.1, D.P.R. 633/1972)" ,
        "RF09" => "Rivendita di documenti di trasporto pubblico e di sosta (art. 74, c.1, D.P.R. 633/1972)" ,
        "RF10" => "Intrattenimenti, giochi e altre attività di cui alla tariffa allegata al D.P.R. 640/72 (art. 74, c.6, D.P.R. 633/1972)" ,
        "RF11" => "Agenzie di viaggi e turismo (art. 74-ter, D.P.R. 633/1972)" ,
        "RF12" => "Agriturismo (art. 5, c.2, L. 413/1991)" ,
        "RF13" => "Vendite a domicilio (art. 25-bis, c.6, D.P.R. 600/1973)" ,
        "RF14" => "Rivendita di beni usati, di oggetti d’arte, d’antiquariato o da collezione (art. 36, D.L. 41/1995)" ,
        "RF15" => "Agenzie di vendite all’asta di oggetti d’arte, antiquariato o da collezione (art. 40-bis, D.L. 41/1995)" ,
        "RF16" => "IVA per cassa P.A. (art. 6, c.5, D.P.R. 633/1972)" ,
        "RF17" => "IVA per cassa (art. 32-bis, D.L. 83/2012)" ,
        "RF19" => "Regime forfettario" ,
        "RF18" => "Altro"
    ];

    public array $tipo_documento = [
        'TD01' => 'Fattura',
        'TD02' => 'Acconto/Anticipo su fattura',
        'TD03' => 'Acconto/Anticipo su parcella',
        'TD04' => 'Nota di Credito',
        'TD05' => 'Nota di Debito',
        'TD06' => 'Parcella',
        'TD16' => 'Integrazione fattura reverse charge interno',
        'TD17' => 'Integrazione/autofattura per acquisto servizi dall’estero',
        'TD18' => 'Integrazione per acquisto di beni intracomunitari',
        'TD19' => 'Integrazione/autofattura per acquisto di beni ex art.17 c.2 DPR 633/72',
        'TD20' => 'Autofattura per regolarizzazione e integrazione delle fatture',
        'TD21' => 'Autofattura per splafonamento',
        'TD22' => 'Estrazione beni da Deposito IVA',
        'TD23' => 'Estrazione beni da Deposito IVA con versamento dell’IVA',
        'TD24' => 'Fattura differita di cui all’art.21, comma 4, lett. a)',
        'TD25' => 'Fattura differita di cui all’art.21, comma 4, terzo periodo lett. b)',
        'TD26' => 'Cessione di beni ammortizzabili e per passaggi interni ',
        'TD27' => 'Fattura per autoconsumo o per cessioni gratuite senza rivalsa',
    ];

    public array $esigibilita_iva = [
        'I' => 'IVA ad esigibilità immediata',
        'D' => 'IVA ad esigibilità differita',
        'S' => 'Scissione dei pagamenti',
    ];

    public array $modalita_pagamento = [
        'MP01' => 'contanti', //cash
        'MP02' => 'assegno', //check
        'MP03' => 'assegno circolare', //cashier's check
        'MP04' => 'contanti presso Tesoreria', //cash at treasury
        'MP05' => 'bonifico', //bank transfer
        'MP06' => 'vaglia cambiario', //bill of exchange
        'MP07' => 'bollettino bancario', //bank bulletin
        'MP08' => 'carta di pagamento', //payment card
        'MP09' => 'RID', //RID
        'MP10' => 'RID utenze', //RID utilities
        'MP11' => 'RID veloce', //fast RID
        'MP12' => 'Riba', //Riba
        'MP13' => 'MAV //MAV',
        'MP14' => 'quietanza erario stato', //state treasury receipt
        'MP15' => 'giroconto su conti di contabilità speciale', //transfer to special accounting accounts
        'MP16' => 'domiciliazione bancaria', //bank domiciliation
        'MP17' => 'domiciliazione postale', //postal domiciliation
        'MP18' => 'bollettino di c/c postale', //postal giro account
        'MP19' => 'SEPA Direct Debit', //SEPA Direct Debit
        'MP20' => 'SEPA Direct Debit CORE', //SEPA Direct Debit CORE
        'MP21' => 'SEPA Direct Debit B2B', //SEPA Direct Debit B2B
        'MP22' => 'Trattenuta su somme già riscosse', //Withholding on sums already collected
        'MP23' => 'PagoPA', //PagoPA
    ];

    public array $esigibilita_pagamento = [
        'TP01' => 'Pagamento a rate',
        'TP02' => 'Pagamento completo',
        'TP03' => 'Anticipo',
    ];

    public function __construct()
    {
    }
    
    public function getRegimeFiscale(): array
    {
        return $this->regime_fiscale;
    }
    public function getTipoDocumento(): array
    {
        return $this->tipo_documento;
    }
    
    public function getEsigibilitaIva(): array
    {
        return $this->esigibilita_iva;
    }

    public function getModalitaPagamento(): array
    {
        return $this->modalita_pagamento;
    }

    public function getEsigibilitaPagamento(): array
    {
        return $this->esigibilita_pagamento;
    }

}