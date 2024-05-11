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

use App\DataMapper\EDoc\FatturaPA\DettaglioPagamento;
use Spatie\LaravelData\Data;

class DatiPagamento extends Data
{
    //string min4 max4 - optionlist
    public string $CondizioniPagamento;

    public DettaglioPagamento $DettaglioPagamento;
}