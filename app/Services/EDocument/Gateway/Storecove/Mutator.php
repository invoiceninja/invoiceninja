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

    // Routing logic extracted to RoutingResolver class.

    /**
     * Resolve and set the Storecove routing metadata for the receiving client.
     *
     * Delegates to RoutingResolver for the actual resolution logic, then applies
     * the result to the storecove_meta and network configuration.
     *
     * @return self
     */
    public function setClientRoutingCode(): self
    {
        $resolver = new RoutingResolver(
            $this->invoice,
            $this->storecove->proxy,
            $this->storecove->router,
        );

        $result = $resolver->resolve();

        if ($result['type'] === 'none') {
            return $this;
        }

        $this->setStorecoveMeta($result['meta']);

        if (!empty($result['networks'])) {
            $this->setStorecoveMeta(['routing' => ['networks' => $result['networks']]]);
        }

        return $this;
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
