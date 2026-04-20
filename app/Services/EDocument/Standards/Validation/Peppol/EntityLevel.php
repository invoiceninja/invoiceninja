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

namespace App\Services\EDocument\Standards\Validation\Peppol;

use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\App;
use App\Services\EDocument\Standards\Peppol;
use App\Exceptions\PeppolValidationException;
use App\Services\EDocument\Standards\Validation\EntityLevelInterface;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use App\Services\EDocument\Standards\Peppol\CountryFactory;

class EntityLevel implements EntityLevelInterface
{
    private array $client_fields = [
        'address1',
        'city',
        'state',
        'postal_code',
        'country_id',
    ];

    private array $company_settings_fields = [
        'address1',
        'city',
        // 'state',
        'postal_code',
        'country_id',
    ];

    private array $company_fields = [
        // 'legal_entity_id',
        // 'vat_number IF NOT an individual
    ];

    private array $invoice_fields = [
        // 'number',
    ];

    private array $errors = [];

    public function __construct() {}

    private function init(string $locale): self
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($locale);

        return $this;

    }

    public function checkClient(Client $client): array
    {
        $this->init($client->locale());

        $this->errors['client'] = $this->testClientState($client);
        $this->errors['passes'] = count($this->errors['client']) == 0;

        return $this->errors;

    }

    public function checkCompany(Company $company): array
    {

        $this->init($company->locale());
        $this->errors['company'] = $this->testCompanyState($company);
        $this->errors['passes'] = count($this->errors['company']) == 0;

        return $this->errors;

    }

    public function checkRecurringInvoice(RecurringInvoice $recurring_invoice): array
    {
        return ['passes' => true];
    }

    public function checkInvoice(Invoice|Credit $invoice): array
    {
        $this->init($invoice->client->locale());

        $this->errors['invoice'] = [];
        $this->errors['client'] = $this->testClientState($invoice->client);
        $this->errors['company'] = $this->testCompanyState($invoice->client); // uses client level settings which is what we want

        if (count($this->errors['client']) > 0) {

            $this->errors['passes'] = false;
            return $this->errors;

        }

        $p = new Peppol($invoice);

        $xml = false;

        try {
            $xml = $p->run()->toXml();

            if (count($p->getErrors()) >= 1) {

                foreach ($p->getErrors() as $error) {
                    $this->errors['invoice'][] = $error;
                }
            }

        } catch (PeppolValidationException $e) {
            $this->errors['invoice'] = ['field' => $e->getInvalidField(), 'label' => $e->getInvalidField()];
        } catch (\Throwable $th) {

        }

        if ($xml) {
            // Second pass through the XSLT validator
            $xslt = new XsltDocumentValidator($xml);
            $errors = $xslt->validate()->getErrors();

            if (isset($errors['stylesheet']) && count($errors['stylesheet']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['stylesheet']);
            }

            if (isset($errors['general']) && count($errors['general']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['general']);
            }

            if (isset($errors['xsd']) && count($errors['xsd']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['xsd']);
            }

        }

        $this->checkNexus($invoice->client);

        $this->errors['passes'] = count($this->errors['invoice']) == 0 && count($this->errors['client']) == 0 && count($this->errors['company']) == 0;

        return $this->errors;

    }

    private function testClientState(Client $client): array
    {

        $errors = [];

        foreach ($this->client_fields as $field) {

            if ($field == 'country_id' && $client->country_id >= 1) {
                continue;
            }

            if (in_array($field, ['address1', 'address2', 'city', 'postal_code']) && strlen($client->{$field} ?? '') < 2) {
                // if (in_array($field, ['address1', 'address2', 'city', 'state', 'postal_code']) && strlen($client->{$field} ?? '') < 2) {
                $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];
            }

            if ($this->validString($client->{$field})) {
                continue;
            }

        }

        if (!$client->country) {
            $errors[] = ['field' => 'country_id', 'label' => ctrans("texts.country")];
            return $errors;
        }

        //Primary contact email is present.
        if ($client->present()->email() == 'No Email Set') {
            $errors[] = ['field' => 'email', 'label' => ctrans("texts.email")];
        }

        if ($client->country_id && $client->country) {
            $non_routable = $client->checkDeliveryNetwork();

            if (is_string($non_routable)) {
                $errors[] = ['field' => 'classification', 'label' => $non_routable];
            }
        }

        // Identifier validation — offline (no network I/O).
        // Only runs once all earlier checks pass AND the client's country is on the Peppol network.
        if (count($errors) === 0
            && in_array($client->country->iso_3166_2, StorecoveRouter::peppolCountries(), true)) {

            $errors = array_merge($errors, $this->testClientIdentifiers($client));
        }

        return $errors;

    }

    /**
     * Validates that the client can be routed on the Peppol network.
     *
     * The country handler's getCandidates() defines exactly what the send-time
     * RoutingResolver will try — a list of (scheme, id) pairs derived from the
     * client's data. Validation succeeds if ANY one of those candidates passes
     * format+checkdigit validation: that is what it means to be routable.
     *
     * Multiple candidates exist for countries where several schemes are
     * interchangeable (e.g. BE derives both BE:EN and BE:VAT from vat_number),
     * so requiring a specific scheme would be wrong.
     *
     * Offline validation only — no SMP discovery; that is the send-time
     * RoutingResolver's responsibility.
     *
     * @return array<int, array{field: string, label: string}>
     */
    private function testClientIdentifiers(Client $client): array
    {
        $router         = new StorecoveRouter();
        $country        = $client->country->iso_3166_2;
        $classification = $client->classification ?? 'business';

        // FIRST: explicit routing_id override (scheme:id form). If set, it must
        // validate — don't silently fall through to the handler and give the
        // user a generic "no valid routing identifier" when the problem is a
        // malformed routing_id.
        $routingError = $this->validateExplicitRoutingId($client, $router);
        if ($routingError !== null) {
            return $routingError === [] ? [] : [$routingError];
        }

        // SECOND: handler-driven candidates (vat_number, id_number, etc.).
        $candidates = CountryFactory::make($country)
            ->getCandidates($client, $classification, $router);

        foreach ($candidates as $candidate) {
            if ($router->validateIdentifierFormat($candidate['scheme'], $candidate['id'])) {
                return [];
            }
        }

        return [$this->buildIdentifierError($candidates, $client, $router)];
    }

    /**
     * Validates an explicit routing_id override on the client.
     *
     * Returns:
     *   - []    → routing_id is set AND valid — caller should return empty (pass).
     *   - array → routing_id is set AND invalid — caller should return this single error.
     *   - null  → no routing_id set — caller should fall through to handler candidates.
     *
     * A scheme-prefixed routing_id (e.g. "0088:1234567890123") short-circuits
     * validation: it is what the send-time RoutingResolver tries first.
     *
     * @return array{field: string, label: string}|array{}|null
     */
    private function validateExplicitRoutingId(Client $client, StorecoveRouter $router): ?array
    {
        $value = trim($client->routing_id ?? '');

        if ($value === '') {
            return null;
        }

        // scheme:id form — always validated strictly.
        if (strpos($value, ':') !== false) {
            return $this->validateSchemeColonId($value, $router);
        }

        // Bare value. For countries whose handler natively consumes routing_id
        // (IT wraps as IT:CUUO; DE government wraps as DE:LWID), let the
        // handler interpret the raw value — don't guess here.
        if ($this->handlerConsumesBareRoutingId($client)) {
            return null;
        }

        // Bare value on a country that doesn't natively use routing_id. The
        // user is attempting an override. Numeric values look like GLN
        // attempts; give a GLN-specific error so the user knows what to fix.
        if (ctype_digit($value)) {
            return $this->validateGln($value, $value);
        }

        return [
            'field' => 'routing_id',
            'label' => "routing_id \"{$value}\" must be in scheme:id format (e.g. 0088:1234567890123 for GLN).",
        ];
    }

    /**
     * Validates a "scheme:id" routing_id value.
     *
     * @return array{field: string, label: string}|array{} error or [] on pass
     */
    private function validateSchemeColonId(string $value, StorecoveRouter $router): array
    {
        $parts = explode(':', $value, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return [
                'field' => 'routing_id',
                'label' => ctrans('texts.routing_id') . "'{$value}' must be in scheme:id format (e.g. 0088:1234567890123 for GLN).",
            ];
        }

        [$scheme, $id] = $parts;

        // GLN (ICD 0088) — must be 14 numeric digits with a valid GS1 mod-10
        // check digit. The router has no regex entry for numeric ICD schemes,
        // so this has to be enforced here.
        if ($scheme === '0088') {
            return $this->validateGln($id, "{$scheme}:{$id}");
        }

        if (!$router->validateIdentifierFormat($scheme, $id)) {
            return [
                'field' => 'routing_id',
                'label' => ctrans('texts.routing_id') . " {$scheme}:{$id} does not match the expected format for {$scheme}.",
            ];
        }

        return []; // valid
    }

    /**
     * Validates a GLN: 14 numeric digits with a valid GS1 mod-10 check digit.
     *
     * Storecove (scheme 0088) enforces `^\d{14}$` and rejects anything else
     * with a 422. On top of that, GS1 requires the rightmost digit to be a
     * mod-10 check over the preceding 13: starting from the rightmost body
     * digit (position 2 from the right), weights alternate 3, 1, 3, 1, ...;
     * check = (10 − (weighted_sum mod 10)) mod 10.
     *
     * @param  string $digits  The numeric value to validate (14 digits expected)
     * @param  string $display The user-visible value used in error messages
     * @return array{field: string, label: string}|array{} error or [] on pass
     */
    private function validateGln(string $digits, string $display): array
    {
        if (!ctype_digit($digits) || strlen($digits) !== 14) {
            return [
                'field' => 'routing_id',
                'label' => "routing_id \"{$display}\" looks like a GLN but must be 14 digits (got " . strlen($digits) . "). Use format 0088:<14-digit-GLN> if that was your intent.",
            ];
        }

        if (StorecoveRouter::isValidGln($digits)) {
            return [];
        }

        // Length was right, so the only remaining failure mode is the check digit.
        $sum = 0;
        $weights = [3, 1];
        for ($i = 12, $j = 0; $i >= 0; $i--, $j++) {
            $sum += ((int) $digits[$i]) * $weights[$j % 2];
        }
        $expected = (10 - ($sum % 10)) % 10;
        $actual   = (int) $digits[13];

        return [
            'field' => 'routing_id',
            'label' => "routing_id GLN \"{$display}\" has an invalid check digit (expected {$expected}, got {$actual}). Verify the value.",
        ];
    }

    /**
     * Countries whose handler reads routing_id directly as a raw value.
     * For these, a bare routing_id is not an "override" — it IS the native
     * routing input (e.g. IT:CUUO).
     */
    private function handlerConsumesBareRoutingId(Client $client): bool
    {
        $country = $client->country->iso_3166_2;
        $classification = $client->classification ?? 'business';

        if ($country === 'IT') {
            return true;
        }

        if ($country === 'DE' && $classification === 'government') {
            return true;
        }

        return false;
    }

    /**
     * Builds a single error when no candidate is routable. Mentions every
     * scheme the handler attempted, so the user can see what inputs would
     * satisfy delivery — not a single "required field" that may be misleading.
     *
     * @param  array<int, array{scheme: string, id: string}> $candidates
     * @return array{field: string, label: string}
     */
    private function buildIdentifierError(array $candidates, Client $client, StorecoveRouter $router): array
    {
        $countryName = $client->country->full_name ?? $client->country->iso_3166_2;

        if (empty($candidates)) {
            return [
                'field' => 'vat_number',
                'label' => "A valid routing identifier is required for Peppol delivery to {$countryName}.",
            ];
        }

        $parts = [];
        foreach ($candidates as $c) {
            $example = $router->getFormatExample($c['scheme']);
            $parts[] = $example
                ? "{$c['scheme']} (e.g. {$example})"
                : $c['scheme'];
        }

        return [
            'field' => 'vat_number',
            'label' => "No valid Peppol routing identifier for {$countryName}. Any one of: " . implode(', ', $parts) . '.',
        ];
    }


    private function testCompanyState(mixed $entity): array
    {

        $client = false;
        $vendor = false;
        $settings_object = false;
        $company = false;

        if ($entity instanceof Client) {
            $client = $entity;
            $company = $entity->company;
            $settings_object = $client;
        } elseif ($entity instanceof Company) {
            $company = $entity;
            $settings_object = $company;
        } elseif ($entity instanceof Vendor) {
            $vendor = $entity;
            $company = $entity->company;
            $settings_object = $company;
        } elseif ($entity instanceof Invoice || $entity instanceof Credit || $entity instanceof Quote) {
            $client = $entity->client;
            $company = $entity->company;
            $settings_object = $entity->client;
        } elseif ($entity instanceof PurchaseOrder) {
            $vendor = $entity->vendor;
            $company = $entity->company;
            $settings_object = $company;
        }

        $errors = [];

        foreach ($this->company_settings_fields as $field) {

            if ($this->validString($settings_object->getSetting($field))) {
                continue;
            }

            $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];

        }

        //test legal entity id present
        if (intval($company->legal_entity_id) == 0) {
            $errors[] = ['field' => "You have not registered a legal entity id as yet."];
        }

        //If not an individual, you MUST have a VAT number
        if (!in_array($company->getSetting('classification'), ['other', 'individual']) && !$this->validString($company->getSetting('vat_number'))) {
            $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.vat_number")];
        }

        return $errors;

    }

    // private function testInvoiceState($entity): array
    // {
    //     $errors = [];

    //     foreach($this->invoice_fields as $field)
    //     {

    //     }

    //     return $errors;
    // }

    // private function testVendorState(): array
    // {

    // }


    /************************************ helpers ************************************/
    private function validString(?string $string): bool
    {
        return iconv_strlen($string ?? '') >= 1;
    }

    private function checkNexus(Client $client): self
    {

        $company_country_code = $client->company->country()->iso_3166_2;
        $client_country_code = $client->country->iso_3166_2;
        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($client_country_code == $company_country_code) {
        } elseif (in_array($company_country_code, $eu_countries) && !in_array($client_country_code, $eu_countries)) {
        } elseif (in_array($client_country_code, $eu_countries)) {

            // First, determine if we're over threshold
            $is_over_threshold = isset($client->company->tax_data->regions->EU->has_sales_above_threshold)
                               && $client->company->tax_data->regions->EU->has_sales_above_threshold;

            // Is this B2B or B2C?
            $is_b2c = strlen($client->vat_number ?? '') < 2
                    || !($client->has_valid_vat_number ?? false)
                    || $client->classification == 'individual';

            // B2C, under threshold, no Company VAT Registerd - must charge origin country VAT
            if ($is_b2c && !$is_over_threshold && strlen($client->company->settings->vat_number ?? '') < 2) {

            } elseif ($is_b2c) {
                if ($is_over_threshold) {
                    // B2C over threshold - need destination VAT number
                    if (!isset($client->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number)) {
                        $this->errors['invoice'][] = "Tax Nexus is client country ({$client_country_code}) - however VAT number not present for this region.";
                    }
                }

            } elseif ($is_over_threshold && !in_array($company_country_code, $eu_countries)) {

            }


        }

        return $this;
    }


}
