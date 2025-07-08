<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
