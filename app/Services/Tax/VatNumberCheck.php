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

use Illuminate\Support\Facades\Http;

class VatNumberCheck
{
    private array $response = [];

    public function __construct(protected ?string $vat_number, protected string $country_code) {}

    /**
     * @throws \RuntimeException when VIES gives no verdict, which the CheckVat job retries
     */
    public function run()
    {
        if (strlen($this->vat_number ?? '') == 0) {
            $this->response = ['valid' => false, 'error' => 'No VAT number provided'];
            return $this;
        } else {
            return $this->checkvat_number();
        }
    }

    private function checkvat_number(): self
    {
        // Prefer the VAT registration prefix, falling back to the supplied country.
        $country_code = strtoupper($this->country_code);
        $vat_number = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $this->vat_number));

        if (preg_match('/^(AT|BE|BG|CY|CZ|DE|DK|EE|EL|ES|FI|FR|GR|HR|HU|IE|IT|LT|LU|LV|MT|NL|PL|PT|RO|SE|SI|SK|XI)/', $vat_number, $matches)) {
            $country_code = $matches[1];
            $vat_number = substr($vat_number, 2);
        }

        // VIES files Greece as EL.
        $country_code = $country_code == 'GR' ? 'EL' : $country_code;

        $response = Http::timeout(20)->post('https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number', [
            'countryCode' => $country_code,
            'vatNumber' => $vat_number,
        ]);

        // A number that is not registered comes back as "valid": false. When VIES cannot answer
        // (a member state is down, the service is busy, or it is rate limiting the caller) there
        // is no "valid" at all, and that must not be read as a "no".
        if (!is_bool($response->json('valid'))) {
            throw new \RuntimeException('VIES returned no verdict: '.($response->json('errorWrappers.0.error') ?? 'HTTP '.$response->status()));
        }

        if ($response->json('valid')) {

            $this->response = [
                'valid' => true,
                'name' => $response->json('name'),
                'address' => $response->json('address'),
            ];
        } else {
            $this->response = ['valid' => false];
        }

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function isValid(): bool
    {
        return $this->response['valid'];
    }

    public function getName()
    {
        return $this->response['name'] ?? '';
    }

    public function getAddress()
    {
        return $this->response['address'] ?? '';
    }
}
