<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www/elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

/**
 * Cancellation value object for invoice backup data.
 */
class Cancellation
{
    public function __construct(
        public float $adjustment = 0, // The cancellation adjustment amount
        public int $status_id = 0 //The status id of the invoice when it was cancelled
    ) {}

    public static function fromArray(array|object $data): self
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        return new self(
            adjustment: $data['adjustment'] ?? 0,
            status_id: $data['status_id'] ?? 0
        );
    }
}
