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
use InvalidArgumentException;

class FranceEReportPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(
        Company $company,
        FranceEReportContext $context,
        FRReportData $report,
        string $idempotencyGuid,
    ): array
    {
        $this->validate($company, $context, $report);

        $frEReport = $report->toArray();
        unset($frEReport['schemaVersion']);

        $declarant = $report->declarantParty;
        $frEReport['declarantParty'] = array_filter([
            'publicIdentifiers' => array_map(
                static fn ($identifier): array => $identifier->toArray(),
                $declarant->publicIdentifiers,
            ),
            'party' => $declarant->party?->companyName
                ? ['companyName' => $declarant->party->companyName]
                : null,
        ], static fn (mixed $value): bool => ! is_null($value));

        return FranceEReportStorecoveProjection::from([
            'legalEntityId' => $context->legalEntityId,
            'idempotencyGuid' => $idempotencyGuid,
            'document' => [
                'documentType' => 'fr_e_report',
                'frEReport' => $frEReport,
            ],
        ]);
    }

    private function validate(
        Company $company,
        FranceEReportContext $context,
        FRReportData $report,
    ): void
    {
        if ((int) $company->id !== $context->companyId
            || (int) $company->legal_entity_id !== $context->legalEntityId) {
            throw new InvalidArgumentException('France e-report context does not match the company legal entity.');
        }

        if ($report->issueDate !== $context->issuedAt->toDateString()
            || $report->issueTime !== $context->issuedAt->format('H:i:s')
            || $report->timeZone !== $context->issuedAt->format('O')) {
            throw new InvalidArgumentException('France e-report metadata does not match its compilation context.');
        }

        $section = $report->transactionReport ?? $report->paymentReport;

        if ($section?->period !== $context->period()) {
            throw new InvalidArgumentException('France e-report period does not match its compilation context.');
        }
    }
}
