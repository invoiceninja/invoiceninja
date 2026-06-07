<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards\France;

use App\DataMapper\FranceEReporting\FRReportData;
use App\Models\Company;

class FranceEReportPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Company $company, FRReportData $report): array
    {
        return [
            'legalEntityId' => (int) $company->legal_entity_id,
            'document' => [
                'documentType' => 'fr_e_report',
                'frEReport' => $report->toArray(),
            ],
        ];
    }
}