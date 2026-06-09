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

namespace App\DataMapper\Billing;

use App\Casts\BillingContextCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

class BillingContext implements Castable
{
    public const VERSION = 1;

    /**
     * @param array{plan_price?: float|int|string|null, docuninja_price?: float|int|string|null} $pricing
     */
    public function __construct(
        public int $version = self::VERSION,
        public int $client_id = 0,
        public ?int $recurring_invoice_id = null,
        public array $pricing = [
            'plan_price' => 0,
            'docuninja_price' => 0,
        ],
        public bool $docuninja_pending_prune = false,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return BillingContextCast::class;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (int) ($data['version'] ?? self::VERSION),
            client_id: (int) ($data['client_id'] ?? 0),
            recurring_invoice_id: isset($data['recurring_invoice_id']) ? (int) $data['recurring_invoice_id'] : null,
            pricing: [
                'plan_price' => round((float) ($data['pricing']['plan_price'] ?? 0), 2),
                'docuninja_price' => round((float) ($data['pricing']['docuninja_price'] ?? 0), 2),
            ],
            docuninja_pending_prune: (bool) ($data['docuninja_pending_prune'] ?? false),
        );
    }
}
