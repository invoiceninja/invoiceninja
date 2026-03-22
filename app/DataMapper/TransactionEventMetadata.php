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

namespace App\DataMapper;

use App\Casts\TransactionEventMetadataCast;
use App\DataMapper\TaxReport\TaxReport;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * TransactionEventMetadata.
 */
class TransactionEventMetadata implements Castable
{
    public TaxReport $tax_report;

    public function __construct(array $attributes = [])
    {
        $this->tax_report = isset($attributes['tax_report'])
            ? new TaxReport($attributes['tax_report'])
            : new TaxReport([]);
    }

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return TransactionEventMetadataCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    public function toArray(): array
    {
        return [
            'tax_report' => $this->tax_report->toArray(),
        ];
    }
}
