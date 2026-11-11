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

use App\DataMapper\FranceEReporting\ReportDataValidator;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class FranceEReportContext
{
    public function __construct(
        public int $companyId,
        public int $legalEntityId,
        public string $periodStart,
        public string $periodEnd,
        public CarbonImmutable $issuedAt,
    ) {
        if ($this->companyId < 1) {
            throw new InvalidArgumentException('France e-report companyId must be a positive integer.');
        }

        if ($this->legalEntityId < 1) {
            throw new InvalidArgumentException('France e-report legalEntityId must be a positive integer.');
        }

        ReportDataValidator::assertDate($this->periodStart, 'periodStart');
        ReportDataValidator::assertDate($this->periodEnd, 'periodEnd');

        if ($this->periodStart >= $this->periodEnd) {
            throw new InvalidArgumentException('France e-report periodEnd must be after periodStart.');
        }

        if ($this->issuedAt->toDateString() <= $this->periodEnd) {
            throw new InvalidArgumentException('France e-report issue date must be after periodEnd.');
        }

        if ($this->issuedAt->format('O') !== $this->issuedAt->setTimezone('Europe/Paris')->format('O')) {
            throw new InvalidArgumentException('France e-report issuedAt must use the Europe/Paris offset for its date.');
        }
    }

    public function period(): string
    {
        return $this->periodStart.' - '.$this->periodEnd;
    }
}
