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
use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Support\GlnIdentifier;

/**
 * Italy (SDI / Peppol). Partita IVA uses scheme label {@see Storecove}'s IT:IVA (VAT tax identifier).
 */
class IT extends BaseCountry
{
    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($classification === 'individual') {
            return $this->italyIndividualCandidates($client);
        }

        // B2B/B2G: Codice Destinatario first for discovery, then Partita IVA (IT:IVA).
        $candidates = [];
        $routingId = trim($client->routing_id ?? '');
        if ($routingId !== '' && !GlnIdentifier::isValid($routingId)) {
            $cuuo = preg_replace("/[^a-zA-Z0-9]/", "", $routingId);
            if (strlen($cuuo) >= 2) {
                $candidates[] = ['scheme' => 'IT:CUUO', 'id' => $cuuo];
            }
        }
        $vat = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        if (strlen($vat) >= 2) {
            $candidates[] = ['scheme' => 'IT:IVA', 'id' => $vat];
        }

        return $candidates;
    }

    /**
     * @return array<int, array{scheme: string, id: string}>
     */
    private function italyIndividualCandidates(object $client): array
    {
        $candidates = [];
        $routingId = trim($client->routing_id ?? '');
        if ($routingId !== '' && !GlnIdentifier::isValid($routingId)) {
            $cuuo = preg_replace("/[^a-zA-Z0-9]/", "", $routingId);
            if (strlen($cuuo) >= 2) {
                $candidates[] = ['scheme' => 'IT:CUUO', 'id' => $cuuo];
            }
        }
        $cf = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        if (strlen($cf) >= 2) {
            $candidates[] = ['scheme' => 'IT:CF', 'id' => $cf];
        }

        return $candidates;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }

    /**
     * Receiver mutations for when the client is in Italy but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }

    public function consumesBareRoutingId(?string $classification): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * B2B/B2G: Partita IVA (IT:IVA) + Codice Destinatario (IT:CUUO), unless GLN routing applies.
     * Domestic consumer (sender IT): IT:CF + IT:CUUO.
     * Foreign consumer: IT:CF; primary contact email must not be a PEC address (SDI eReporting + email).
     */
    public function validateReceiverRoutingIdentifiers(Client $client, string $classification, StorecoveRouter $router, ?string $senderCountryCode = null): array
    {
        $routingRaw = trim($client->routing_id ?? '');

        if ($routingRaw !== '' && GlnIdentifier::isValid($routingRaw)) {
            return [];
        }

        if (in_array($classification, ['business', 'government'], true)) {
            return $this->validateItalyBusinessGovernment($client);
        }

        if ($classification === 'individual') {
            return $senderCountryCode === 'IT'
                ? $this->validateItalyDomesticConsumer($client)
                : $this->validateItalyForeignConsumer($client);
        }

        return parent::validateReceiverRoutingIdentifiers($client, $classification, $router, $senderCountryCode);
    }

    /**
     * @return array<int, array{field: string, label: string}>
     */
    private function validateItalyBusinessGovernment(Client $client): array
    {
        $routingRaw = trim($client->routing_id ?? '');
        $vatClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        $cuuoClean = preg_replace("/[^a-zA-Z0-9]/", "", $routingRaw);

        $errors = [];

        $vatOk = strlen($vatClean) >= 2 && $this->identifierValidator()->validFormat('IT:IVA', $vatClean);
        $cuuoOk = strlen($cuuoClean) >= 2 && $this->identifierValidator()->validFormat('IT:CUUO', $cuuoClean);

        if (!$vatOk) {
            $example = $this->identifierValidator()->formatExample('IT:IVA');
            $errors[] = [
                'field' => 'vat_number',
                'label' => $example
                    ? "Italian Partita IVA / VAT ID (IT:IVA) is required — e.g. {$example}."
                    : 'Italian Partita IVA / VAT ID (IT:IVA) is required for SDI delivery to business or government recipients.',
            ];
        }

        if (!$cuuoOk) {
            $example = $this->identifierValidator()->formatExample('IT:CUUO');
            $errors[] = [
                'field' => 'routing_id',
                'label' => $example
                    ? "Codice Destinatario (IT:CUUO) is required in routing_id — e.g. {$example}."
                    : 'Codice Destinatario (IT:CUUO) is required in routing_id for SDI routing.',
            ];
        }

        return $errors;
    }

    /**
     * @return array<int, array{field: string, label: string}>
     */
    private function validateItalyDomesticConsumer(Client $client): array
    {
        $routingRaw = trim($client->routing_id ?? '');
        $cfClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        $cuuoClean = preg_replace("/[^a-zA-Z0-9]/", "", $routingRaw);

        $errors = [];

        $cfOk = strlen($cfClean) >= 2 && $this->identifierValidator()->validFormat('IT:CF', $cfClean);
        $cuuoOk = strlen($cuuoClean) >= 2 && $this->identifierValidator()->validFormat('IT:CUUO', $cuuoClean);

        if (!$cfOk) {
            $example = $this->identifierValidator()->formatExample('IT:CF');
            $errors[] = [
                'field' => 'id_number',
                'label' => $example
                    ? "Codice Fiscale (IT:CF) is required — e.g. {$example}."
                    : 'Codice Fiscale (IT:CF) is required for domestic consumer recipients.',
            ];
        }

        if (!$cuuoOk) {
            $example = $this->identifierValidator()->formatExample('IT:CUUO');
            $errors[] = [
                'field' => 'routing_id',
                'label' => $example
                    ? "Codice Destinatario (IT:CUUO) is required in routing_id — e.g. {$example}."
                    : 'Codice Destinatario (IT:CUUO) is required in routing_id for domestic consumer SDI routing.',
            ];
        }

        return $errors;
    }

    /**
     * Non-IT sender → IT consumer: CF required; email must not be PEC (SDI rules).
     *
     * @return array<int, array{field: string, label: string}>
     */
    private function validateItalyForeignConsumer(Client $client): array
    {
        $cfClean = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
        $errors = [];

        if (strlen($cfClean) < 2 || !$this->identifierValidator()->validFormat('IT:CF', $cfClean)) {
            $example = $this->identifierValidator()->formatExample('IT:CF');
            $errors[] = [
                'field' => 'id_number',
                'label' => $example
                    ? "Codice Fiscale (IT:CF) is required — e.g. {$example}."
                    : 'Codice Fiscale (IT:CF) is required for Italian consumer recipients.',
            ];
        }

        $email = $client->present()->email();
        if ($email !== 'No Email Set' && $email !== '' && self::isItalianPecEmail($email)) {
            $errors[] = [
                'field' => 'email',
                'label' => 'Italian SDI eReporting via email cannot use a PEC (certified email) address. Use an ordinary email contact.',
            ];
        }

        return $errors;
    }

    /**
     * Certified / PEC-style mailbox domains used for Italian electronic invoicing (must not be used for foreign-sender B2C email routing per SDI rules).
     */
    public static function isItalianPecEmail(string $email): bool
    {
        $lower = strtolower(trim($email));

        if ($lower === '' || str_contains($lower, 'no email')) {
            return false;
        }

        if (!str_contains($lower, '@')) {
            return false;
        }

        return str_ends_with($lower, '@pec.it')
            || (bool) preg_match('/@[^@\s]+\.pec\.it$/', $lower)
            || str_ends_with($lower, '@legalmail.it')
            || (bool) preg_match('/@[^@\s]+\.legalmail\.it$/', $lower);
    }
}
