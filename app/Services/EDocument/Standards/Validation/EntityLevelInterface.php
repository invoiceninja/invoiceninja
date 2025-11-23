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

namespace App\Services\EDocument\Standards\Validation;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;

interface EntityLevelInterface
{
    public function checkClient(Client $client): array;

    public function checkCompany(Company $company): array;

    public function checkInvoice(Invoice $invoice): array;

}
