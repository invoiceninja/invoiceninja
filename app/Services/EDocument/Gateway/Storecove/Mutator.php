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

use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\MutatorInterface;
use App\Services\EDocument\Standards\Peppol\CountryFactory;

/**
 * Storecove-specific Mutator for e-invoicing via the Storecove API.
 *
 * Transforms a Peppol Invoice/CreditNote model into a Storecove-ready payload by:
 *  - Applying country-specific sender/receiver mutations (delegated to CountryFactory handlers)
 *  - Resolving client routing (eIdentifiers, email fallback, Peppol/SDI/Svefaktura networks)
 *  - Building the `storecove_meta` array that wraps the document for the Storecove send API
 *
 * Typical pipeline (orchestrated by StorecoveAdapter):
 *   $mutator->setInvoice()->setPeppol()->setClientSettings()->setCompanySettings()
 *           ->senderSpecificLevelMutators()
 *           ->receiverSpecificLevelMutators()
 *           ->setClientRoutingCode()
 *
 * The resulting Peppol model (getPeppol()) and routing metadata (getStorecoveMeta())
 * are then serialised and POSTed to Storecove.
 *
 * @see \App\Services\EDocument\Gateway\Storecove\StorecoveAdapter  Orchestrates the full send flow
 * @see \App\Services\EDocument\Standards\Peppol\CountryFactory      Dispatches country-specific mutations
 * @see \App\Services\EDocument\Gateway\Storecove\StorecoveRouter    Resolves routing scheme codes per country
 */
class Mutator implements MutatorInterface
{
    /** @var \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote The Peppol document being mutated */
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice;

    /** @var ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice Peppol settings configured at the client level (e_invoice field on the client) */
    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    /** @var ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice Peppol settings configured at the company level (e_invoice field on the company) */
    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    /** @var \App\Models\Invoice|\App\Models\Credit The Invoice Ninja invoice/credit being sent */
    private $invoice;

    /**
     * Storecove API envelope metadata (routing, emails, network config).
     * Built up incrementally by setClientRoutingCode() and its helpers,
     * then read by StorecoveAdapter when constructing the final API payload.
     *
     * @var array{routing?: array{eIdentifiers?: array, emails?: string[], networks?: array}}
     */
    private array $storecove_meta = [];

    /**
     * When set, country handlers should use this VAT number instead of the
     * company's own vat_number. Used for tax-representative / fiscal-representative scenarios.
     */
    private string $override_vat_number = '';

    /** @var MutatorUtil Shared helpers for setting payment means, customer IDs, and resolving cascading settings */
    private MutatorUtil $mutator_util;

    public function __construct(public Storecove $storecove)
    {
        $this->mutator_util = new MutatorUtil($this);
    }

    /**
     * Set the Invoice Ninja invoice or credit note to be sent.
     *
     * @param  \App\Models\Invoice|\App\Models\Credit $invoice
     * @return self
     */
    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    /**
     * Set the Peppol UBL document model that will be mutated and serialised.
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote $p_invoice
     * @return self
     */
    public function setPeppol($p_invoice): self
    {
        $this->p_invoice = $p_invoice;
        return $this;
    }

    /**
     * Get the current Peppol UBL document model (after any mutations applied).
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|\InvoiceNinja\EInvoice\Models\Peppol\CreditNote
     */
    public function getPeppol(): mixed
    {
        return $this->p_invoice;
    }

    /**
     * Set the Peppol settings stored on the client (client.e_invoice).
     * These take precedence over company-level settings when resolving properties via MutatorUtil::getSetting().
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice|null $client_settings
     * @return self
     */
    public function setClientSettings($client_settings): self
    {
        $this->_client_settings = $client_settings;
        return $this;
    }

    /**
     * Set the Peppol settings stored on the company (company.e_invoice).
     * Acts as the lowest-priority fallback in the settings cascade (invoice -> client -> company).
     *
     * @param  \InvoiceNinja\EInvoice\Models\Peppol\Invoice|null $company_settings
     * @return self
     */
    public function setCompanySettings($company_settings): self
    {
        $this->_company_settings = $company_settings;
        return $this;
    }

    /**
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|null
     */
    public function getClientSettings(): mixed
    {
        return $this->_client_settings;
    }

    /**
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice|null
     */
    public function getCompanySettings(): mixed
    {
        return $this->_company_settings;
    }

    /**
     * @return \App\Models\Invoice|\App\Models\Credit
     */
    public function getInvoice(): mixed
    {
        return $this->invoice;
    }

    /**
     * Override the company VAT number for fiscal-representative scenarios.
     * Country handlers check this before falling back to company->settings->vat_number.
     */
    public function setOverrideVatNumber(string $vat_number): self
    {
        $this->override_vat_number = $vat_number;
        return $this;
    }

    public function getOverrideVatNumber(): string
    {
        return $this->override_vat_number;
    }

    /**
     * Resolve a Peppol property using the three-tier cascade: invoice -> client -> company.
     * Delegates to MutatorUtil which uses PropertyResolver under the hood.
     *
     * @param  string $property_path  Dot-notation path e.g. 'Invoice.PaymentMeans'
     * @return mixed  The resolved value, or null if not set at any level
     */
    public function getSetting(string $property_path): mixed
    {
        return $this->mutator_util->getSetting($property_path);
    }

    /**
     * Apply country-specific mutations for the sender (company) side.
     *
     * Resolves the company's country code, looks up a handler via CountryFactory,
     * and delegates to handler->senderMutations(). Handlers may modify the Peppol
     * document (e.g. adding AccountingSupplierParty tax schemes, fiscal identifiers)
     * and/or inject Storecove-specific metadata.
     *
     * @return self
     */
    public function senderSpecificLevelMutators(): self
    {
        $countryCode = $this->invoice->company->country()->iso_3166_2;

        $handler = CountryFactory::make($countryCode);
        $result = $handler->senderMutations(
            $this->p_invoice,
            $this->invoice,
            $this->mutator_util,
            $this->storecove_meta
        );

        $this->p_invoice = $result['p_invoice'];
        $this->storecove_meta = $result['storecove_meta'];

        return $this;
    }

    /**
     * Apply country-specific mutations for the receiver (client) side.
     *
     * Resolves the client's country code, looks up a handler via CountryFactory,
     * and delegates to handler->receiverMutations(). Handlers may modify the Peppol
     * document (e.g. adding buyer tax registration, electronic address schemes)
     * and/or inject Storecove-specific metadata.
     *
     * @return self
     */
    public function receiverSpecificLevelMutators(): self
    {
        $countryCode = $this->invoice->client->country->iso_3166_2;

        $handler = CountryFactory::make($countryCode);
        $result = $handler->receiverMutations(
            $this->p_invoice,
            $this->invoice,
            $this->mutator_util,
            $this->storecove_meta
        );

        $this->p_invoice = $result['p_invoice'];
        $this->storecove_meta = $result['storecove_meta'];

        return $this;
    }

    /////////////// Storecove Helpers ///////////////

    /**
     * Get the client's primary email for email-based delivery (individual/B2C recipients).
     */
    private function getIndividualEmailRoute(): string
    {
        return $this->invoice->client->present()->email();
    }

    /**
     * Fallback: extract a sanitised alphanumeric identifier from the client.
     * For individuals, prefers id_number; otherwise uses vat_number.
     *
     * @param  string $code  The resolved routing scheme code (unused but kept for signature consistency)
     */
    private function getClientPublicIdentifier(string $code): string
    {
        if ($this->invoice->client->classification == 'individual' && strlen($this->invoice->client->id_number ?? '') > 2) {
            return preg_replace("/[^a-zA-Z0-9]/", "", $this->invoice->client->id_number ?? '');
        }

        return preg_replace("/[^a-zA-Z0-9]/", "", $this->invoice->client->vat_number ?? '');
    }

    /**
     * Resolve and set the Storecove routing metadata for the receiving client.
     *
     * This is the main routing orchestrator. It determines how Storecove should
     * deliver the document to the recipient by building the `storecove_meta.routing`
     * payload. The resolution order is:
     *
     *  1. If the client has no vat_number/id_number and is an individual -> email routing
     *  2. If the client has an explicit routing_id in "scheme:id" format -> use directly (after proxy discovery)
     *  3. Otherwise, resolve the scheme via StorecoveRouter based on country + classification,
     *     pick the correct identifier (vat_number, id_number, or routing_id depending on scheme),
     *     apply country-specific formatting (DK:DIGST prefix, SG:UEN prefix, BE fallback),
     *     and build the eIdentifiers routing array
     *
     * Also enables the Svefaktura network for Swedish recipients.
     *
     * @return self
     */
    public function setClientRoutingCode(): self
    {

        if (strlen($this->invoice->client->vat_number ?? '') < 2 && strlen($this->invoice->client->id_number ?? '') < 2) {
            if ($this->invoice->client->classification == 'individual') {
                return $this->setEmailRouting($this->getIndividualEmailRoute());
            }
            return $this;
        }

        if (stripos($this->invoice->client->routing_id ?? '', ":") !== false) {

            $parts = explode(":", $this->invoice->client->routing_id);

            if (count($parts) == 2) {
                $scheme = $parts[0];
                $id = $parts[1];

                if ($this->proxyDiscovery($id, $scheme)) {
                    $this->setStorecoveMeta($this->buildRouting([
                        ["scheme" => $scheme, "id" => $id],
                    ]));

                    $this->setSvefakturaNetwork();

                    return $this;
                }
            }

        }

        $code = $this->getClientRoutingCode();

        if ($code === 'Email') {
            return $this->setEmailRouting($this->getIndividualEmailRoute());
        }

        $identifier = false;

        // Non-VAT routing schemes (DK:DIGST, SE:ORGNR, FI:OVT, EE:CC, NO:ORG, LT:LEC, etc.)
        // use id_number (org/registry number), not vat_number.
        // IT:CUUO uses routing_id (SDI code).
        $is_vat_scheme = str_contains($code, ':VAT') || str_contains($code, ':IVA') || str_contains($code, ':CF');

        if ($this->invoice->client->country->iso_3166_2 == 'FR') {
            $identifier = $this->invoice->client->id_number;
        } elseif (str_contains($code, ':CUUO') && strlen($this->invoice->client->routing_id ?? '') > 1) {
            $identifier = $this->invoice->client->routing_id;
        } elseif (!$is_vat_scheme && strlen($this->invoice->client->id_number ?? '') > 1) {
            $clean_id = preg_replace("/[^a-zA-Z0-9]/", "", $this->invoice->client->id_number);
            $identifier = (new StorecoveRouter())->matchesSchemeFormat($code, $clean_id)
                ? $this->invoice->client->id_number
                : $this->invoice->client->vat_number;
        } else {
            $identifier = $this->invoice->client->vat_number;
        }

        if ($this->invoice->client->country->iso_3166_2 == 'DE' && $this->invoice->client->classification == 'government') {
            $identifier = $this->invoice->client->routing_id;
        }

        if (!$identifier) {
            $identifier = $this->getClientPublicIdentifier($code);
        }

        $country_prefix = $this->invoice->client->country->iso_3166_2;
        $identifier = preg_replace("/[^a-zA-Z0-9]/", "", $identifier);

        // DK:DIGST expects DK prefix on the CVR number — ensure it's present
        if ($code === 'DK:DIGST' && !str_starts_with(strtoupper($identifier), 'DK')) {
            $identifier = 'DK' . $identifier;
        }


        //Check the recipient is on the network, and can be delivered the correct document.
        if($this->invoice->client->country->iso_3166_2 == "BE"){

            $identifier = preg_replace("/^{$country_prefix}/i", "", $identifier);

            if ($this->proxyDiscovery($identifier, 'BE:EN')) {
                    $this->setStorecoveMeta($this->buildRouting([
                        ["scheme" => 'BE:EN', "id" => $identifier],
                    ]));

                    return $this;
            }
            elseif($this->proxyDiscovery("BE".$identifier, 'BE:VAT')) {
                $this->setStorecoveMeta($this->buildRouting([
                    ["scheme" => 'BE:VAT', "id" => "BE".$identifier],
                ]));

                return $this;
            }

        }


        // Composite routing codes (e.g. "0195:SGUENT08GA0028A") encode a fixed
        // gateway endpoint as scheme:id — split and use directly.
        if (preg_match('/^(\d{4}):(.+)$/', $code, $m)) {
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => $m[1], "id" => $m[2]],
            ]));
        } else {
            $this->setStorecoveMeta($this->buildRouting([
                ["scheme" => $code, "id" => $identifier],
            ]));
        }

        $this->setSvefakturaNetwork();

        return $this;
    }

    /**
     * Sets the Svefaktura network in routing metadata when the receiver is Swedish.
     */
    private function setSvefakturaNetwork(): self
    {
        if ($this->invoice->client->country->iso_3166_2 == 'SE') {
            $this->setStorecoveMeta(["routing" => ["networks" => [
                [
                    "application" => "svefaktura",
                    "settings" => [
                        "enabled" => true,
                    ],
                ],
            ]]]);
        }

        return $this;
    }

    /**
     * Resolve the Storecove/Peppol routing scheme code for the client's country and classification.
     *
     * Delegates to StorecoveRouter which maintains the per-country routing rules matrix.
     * Examples: 'DE:VAT', 'IT:CUUO', 'SE:ORGNR', 'SG:UEN', 'FR:SIRET'.
     *
     * @return string  The scheme code e.g. 'DE:VAT'
     */
    private function getClientRoutingCode(): string
    {
        return (new StorecoveRouter())->setInvoice($this->invoice)->resolveRouting($this->invoice->client->country->iso_3166_2, $this->invoice->client->classification);
    }

    /**
     * Route discovery through the proxy so self-hosted instances
     * can reach the Storecove API via the hosted server.
     */
    private function proxyDiscovery(string $identifier, string $scheme): bool
    {
        return $this->storecove->proxy
            ->setCompany($this->invoice->company)
            ->discovery($identifier, $scheme);
    }


    /**
     * Build the Storecove routing.eIdentifiers structure.
     *
     * @param  array<int, array{scheme: string, id: string}> $identifiers  One or more scheme/id pairs
     * @return array{routing: array{eIdentifiers: array}}
     */
    private function buildRouting(array $identifiers): array
    {
        return
        [
            "routing" => [
                "eIdentifiers"
                    => $identifiers,

            ],
        ];
    }


    /**
     * Add an email address to the Storecove routing metadata.
     * Used as a delivery fallback for individual/B2C recipients not on the Peppol network.
     * Multiple emails can be accumulated (appended, not replaced).
     *
     * @param  string $email
     * @return self
     */
    private function setEmailRouting(string $email): self
    {
        $meta = $this->getStorecoveMeta();

        if (isset($meta['routing']['emails'])) {
            $emails = $meta['routing']['emails'];
            array_push($emails, $email);
            $meta['routing']['emails'] = $emails;
        } else {
            $meta['routing']['emails'] = [$email];
        }

        $this->setStorecoveMeta($meta);

        return $this;
    }



    /**
     * Merge additional metadata into the Storecove API envelope.
     *
     * Uses array_merge_recursive so nested keys (routing.eIdentifiers, routing.emails, etc.)
     * are accumulated rather than overwritten. This allows multiple helpers to contribute
     * routing data without clobbering each other.
     *
     * @param  array $meta  Partial metadata to merge (e.g. routing, network config)
     * @return self
     */
    private function setStorecoveMeta(array $meta): self
    {

        $this->storecove_meta = array_merge_recursive($this->storecove_meta, $meta);

        return $this;
    }

    /**
     * Get the accumulated Storecove routing/network metadata for the API send payload.
     *
     * @return array{routing?: array{eIdentifiers?: array, emails?: string[], networks?: array}}
     */
    public function getStorecoveMeta(): array
    {
        return $this->storecove_meta;
    }


}
