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

class ScontoMaggiorazione extends Data
{
      //string options 
      //SC - Sconto //discount
      //MG - Maggiorazione //surcharge
      public string $Tipo;      
      //float 0-100
      public float|Optional $Percentuale;
      
      //Amount8DecimalType
      public float|Optional $Importo; 
}
