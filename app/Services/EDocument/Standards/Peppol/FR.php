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
use App\Models\Company;
use App\Models\Credit as NinjaCredit;
use App\Models\Invoice as NinjaInvoice;
use Carbon\CarbonImmutable;
use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\Storecove\Models\Credit as StorecoveCredit;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice as StorecoveInvoice;
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
    private const CTC_MANDATE_START_DATE = '2026-09-01';
    private const INVOICE_ITEM_TYPE_GOODS = 1;
    private const INVOICE_ITEM_TYPE_SERVICE = 2;

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
     * @return array<int, array{identifier: string, scheme: string, required: bool}>
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
                $identifiers[] = ['identifier' => $siren, 'scheme' => 'FR:SIRENE', 'required' => true];
                $identifiers[] = ['identifier' => $siren, 'scheme' => 'FR:CTC', 'required' => true];
            }
        }

        $siret = preg_replace("/[^0-9]/", "", $data['id_number'] ?? '');
        if ($siret !== '' && $validator->validFormat('FR:SIRET', $siret)) {
            $identifiers[] = ['identifier' => $siret, 'scheme' => 'FR:SIRET', 'required' => false];
        }

        return $identifiers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getIdentifierNetworkSpecifications(string $scheme): array
    {
        if (! in_array($scheme, ['FR:VAT', 'FR:SIRENE', 'FR:SIRET', 'FR:CTC'], true)) {
            return [];
        }

        $networks = [
            [
                'name' => 'peppol',
                'sub_networks' => ['main', 'france'],
            ],
        ];

        if ($scheme === 'FR:CTC') {
            $networks[] = [
                'name' => 'dgfip',
                'sub_networks' => ['main'],
                'annuaire' => [
                    'start_date' => $this->ctcStartDate(),
                ],
            ];
        }

        return $networks;
    }

    private function ctcStartDate(): string
    {
        $earliestStorecoveDate = CarbonImmutable::today('Europe/Paris')->addDays(2);
        $mandateStartDate = CarbonImmutable::parse(self::CTC_MANDATE_START_DATE, 'Europe/Paris');

        return $earliestStorecoveDate->max($mandateStartDate)->toDateString();
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

    public function decorateStorecoveDocument(
        StorecoveInvoice|StorecoveCredit $storecoveDocument,
        NinjaInvoice|NinjaCredit $sourceDocument,
    ): StorecoveInvoice|StorecoveCredit {
        if (! $this->isDgfipApplicable($sourceDocument->client, $sourceDocument->company)) {
            return $storecoveDocument;
        }

        return $storecoveDocument->setFrCadreDeFacturation(
            $this->inferCadreDeFacturation($sourceDocument),
        );
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

        if ($this->isDgfipApplicable($client, $client->company)) {
            return [['application' => 'fr-dgfip', 'settings' => ['enabled' => true]]];
        }

        return [];
    }

    private function isDgfipApplicable(Client $client, Company $company): bool
    {
        $sellerCountryIsFrance = $company->country()?->iso_3166_2 === 'FR';
        $sellerHasFrenchVatNexus = ! empty(data_get($company->tax_data, 'regions.EU.subregions.FR.vat_number'));
        $receiverCountryIsFrance = $client->country?->iso_3166_2 === 'FR';
        $classification = $client->classification ?? 'business';

        return $receiverCountryIsFrance
            && ($sellerCountryIsFrance || $sellerHasFrenchVatNexus)
            && ! in_array($classification, ['government', 'individual'], true);
    }

    /**
     * Infer only the standard goods, services, or mixed AFNOR context.
     * Fee and expense line origins are neutral; B1 is the fallback when the
     * document has no recognised supply line. Product and service signals are
     * treated as independent for M1 until Invoice Ninja captures ancillary supply.
     */
    private function inferCadreDeFacturation(NinjaInvoice|NinjaCredit $sourceDocument): string
    {
        $containsGoods = false;
        $containsServices = false;

        foreach ((array) $sourceDocument->line_items as $lineItem) {
            $lineType = (int) data_get($lineItem, 'type_id');

            if ($lineType === self::INVOICE_ITEM_TYPE_GOODS) {
                $containsGoods = true;
            } elseif ($lineType === self::INVOICE_ITEM_TYPE_SERVICE) {
                $containsServices = true;
            }

            if ($containsGoods && $containsServices) {
                return 'M1';
            }
        }

        return $containsServices ? 'S1' : 'B1';
    }

}
