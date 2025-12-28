<?php

/**
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
