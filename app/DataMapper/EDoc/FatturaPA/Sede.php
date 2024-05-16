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

class Sede extends Data
{
        public string $Indirizzo = ''; //string - address,  60char limit

        public int $CAP = 12345; //[0-9][0-9][0-9][0-9][0-9] ie 12345
        
        public string $Comune = ''; //String 60char limit
        
        public string $Nazione = 'IT'; //String default IT
        
        public string|Optional $Provincia; //String [A-Z]{2}

        public string|Optional $NumeroCivico; // regex \p{IsBasicLatin}{1,8})
}