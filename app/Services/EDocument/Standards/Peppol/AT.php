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
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;

class AT extends BaseCountry
{
    /**
     * AT government routing uses the fixed Storecove endpoint AT:GOV:b.
     * Validation passes whenever the client has an id_number, which is
     * required for customerAssignedAccountIdValue on the supplier party.
     */
    public function getCandidates(object $client, string $classification, object $router): array
    {
        /** @var StorecoveRouter $router */
        if ($classification === 'government') {
            $id = trim($client->id_number ?? '');
            return strlen($id) >= 1 ? [['scheme' => 'AT:GOV', 'id' => 'b']] : [];
        }

        return parent::getCandidates($client, $classification, $router);
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }
}
