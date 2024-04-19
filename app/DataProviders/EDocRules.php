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
                "children" => [],
            ],
            [
                "key" => "DatiContratto",
                "label" => "Dati Contratto",
                "type" => "object",
                "resource" => "DatiContratto",
                "required" => false,
                "children" => [
                    [
                        "key"=> "RiferimentoNumeroLinea",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "IdDocumento",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "Data",
                        "validation" => [
                            "string","date","required",
                            "type" => "date",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "NumItem",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "CodiceCommessaConvenzione",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "CodiceCUP",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
[
                        "key"=> "CodiceCIG",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                ],
            ],
            [
                "key" => "DatiOrdineAcquisto",
                "label" => "Dati Ordine Acquisto",
                "type" => "object",
                "resource" => "DatiOrdineAcquisto",
                "required" => false,
                "children" => [
                    [
                        "key"=> "RiferimentoNumeroLinea",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "IdDocumento",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "Data",
                        "validation" => [
                            "string","date","required",
                            "type" => "date",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "NumItem",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "CodiceCommessaConvenzione",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "CodiceCUP",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
[
                        "key"=> "CodiceCIG",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                ],
            ],
            [
                "key" => "DatiAnagraficiVettore",
                "label" => "Dati Anagrafici Vettore",
                "type" => "object",
                "resource" => "DatiAnagraficiVettore",
                "required" => false,
                "children" => [
                    [
                        "key"=> "RiferimentoNumeroLinea",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "IdDocumento",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "Data",
                        "validation" => [
                            "string","date","required",
                            "type" => "date",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "NumItem",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "CodiceCommessaConvenzione",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                    [
                        "key"=> "CodiceCUP",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
[
                        "key"=> "CodiceCIG",
                        "validation" => [
                            "string","min:1","max:10","required",
                            "type" => "string",
                            "resource" => "",
                            "required" => true,
                        ],
                    ],
                ],
            ],
        ];
    }
}