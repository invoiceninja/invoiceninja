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

namespace App\Services\EDocument\Standards;

use App\Models\Invoice;
use App\Services\AbstractService;

class Peppol extends AbstractService
{
    /**
     * @param Invoice $invoice
     */
    public function __construct(public Invoice $invoice)
    {
    }

    public function run()
    {
    }
}