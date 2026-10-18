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

namespace App\Services\EDocument\Standards\Peppol;

use App\Models\Client;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Support\GlnIdentifier;

/**
 * Finland (Finvoice / Storecove): operator id (FI:OPID) in routing_id, OVT in id_number.
 */
class FI extends BaseCountry
{
    public function getCandidates(object $client, string $classification, object $router): array
    {
        if (in_array($classification, ['business', 'government'], true)) {
            return [];
        }

        return parent::getCandidates($client, $classification, $router);
    }

    public function consumesBareRoutingId(?string $classification): bool
    {
        return in_array($classification, ['business', 'government'], true);
    }

    /**
     * {@inheritdoc}
     *
     * B2B/B2G: Finvoice routing requires FI:OPID (routing_id) with FI:OVT (id_number) and FI:VAT.
     */
    public function validateReceiverRoutingIdentifiers(Client $client, string $classification, StorecoveRouter $router, ?string $senderCountryCode = null): array
    {
        $routingRaw = trim($client->routing_id ?? '');

        if ($routingRaw !== '' && GlnIdentifier::isValid($routingRaw)) {
            return [];
        }

        if (!in_array($classification, ['business', 'government'], true)) {
            return parent::validateReceiverRoutingIdentifiers($client, $classification, $router, $senderCountryCode);
        }

        return $this->validateFinlandBusinessGovernment($client);
    }

    /**
     * Peppol / EAS endpoint form for OVT — not a Finvoice operator id.
     */
    public static function isOvtEndpointRoutingId(string $routingId): bool
    {
        $routingId = trim($routingId);

        if (!str_contains($routingId, ':')) {
            return false;
        }

        [$scheme] = explode(':', $routingId, 2);

        return in_array($scheme, ['0216', '0037', 'FI:OVT'], true);
    }

    /**
     * {@inheritdoc}
     *
     * B2B/B2G: Peppol buyer EndpointID is OVT (id_number). routing_id holds FI:OPID for Storecove only.
     */
    public function resolveClientEndpointScheme(Client $client, StorecoveRouter $router): array
    {
        $routingId = trim((string) ($client->routing_id ?? ''));

        if ($gln = $this->glnEndpointFromIdentifier($routingId)) {
            return $gln;
        }

        $classification = $client->classification ?? 'business';

        if (!in_array($classification, ['business', 'government'], true)) {
            return parent::resolveClientEndpointScheme($client, $router);
        }

        $ovtClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');

        if (strlen($ovtClean) >= 2 && $this->identifierValidator()->matchesSchemeFormat('FI:OVT', $ovtClean)) {
            return [
                'scheme' => $this->schemeResolver()->iso6523('FI:OVT'),
                'id' => $ovtClean,
            ];
        }

        return [
            'scheme' => '',
            'id' => '',
        ];
    }

    /**
     * @return array<int, array{field: string, label: string}>
     */
    private function validateFinlandBusinessGovernment(Client $client): array
    {
        $routingRaw = trim($client->routing_id ?? '');
        $ovtClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        $vatRaw = trim($client->vat_number ?? '');
        $opidClean = preg_replace("/[^a-zA-Z0-9]/", "", $routingRaw);

        $errors = [];

        if ($routingRaw !== '' && self::isOvtEndpointRoutingId($routingRaw)) {
            $errors[] = [
                'field' => 'routing_id',
                'label' => 'Finvoice operator id (FI:OPID) belongs in routing_id; put the OVT identifier in ID Number.',
            ];

            return $errors;
        }

        $ovtOk = strlen($ovtClean) >= 2 && $this->identifierValidator()->validFormat('FI:OVT', $ovtClean);
        $opidOk = strlen($opidClean) >= 2
            && !str_contains($routingRaw, ':')
            && $this->identifierValidator()->validFormat('FI:OPID', $opidClean);
        $vatOk = strlen($vatRaw) >= 2 && $this->identifierValidator()->validFormat('FI:VAT', $vatRaw, checkDigit: false);

        if (!$ovtOk) {
            $example = $this->identifierValidator()->formatExample('FI:OVT');
            $errors[] = [
                'field' => 'id_number',
                'label' => $example
                    ? "Finnish OVT (FI:OVT) is required in ID Number — e.g. {$example}."
                    : 'Finnish OVT (FI:OVT) is required in ID Number for Finvoice delivery.',
            ];
        }

        if (!$opidOk) {
            $example = $this->identifierValidator()->formatExample('FI:OPID');
            $errors[] = [
                'field' => 'routing_id',
                'label' => $example
                    ? "Finvoice operator id (FI:OPID) is required in routing_id — e.g. {$example}."
                    : 'Finvoice operator id (FI:OPID) is required in routing_id.',
            ];
        }

        if (!$vatOk) {
            $example = $this->identifierValidator()->formatExample('FI:VAT');
            $errors[] = [
                'field' => 'vat_number',
                'label' => $example
                    ? "Finnish VAT (FI:VAT) is required — e.g. {$example}."
                    : 'Finnish VAT (FI:VAT) is required for Finvoice delivery.',
            ];
        }

        return $errors;
    }
}
