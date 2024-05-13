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

namespace App\DataMapper\EDoc\FatturaPA\Body;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Size;
use Spatie\LaravelData\Attributes\Validation\Regex;

class DatiCassaPrevidenziale extends Data
{
      //string 4char options
      #[Size(4)]
      public string $TipoCassa;

      //rate type 0-100
      #[Regex('/^[0-9]{1,3}\.[0-9]{2}$/')]
      public float $AlCassa = 0;
      
      //Amount2DecimalType
      #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
      public float $ImportoContributoCassa;
      
      //rate type 0-100
      #[Regex('/^[0-9]{1,3}\.[0-9]{2}$/')]
      public float $AliquotaIVA;

      //Amount2DecimalType
      #[Regex('/^[\-]?[0-9]{1,11}\.[0-9]{2}$/')]
      public float|Optional $ImponibileCassa;                    

      //string string options
      #[Size(4)]
      public string|Optional $Ritenuta;       
      
      //string 2char options 
      public string|Optional $Natura;

      //String20Type
      #[Max(20)]
      public string|Optional $RiferimentoAmministrazione;         

}
