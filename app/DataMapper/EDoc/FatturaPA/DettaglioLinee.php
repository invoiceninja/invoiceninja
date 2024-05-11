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
use Spatie\LaravelData\Attributes\WithTransformer;
use App\DataMapper\EDoc\FatturaPA\Generics\CodiceArticolo;
use App\DataMapper\EDoc\FatturaPA\Body\ScontoMaggiorazione;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class DettaglioLinee extends Data
{

      //1-9999
    public int $NumeroLinea;                
      
    //String1000LatinType
    public string $Descrizione;
      
    //Amount8DecimalType
    public float $PrezzoUnitario;             
      
    //   Amount8DecimalType
    public float $PrezzoTotale;               

    //       <xs:restriction base="xs:decimal">
    //   <xs:maxInclusive value="100.00" />
    //   <xs:pattern value="/^[0-9]{1,3}\.[0-9]{2}$/" />
    // 0-100  
    public float $AliquotaIVA;                

    //string 2 char options
    public string|Optional $TipoCessionePrestazione;    

    public CodiceArticolo|Optional $CodiceArticolo;        
    
    //     <xs:restriction base="xs:decimal">
    //   <xs:pattern value="[0-9]{1,12}\.[0-9]{2,8}" />
    public float|Optional $Quantita;                

    //String10Type
    public string|Optional $UnitaMisura;                

    //date
    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public \Illuminate\Support\Carbon|Optional $DataInizioPeriodo;           

    //date
    #[WithTransformer(DateTimeInterfaceTransformer::class, format: 'Y-m-d')]
    public \Illuminate\Support\Carbon|Optional $DataFinePeriodo;             

    public ScontoMaggiorazione|Optional $ScontoMaggiorazione;         

    //4 char options
    public string|Optional $Ritenuta;               

    //string options 
    public string|Optional $Natura;

    //string 20 char
    public string|Optional $RiferimentoAmministrazione;

    public AltriDatiGestionali|Optional $AltriDatiGestionali;         


}

