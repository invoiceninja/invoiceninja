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
use App\Services\EDocument\Gateway\Storecove\Identifiers\StorecoveIdentifierValidator;

/**
 * France — Chorus Pro (B2G) + PEPPOL (B2B)
 *
 * B2G: All government invoices route to Chorus Pro via SIRET 0009:11000201100044.
 *       The final recipient's SIRET must be included as customerAssignedAccountId.
 * B2B: Route via FR:SIRENE (9-digit) or FR:SIRET (14-digit) based on client id_number.
 * B2C: Out of scope — France's e-invoicing mandate covers B2B/B2G only.
 */
class FR extends BaseCountry
{
    public function getCandidates(object $client, string $classification, object $router): array
    {
        if ($classification === 'government') {
            return [['scheme' => '0009', 'id' => '11000201100044']];
        }

        $idNumber = preg_replace("/[^a-zA-Z0-9]/", "", $client->id_number ?? '');

        if (strlen($idNumber) >= 9) {
            $scheme = strlen($idNumber) === 9 ? 'FR:SIRENE' : 'FR:SIRET';
            return [['scheme' => $scheme, 'id' => $idNumber]];
        }

        // Either id_number (SIREN/SIRET) or VAT is acceptable - when no
        // id_number is supplied, infer the SIREN from the trailing 9 digits
        // of the VAT number (FR + 2-char key + 9-digit SIREN).
        $vat = preg_replace("/[^0-9]/", "", $client->vat_number ?? '');
        if (strlen($vat) >= 9) {
            return [['scheme' => 'FR:SIRENE', 'id' => substr($vat, -9)]];
        }

        return [];
    }

    /**
     * The FR business routing identifier is the compound "FR:SIRENE or FR:SIRET"
     * (config column 3). Emitting that literal string as a publicIdentifier
     * scheme causes Storecove to reject the document ("scheme not found"), so
     * resolve it to a concrete scheme here.
     *
     * SIREN/SIRET is taken from id_number; when absent, the SIREN is inferred
     * from the trailing 9 digits of the VAT number (FR + 2-char key + SIREN),
     * mirroring the registration identifiers. The secondary FR:VAT pair is
     * appended exactly as the base implementation does.
     *
     * Government / individual are not compound and are handled correctly by
     * the base resolver, so they are delegated to the parent.
     */
    public function storecoveCustomerPartyPublicIdentifiers(object $client, object $invoice, StorecoveRouter $router): array
    {
        $classification = $client->classification ?? 'business';

        if ($classification !== 'business') {
            return parent::storecoveCustomerPartyPublicIdentifiers($client, $invoice, $router);
        }

        $idNumber = preg_replace("/[^0-9]/", "", $client->id_number ?? '');

        if (strlen($idNumber) >= 9) {
            $primary = [
                'scheme' => strlen($idNumber) === 9 ? 'FR:SIRENE' : 'FR:SIRET',
                'id' => $idNumber,
            ];
        } else {
            $vat = preg_replace("/[^0-9]/", "", $client->vat_number ?? '');

            if (strlen($vat) < 9) {
                return [];
            }

            $primary = ['scheme' => 'FR:SIRENE', 'id' => substr($vat, -9)];
        }

        $pairs = [$primary];

        $taxScheme = $router->resolveTaxScheme($client->country->iso_3166_2, $classification);
        if (!empty($taxScheme) && $taxScheme !== $primary['scheme']) {
            $vatRaw = trim($client->vat_number ?? '');
            if (strlen($vatRaw) > 1 && $this->identifierValidator()->matchesSchemeFormat($taxScheme, $vatRaw)) {
                $pairs[] = ['scheme' => $taxScheme, 'id' => $vatRaw];
            }
        }

        return $pairs;
    }

    /**
     * France registers on FR:VAT as the primary tax identifier, but B2B
     * discovery on PEPPOL is performed against FR:SIRENE (9-digit SIREN) or
     * FR:SIRET (14-digit). These must therefore also be published as PEPPOL
     * identifiers on the legal entity, otherwise counterparts cannot
     * discover us.
     *
     * The SIREN is inferred from the French VAT number (FR + 2-char key +
     * 9-digit SIREN), so the trailing 9 digits are the SIREN. The SIRET is
     * only published when an id_number is supplied and is a well-formed
     * 14-digit FR:SIRET.
     *
     * @param  array{classification?: string, vat_number?: string, id_number?: string}  $data
     * @return array<int, array{identifier: string, scheme: string}>
     */
    public function getAdditionalIdentifiers(array $data): array
    {
        if (($data['classification'] ?? 'business') === 'individual') {
            return [];
        }

        $identifiers = [];
        $validator = new StorecoveIdentifierValidator();

        $vat = preg_replace("/[^0-9]/", "", $data['vat_number'] ?? '');
        if (strlen($vat) >= 9) {
            $siren = substr($vat, -9);

            if ($validator->validFormat('FR:SIRENE', $siren)) {
                $identifiers[] = ['identifier' => $siren, 'scheme' => 'FR:SIRENE'];
            }
        }

        $siret = preg_replace("/[^0-9]/", "", $data['id_number'] ?? '');
        if ($siret !== '' && $validator->validFormat('FR:SIRET', $siret)) {
            $identifiers[] = ['identifier' => $siret, 'scheme' => 'FR:SIRET'];
        }

        return $identifiers;
    }

    public function senderMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }

    /**
     * Receiver mutations for when the client is in France but the sender is not.
     */
    public function receiverMutations(
        mixed $p_invoice,
        mixed $invoice,
        MutatorUtil $mutator_util,
    ): mixed {

        return $p_invoice;
    }
    
    /**
     * getNetworkOverrides
     *
     * @return array
     */
    public function getNetworkOverrides(?Client $client = null): array
    {
        if (! $client) {
            return [];
        }

        /**
         * Casers to handle:
         * 
         * 1. FR => FR (Business)
         * 2. WORLD => FR WITH FR VAT Number configured.
         * 
         */
        $sellerWithFRVAT = $client->company->country()->iso_3166_2 != "FR" && empty(data_get($client->company->tax_data, 'regions.EU.subregions.FR.vat_number', ''));
        $receiverCountryIsFR = $client->country?->iso_3166_2 == "FR";
        $classification = $client->classification ?? 'business';

        /** French Tax Nexus + Not Government Or Individual */
        if ($receiverCountryIsFR && !$sellerWithFRVAT && !in_array($classification, ['government', 'individual'])) {
            return [['application' => 'fr-dgfip', 'settings' => ['enabled' => true]]];
        }

        return [];
    }

}
