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

namespace App\DataMapper\EDoc;

use Spatie\LaravelData\Data;

class Fact1 extends Data
{
    public string $sectorCode = 'SECTOR1';
    public string $BankId = '';
    public string $BankName = '';
    public string $PaymentMeans = 'TP02';

}
