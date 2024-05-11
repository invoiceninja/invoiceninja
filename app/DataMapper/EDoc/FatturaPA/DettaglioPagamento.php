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

namespace App\DataMapper\EDoc\FatturaPA;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use App\DataMapper\EDoc\FatturaPA\DatiVeicoli;
use Spatie\LaravelData\Attributes\WithTransformer;
use App\DataMapper\EDoc\FatturaPA\Body\DatiGenerali;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DettaglioPagamento extends Data
{

      //string length 4 - options
      public string $ModalitaPagamento = 'MP01';             

      // Amount2DecimalType
      public float $ImportoPagamento;      
      
      //String200LatinType
      public string|Optional $Beneficiario;     

      //date
      public string|Optional $DataRiferimentoTerminiPagamento;

      // <xs:restriction base="xs:integer">
      // <xs:minInclusive value="0" />
      // <xs:maxInclusive value="999" />
      public int|Optional $GiorniTerminiPagamento;    
      
      //date
      #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
      public  \Illuminate\Support\Carbon|Optional $DataScadenzaPagamento;

      //String20Type
      public string|Optional $CodUfficioPostale;          

      //String60LatinType
      public string|Optional $CognomeQuietanzante;

      //String60LatinType
      public string|Optional $NomeQuietanzante;     

      //string [A-Z0-9]{16}
      public string|Optional $CFQuietanzante;         

      //string {IsBasicLatin}{2,10}
      public string|Optional $TitoloQuietanzante;            

      //string String80LatinType
      public string|Optional $IstitutoFinanziario;

      //[a-zA-Z]{2}[0-9]{2}[a-zA-Z0-9]{11,30}
      public string|Optional $IBAN;                          
      
      // string [0-9][0-9][0-9][0-9][0-9]
      public string|Optional $ABI;        
      
      //[0-9][0-9][0-9][0-9][0-9]
      public string|Optional $CAB;        
      
      //[A-Z]{6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3}){0,1}
      public string|Optional $BIC;
      
      //Amount2DecimalType
      public float|Optional  $ScontoPagamentoAnticipato;     

      //Date
      #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
      public \Illuminate\Support\Carbon|Optional $DataLimitePagamentoAnticipato;  
      
      //Amount2DecimalType
      public float|Optional $PenalitaPagamentiRitardati;     

      //date
      #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
      public \Illuminate\Support\Carbon|Optional $DataDecorrenzaPenale;
      
      //String60Type
      public string|Optional $CodicePagamento;                

}