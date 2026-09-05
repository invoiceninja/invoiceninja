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

namespace App\Services\Tax;

use App\Models\Client;

class TaxService
{
    public function __construct(public Client $client) {}

    /**
     * Ask VIES about the client's VAT number and record the answer.
     *
     * A definite answer sets the flag either way. An answer of "not registered" now clears
     * `has_valid_vat_number`, where before there was no `else` at all: a number VIES
     * rejected left the flag at whatever it was, so a counterparty whose registration was
     * later cancelled kept a zero-rated, reverse-charge treatment indefinitely.
     *
     * No answer changes nothing. VIES reports "not registered" and "that member state's
     * system is down" through the same field, and the endpoint throttles by IP, so an
     * outage must never be read as a rejection.
     */
    public function validateVat(): self
    {
        $client_country_code = $this->client->shipping_country ? $this->client->shipping_country->iso_3166_2 : $this->client->country->iso_3166_2;

        $check = (new VatNumberCheck(
            $this->client->vat_number,
            $client_country_code,
            $this->client->company->settings->vat_number ?? null
        ))->run();

        if ($check->isUnavailable()) {
            nlog("VIES did not answer for {$this->client->vat_number} ({$check->getError()}) - the existing flag is left unchanged");

            return $this;
        }

        $valid = $check->isValid();

        $this->client->has_valid_vat_number = $valid;

        if ($valid) {
            if (!$this->client->name && strlen($check->getName()) > 2) {
                $this->client->name = $check->getName();
            }

            if (empty($this->client->private_notes) && strlen($check->getAddress()) > 2) {
                $this->client->private_notes = $check->getAddress();
            }
        }

        $this->client->saveQuietly();

        return $this;

    }

    public function initTaxProvider() {}
}
