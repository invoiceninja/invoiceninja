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

namespace App\DataMapper;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Invoiceninja\Einvoice\Models\FatturaPA\FatturaElettronica;

class EDocSettings extends Data
{
    public FatturaElettronica|Optional $FatturaElettronica;

    public function __construct() {}

    public function createFatturaPA(): FatturaElettronica
    {
        return $this->FatturaElettronica ??= new FatturaElettronica;
    }

}