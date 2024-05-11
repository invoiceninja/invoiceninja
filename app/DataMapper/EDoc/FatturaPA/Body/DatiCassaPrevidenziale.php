
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

class DatiCassaPrevidenziale extends Data
{
      //string 4char options
      public string $TipoCassa;
      

      //rate type 0-100
      public float $AlCassa = 0;
      
      //Amount2DecimalType
      public float $ImportoContributoCassa;
      
      
      //rate type 0-100
      public float $AliquotaIVA;

      //Amount2DecimalType
      public float|Optional $ImponibileCassa;                    

      //string string options
      public string|Optional $Ritenuta;       
      
      //string 2char options 
      public string|Optional $Natura;

      //String20Type
      public string|Optional $RiferimentoAmministrazione;         

}
