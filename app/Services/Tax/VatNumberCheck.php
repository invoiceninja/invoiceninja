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

/**
 * Checks a VAT number against the European Commission's VIES service.
 *
 * Uses the REST API rather than SOAP, so no PHP extension is required.
 *
 * The verdict is three-valued on purpose. `true` and `false` are facts about the
 * counterparty; `null` means VIES did not answer - the service was unreachable, timed out,
 * or the member state's own system was down. VIES reports "not registered" and "I could not
 * ask" through the same field, and only the first may be allowed to change a tax treatment.
 * The endpoint also throttles by IP and answers a block with an HTML page rather than JSON,
 * which is likewise a `null` and not a "no".
 *
 * The POST form is used rather than GET because only the POST, which names the enquirer,
 * returns a `requestIdentifier` - the consultation number, which is the evidence in several
 * member states that the check was made.
 */
class VatNumberCheck
{
    private const ENDPOINT = 'https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number';

    private const TIMEOUT = 20;

    private const ATTEMPTS = 4;

    /** Codes that mean the service could not answer, not that the number is bad. */
    private const UNAVAILABLE = [
        'MS_UNAVAILABLE', 'MS_MAX_CONCURRENT_REQ', 'SERVICE_UNAVAILABLE',
        'TIMEOUT', 'GLOBAL_MAX_CONCURRENT_REQ', 'SERVER_BUSY',
    ];

    private array $response = [];

    public function __construct(protected ?string $vat_number, protected string $country_code, protected ?string $requester_vat = null) {}

    public function run(): self
    {
        [$country, $number] = $this->split($this->vat_number, $this->country_code);

        if (! $country || ! $number) {
            $this->response = ['valid' => null, 'error' => 'No VAT number provided'];

            return $this;
        }

        $payload = ['countryCode' => $country, 'vatNumber' => $number];

        [$rq_country, $rq_number] = $this->split($this->requester_vat, '');
        if ($rq_country && $rq_number) {
            $payload['requesterMemberStateCode'] = $rq_country;
            $payload['requesterNumber'] = $rq_number;
        }

        $answer = null;
        $error = '';

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            if ($attempt) {
                usleep((int) (1_500_000 * $attempt));
            }

            try {
                $r = Http::timeout(self::TIMEOUT)->asJson()->post(self::ENDPOINT, $payload);

                if ($r->failed()) {
                    $error = 'HTTP '.$r->status();

                    continue;
                }

                $body = $r->json();
            } catch (\Throwable $e) {
                $error = class_basename($e).': '.$e->getMessage();

                continue;
            }

            $code = $this->errorOf($body);

            if (in_array($code, self::UNAVAILABLE, true)) {
                $error = $code;

                continue;
            }

            $answer = $body;
            $error = $code;
            break;
        }

        if ($answer === null) {
            $this->response = ['valid' => null, 'error' => $error ?: 'VIES did not answer'];

            return $this;
        }

        if (! array_key_exists('valid', $answer) || $answer['valid'] === null || ($answer['actionSucceed'] ?? true) === false) {
            $this->response = ['valid' => null, 'error' => $error ?: 'VIES returned no verdict'];

            return $this;
        }

        $this->response = [
            'valid' => (bool) $answer['valid'],
            'request_id' => $answer['requestIdentifier'] ?? '',
            'name' => trim($answer['name'] ?? ''),
            'address' => trim(preg_replace('/\s+/', ' ', $answer['address'] ?? '')),
            'error' => ((bool) $answer['valid'] || $error === 'INVALID') ? '' : $error,
        ];

        return $this;
    }

    /** 'ESB12345678' becomes ['ES', 'B12345678']. Greece files as EL, not GR. */
    private function split(?string $vat, string $fallback_country): array
    {
        $vat = strtoupper(str_replace([' ', '-'], '', trim($vat ?? '')));

        if ($vat === '') {
            return [null, null];
        }

        if (strlen($vat) > 2 && ctype_alpha(substr($vat, 0, 2))) {
            $code = substr($vat, 0, 2);

            return [$code === 'GR' ? 'EL' : $code, substr($vat, 2)];
        }

        $code = strtoupper(trim($fallback_country));

        return [$code === 'GR' ? 'EL' : ($code ?: null), $vat];
    }

    /** The error code, wherever this particular answer put it. */
    private function errorOf(mixed $body): string
    {
        if (! is_array($body)) {
            return '';
        }

        $err = strtoupper(trim((string) ($body['userError'] ?? '')));

        if ($err !== '') {
            return $err;
        }

        foreach (($body['errorWrappers'] ?? []) as $wrapper) {
            $code = strtoupper(trim((string) ($wrapper['error'] ?? '')));

            if ($code !== '') {
                return $code;
            }
        }

        return '';
    }

    public function getResponse(): array
    {
        return $this->response;
    }

    /** True only when VIES said so. A service outage is not a "no". */
    public function isValid(): bool
    {
        return ($this->response['valid'] ?? null) === true;
    }

    /** True only when VIES said the number is not a current registration. */
    public function isInvalid(): bool
    {
        return ($this->response['valid'] ?? null) === false;
    }

    /** True when VIES did not answer at all - the case that must change nothing. */
    public function isUnavailable(): bool
    {
        return ($this->response['valid'] ?? null) === null;
    }

    public function getRequestIdentifier(): string
    {
        return $this->response['request_id'] ?? '';
    }

    public function getName(): string
    {
        return $this->response['name'] ?? '';
    }

    public function getAddress(): string
    {
        return $this->response['address'] ?? '';
    }

    public function getError(): string
    {
        return $this->response['error'] ?? '';
    }
}
