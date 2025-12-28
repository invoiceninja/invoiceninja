<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\Validation\Peppol;

use Symfony\Component\Serializer\Attribute\SerializedName;
use InvoiceNinja\EInvoice\Models\Peppol\PeriodType\InvoicePeriod;

class InvoiceLevel
{
    /** @var InvoicePeriod[] */
    #[SerializedName('cac:InvoicePeriod')]
    public array $InvoicePeriod;

}
