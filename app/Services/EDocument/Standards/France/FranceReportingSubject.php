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

use App\DataMapper\FranceEReporting\FRReportEntryData;
use InvalidArgumentException;

final readonly class FranceReportingSubject
{
    public function __construct(
        public string $key,
        public ?FRReportEntryData $entry,
        public int $clientId,
        public ?int $invoiceId = null,
        public ?int $paymentId = null,
        public ?int $creditId = null,
    ) {
        if (trim($this->key) === '') {
            throw new InvalidArgumentException('France reporting subject key must not be empty.');
        }

        if ($this->clientId < 1) {
            throw new InvalidArgumentException('France reporting subject requires a persisted client.');
        }
    }

    public function hash(): string
    {
        return hash('sha256', json_encode(
            $this->entry?->toArray(),
            JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        ));
    }

    public function tombstone(): self
    {
        return new self(
            key: $this->key,
            entry: null,
            clientId: $this->clientId,
            invoiceId: $this->invoiceId,
            paymentId: $this->paymentId,
            creditId: $this->creditId,
        );
    }
}
