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

namespace App\Services\EDocument\Gateway\Storecove;

class StorecoveC5
{
    public function __construct(private Storecove $storecove)
    {
    }

    /**
     * Request a new C5 Email Activation with IRAS.
     *
     * Storecove will email the signer a link to authorize
     * the SG:UEN identifier with Singapore IRAS.
     *
     * @param int $legal_entity_id
     * @param string $identifier The UEN identifier
     * @param string $name Name of the signer
     * @param string $email Email of the signer
     * @return array|\Illuminate\Http\Client\Response
     */
    public function activate(int $legal_entity_id, string $identifier, string $name, string $email): array|\Illuminate\Http\Client\Response
    {
        $payload = [
            'scheme' => 'SG:UEN',
            'identifier' => $identifier,
            'superscheme' => 'iso6523-actorid-upis',
            'name' => $name,
            'email' => $email,
        ];

        $r = $this->storecove->httpClient(
            "legal_entities/{$legal_entity_id}/c5/iras/email/activate",
            (HttpVerb::POST)->value,
            $payload,
        );

        if ($r->successful()) {
            return $r->json() ?? [];
        }

        return $r;
    }

    /**
     * Request a C5 Email Deactivation with IRAS.
     *
     * @param int $legal_entity_id
     * @param string $identifier The UEN identifier
     * @param string $name Name of the signer
     * @param string $email Email of the signer
     * @return array|\Illuminate\Http\Client\Response
     */
    public function deactivate(int $legal_entity_id, string $identifier, string $name, string $email): array|\Illuminate\Http\Client\Response
    {
        $payload = [
            'scheme' => 'SG:UEN',
            'identifier' => $identifier,
            'superscheme' => 'iso6523-actorid-upis',
            'name' => $name,
            'email' => $email,
        ];

        $r = $this->storecove->httpClient(
            "legal_entities/{$legal_entity_id}/c5/iras/email/deactivate",
            (HttpVerb::POST)->value,
            $payload,
        );

        if ($r->successful()) {
            return $r->json() ?? [];
        }

        return $r;
    }

    /**
     * Cancel a pending C5 Email request with IRAS.
     *
     * @param int $legal_entity_id
     * @param string $identifier The UEN identifier
     * @return array|\Illuminate\Http\Client\Response
     */
    public function cancel(int $legal_entity_id, string $identifier): array|\Illuminate\Http\Client\Response
    {
        $payload = [
            'scheme' => 'SG:UEN',
            'identifier' => $identifier,
            'superscheme' => 'iso6523-actorid-upis',
        ];

        $r = $this->storecove->httpClient(
            "legal_entities/{$legal_entity_id}/c5/iras/email/cancel",
            (HttpVerb::PUT)->value,
            $payload,
        );

        if ($r->successful()) {
            return $r->json() ?? [];
        }

        return $r;
    }
}
