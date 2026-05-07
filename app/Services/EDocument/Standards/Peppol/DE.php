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

use App\Services\EDocument\Gateway\MutatorUtil;

class DE extends BaseCountry
{
    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($classification === 'government') {
            $candidates = [];
            foreach (['routing_id', 'id_number', 'vat_number'] as $field) {
                // Preserve dashes — the Leitweg-ID format is Grobadresse-Feinadresse-Prüfziffer
                // and Storecove enforces the dashed form. Strip only whitespace.
                $id = preg_replace('/\s+/', '', $client->{$field} ?? '');
                if (strlen($id) >= 2) {
                    $candidates[] = ['scheme' => 'DE:LWID', 'id' => $id];
                }
            }
            return $candidates;
        }

        if ($classification === 'individual') {
            $id = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');
            return strlen($id) >= 2 ? [['scheme' => 'DE:STNR', 'id' => $id]] : [];
        }

        // Business: default VAT
        $id = preg_replace("/[^a-zA-Z0-9]/", "", $client->vat_number ?? '');
        return strlen($id) >= 2 ? [['scheme' => 'DE:VAT', 'id' => $id]] : [];
    }

    public function consumesBareRoutingId(?string $classification): bool
    {
        return ($classification ?? 'business') === 'government';
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        $mutator_util->setPaymentMeans(true);

        return $p_invoice;
    }
}
