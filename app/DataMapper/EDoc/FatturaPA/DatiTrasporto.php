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
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DatiTrasporto extends Data
{
      public DatiAnagraficiVettore|Optional $DatiAnagraficiVettore; 
      
      //String80LatinType
      public string|Optional $MezzoTrasporto;        
      
      //String100LatinType
      public string|Optional $CausaleTrasporto;      
      
      //int 1-9999
      public int|Optional $NumeroColli;          
      
      //String100LatinType
      public string|Optional $Descrizione;       
      
      //String10Type
      public string|Optional $UnitaMisuraPeso;   
      
      
      //[0-9]{1,4}\.[0-9]{1,2} //decimal
      public float|Optional $PesoLordo;         
      
      //[0-9]{1,4}\.[0-9]{1,2} //decimal
      public float|Optional $PesoNetto;
      
      //dateTime
      #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d\TH:i:s.uP')]
      public Carbon|Optional $DataOraRitiro;
      //date
      #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
      public Carbon|Optional $DataInizioTrasporto;
      
      //[A-Z]{3}
      public string|Optional $TipoResa;              

      public Sede|Optional $IndirizzoResa;        
      
      //dateTime
      #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
      public Carbon|Optional $DataOraConsegna;


//     public function __construct()
//     {
      //   $this->DataOraConsegna = new \DateTime();
      //   $this->DataInizioTrasporto = new \DateTime();
      //   $this->DataInizioTrasporto = new \DateTime();
//     }

}
