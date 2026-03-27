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

namespace App\Jobs\Client;

use App\DataMapper\ClientSync;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Gateway\Storecove\StorecoveProxy;
use App\Services\EDocument\Gateway\Storecove\StorecoveRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CheckPeppolDiscovery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public function __construct(public Client $client, protected Company $company) {}

    public function handle(): void
    {
        MultiDB::setDb($this->company->db);

        /** @var Storecove $storecove */
        $storecove = app(Storecove::class);
        $proxy = $storecove->proxy->setCompany($this->company);

        $discovered = false;

        foreach ($this->resolveDiscoveryCandidates() as $candidate) {
            if ($proxy->discovery($candidate['identifier'], $candidate['scheme'])) {
                $discovered = true;
                break;
            }
        }

        $sync = $this->client->sync ?? new ClientSync();
        $sync->peppol_discovery = $discovered;
        $this->client->sync = $sync;
        $this->client->saveQuietly();
    }

    /**
     * Build scheme/identifier pairs that are identical to what
     * Mutator::setClientRoutingCode() would produce at send time.
     *
     * The routing_rules matrix in StorecoveRouter is the single source of
     * truth for both the scheme and which client field holds the identifier.
     *
     * @return array<int, array{scheme: string, identifier: string}>
     */
    private function resolveDiscoveryCandidates(): array
    {
        $candidates = [];
        $country = $this->client->country->iso_3166_2 ?? null;
        $classification = $this->client->classification ?? 'business';

        if (!$country) {
            return $candidates;
        }

        // Mutator line 219: early exit when no identifiers present
        if (strlen($this->client->vat_number ?? '') < 2 && strlen($this->client->id_number ?? '') < 2) {
            return $candidates;
        }

        // Mutator lines 226-244: explicit routing_id override (scheme_code:identifier_value)
        if (stripos($this->client->routing_id ?? '', ':') !== false) {
            $parts = explode(':', $this->client->routing_id);
            if (count($parts) === 2) {
                $candidates[] = [
                    'scheme' => $parts[0],
                    'identifier' => $parts[1],
                ];
            }
        }

        // Mutator line 247: resolve routing scheme from StorecoveRouter
        $router = new StorecoveRouter();
        $code = $router->resolveRouting($country, $classification);

        if (!$code || str_contains($code, ',')) {
            return $this->deduplicate($candidates);
        }

        // Mutator lines 249-272: resolve identifier matching the Mutator's exact order
        $is_vat_scheme = str_contains($code, ':VAT') || str_contains($code, ':IVA') || str_contains($code, ':CF');

        $identifier = false;

        if ($country === 'FR') {
            $identifier = $this->client->id_number;
        } elseif (str_contains($code, ':CUUO') && strlen($this->client->routing_id ?? '') > 1) {
            $identifier = $this->client->routing_id;
        } elseif (!$is_vat_scheme && strlen($this->client->id_number ?? '') > 1) {
            $identifier = $this->client->id_number;
        } else {
            $identifier = $this->client->vat_number;
        }

        // Mutator line 266-268: DE government override
        if ($country === 'DE' && $classification === 'government') {
            $identifier = $this->client->routing_id;
        }

        if (!$identifier || strlen($identifier) < 2) {
            return $this->deduplicate($candidates);
        }

        // Mutator line 275: clean identifier
        $identifier = preg_replace('/[^a-zA-Z0-9]/', '', $identifier);

        // Mutator lines 278-280: DK:DIGST expects DK prefix
        if ($code === 'DK:DIGST' && !str_starts_with(strtoupper($identifier), 'DK')) {
            $identifier = 'DK' . $identifier;
        }

        // Mutator lines 283-302: BE tries BE:EN then BE:VAT
        if ($country === 'BE') {
            $stripped = preg_replace("/^{$country}/i", '', $identifier);

            $candidates[] = [
                'scheme' => 'BE:EN',
                'identifier' => $stripped,
            ];

            $candidates[] = [
                'scheme' => 'BE:VAT',
                'identifier' => 'BE' . $stripped,
            ];

            return $this->deduplicate($candidates);
        }

        // Mutator lines 305-307: standard path — single scheme + identifier
        $candidates[] = [
            'scheme' => $code,
            'identifier' => $identifier,
        ];

        return $this->deduplicate($candidates);
    }

    private function deduplicate(array $candidates): array
    {
        $seen = [];
        $unique = [];

        foreach ($candidates as $c) {
            $key = $c['scheme'] . '|' . $c['identifier'];
            if (!isset($seen[$key]) && strlen($c['identifier']) >= 1) {
                $seen[$key] = true;
                $unique[] = $c;
            }
        }

        return $unique;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->client->client_hash))->dontRelease()];
    }
}
