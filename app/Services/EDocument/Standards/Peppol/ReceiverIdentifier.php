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

namespace App\Services\EDocument\Standards\Peppol;

// https://www.storecove.com/docs/#_receiver_identifiers_list

class ReceiverIdentifier
{
    public array $mappings = [
        'DE' => 'DE:VAT',

        // @todo: Check with Dave what other countries we support.
    ];

    public function __construct(
        public string $country,
    ) {
    }

    public function get(): ?string
    {
        return $this->mappings[$this->country] ?? null;
    }
}
