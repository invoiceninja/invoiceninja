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
use App\DataMapper\EDoc\FatturaPA;

class EDocSettings extends Data
{
    public FatturaPA|Optional $FatturaPA;

    public function __construct() {}

    public function createFatturaPA(): FatturaPA
    {
        return $this->FatturaPA ??= new FatturaPA();
    }

}