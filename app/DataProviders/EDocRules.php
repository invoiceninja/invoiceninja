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

class EDocRules
{
    // [
    //     "key" => "",
    //     "label" => "",
    //     "type" => "dropdown/date/string/text",
    //     "resource" => "resource.json",
    //     "required" => true,
    // ]
    public function rules() 
    {
                
        return [
            'FatturaPA' => $this->FatturaPADefaults(),
        ];

    }


    private function FatturaPADefaults()
    {
        return [
            [
                "key" => "RegimeFiscale",
                "label" => "Regime Fiscale",
                "type" => "dropdown",
                "resource" => "RegimeFiscale.json",
                "required" => true,
            ],
            [
                "key" => "TipoDocumento",
                "label" => "Tipo Documento",
                "type" => "dropdown",
                "resource" => "TipoDocumento.json",
                "required" => true,
            ],
            [
                "key" => "ModalitaPagamento",
                "label" => "Modalita Pagamento",
                "type" => "dropdown",
                "resource" => "ModalitaPagamento.json",
                "required" => true,
            ],
            [
                "key" => "CondizioniPagamento",
                "label" => "Condizioni Pagamento",
                "type" => "dropdown",
                "resource" => "CondizioniPagamento.json",
                "required" => true,
            ],
            [
                "key" => "DatiRicezione",
                "label" => "Dati Ricezione",
                "type" => "dropdown",
                "resource" => "CondizioniPagamento",
                "required" => false,
            ],
            [
                "key" => "DatiContratto",
                "label" => "Dati Contratto",
                "type" => "object",
                "resource" => "DatiContratto",
                "required" => false,
            ],
            [
                "key" => "DatiOrdineAcquisto",
                "label" => "Dati Ordine Acquisto",
                "type" => "object",
                "resource" => "DatiOrdineAcquisto",
                "required" => false,
            ],
            [
                "key" => "DatiAnagraficiVettore",
                "label" => "Dati Anagrafici Vettore",
                "type" => "object",
                "resource" => "DatiAnagraficiVettore",
                "required" => false,
            ],
        ];
    }
}