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

namespace App\Http\Controllers;

use App\DataProviders\SettingsSearchMap;
use App\Http\Requests\Search\GenericSearchRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    use MakesHash;
    
    private array $clients = [];

    private array $client_contacts = [];

    private array $invoices = [];

    private array $quotes = [];

    private array $expenses = [];

    private array $credits = [];

    private array $recurring_invoices = [];

    private array $vendors = [];

    private array $vendor_contacts = [];

    private array $purchase_orders = [];

    private array $projects = [];

    private array $tasks = [];

    public function __invoke(GenericSearchRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $locale = $user->language ? $user->language->locale : $user->company()->locale();

        \Illuminate\Support\Facades\App::setLocale($locale);

        if (config('scout.driver') == 'elastic') {
            try {
                return $this->search($request->input('search', '*'));
            } catch (\Exception $e) {
                nlog("elk down?" . $e->getMessage());
            }
        }

        $this->clientMap($user);

        $this->invoiceMap($user);

        $this->projectMap($user);

        return response()->json([
            'clients' => $this->clients,
            'client_contacts' => $this->client_contacts,
            'invoices' => $this->invoices,
            'projects' => $this->projects,
            'settings' => $this->settingsMap($user),
        ], 200);

    }

    public function search(string $search)
    {
        $user = auth()->user();
        $company = $user->company();

        $search_index = 'clients,invoices,client_contacts,quotes,expenses,credits,recurring_invoices,vendors,vendor_contacts,purchase_orders,projects,tasks';

        $search = trim($search);


        $elastic = ClientBuilder::fromConfig(config('elastic.client.connections.default'));

        $params = [
            'index' => $search_index,
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => [
                            [
                                'multi_match' => [
                                    'query' => $search,
                                    'fields' => ['*'],
                                    'fuzziness' => 'AUTO',
                                ],
                            ],
                            // Safe nested search that won't fail on missing fields
                            [
                                'nested' => [
                                    'path' => 'line_items',
                                    'query' => [
                                        'multi_match' => [
                                            'query' => $search,
                                            'fields' => [
                                                'line_items.product_key^2',
                                                'line_items.notes^2',
                                                'line_items.custom_value1',
                                                'line_items.custom_value2',
                                                'line_items.custom_value3',
                                                'line_items.custom_value4',
                                            ],
                                            'fuzziness' => 'AUTO',
                                        ],
                                    ],
                                    'ignore_unmapped' => true,
                                ],
                            ],
                        ],
                        'minimum_should_match' => 1,
                        'filter' => $this->permissionFilter($user, $company),
                    ],
                ],
                'size' => 100,
            ],
        ];


        $results = $elastic->search($params);

        $this->mapResults($results['hits']['hits'] ?? []);

        return response()->json([
            'clients' => $this->clients,
            'client_contacts' => $this->client_contacts,
            'invoices' => $this->invoices,
            'quotes' => $this->quotes,
            'expenses' => $this->expenses,
            'credits' => $this->credits,
            'recurring_invoices' => $this->recurring_invoices,
            'vendors' => $this->vendors,
            'vendor_contacts' => $this->vendor_contacts,
            'purchase_orders' => $this->purchase_orders,
            'projects' => $this->projects,
            'tasks' => $this->tasks,
            'settings' => $this->settingsMap($user),
        ], 200);

    }

    /**
     * Builds the Elasticsearch filter clause that scopes results to the
     * current company and, for non-admin users, to the entities they are
     * permitted to view.
     *
     * The company_key term is ALWAYS enforced as a top-level `must` to prevent
     * cross-tenant bleed, since user_id/assigned_user_id are raw integer ids
     * that collide across databases.
     *
     * For each index the user lacks the relevant `view_{entity}` permission on,
     * results are restricted to records they created (user_id) OR were assigned
     * (assigned_user_id).
     *
     * @param  User    $user
     * @param  Company $company
     * @return array<int|string, mixed>
     */
    private function permissionFilter(User $user, Company $company): array
    {
        $company_filter = [
            'match' => [
                'company_key' => $company->company_key,
            ],
        ];

        if ($user->isSuperUser()) {
            return [$company_filter];
        }

        $index_permissions = [
            'clients' => 'view_client',
            'client_contacts' => 'view_client',
            'invoices' => 'view_invoice',
            'quotes' => 'view_quote',
            'expenses' => 'view_expense',
            'credits' => 'view_credit',
            'recurring_invoices' => 'view_recurring_invoice',
            'vendors' => 'view_vendor',
            'vendor_contacts' => 'view_vendor',
            'purchase_orders' => 'view_purchase_order',
            'projects' => 'view_project',
            'tasks' => 'view_task',
        ];

        $unrestricted = [];
        $restricted = [];

        foreach ($index_permissions as $index => $permission) {
            if ($user->hasPermission($permission)) {
                $unrestricted[] = $index;
            } else {
                $restricted[] = $index;
            }
        }

        $should = [];

        if (count($unrestricted) > 0) {
            $should[] = ['terms' => ['_index' => $unrestricted]];
        }

        if (count($restricted) > 0) {
            $should[] = [
                'bool' => [
                    'must' => [
                        ['terms' => ['_index' => $restricted]],
                        ['bool' => [
                            'minimum_should_match' => 1,
                            'should' => [
                                ['term' => ['user_id' => (string) $user->id]],
                                ['term' => ['assigned_user_id' => (string) $user->id]],
                            ],
                        ]],
                    ],
                ],
            ];
        }

        return [
            'bool' => [
                'must' => [$company_filter],
                'minimum_should_match' => 1,
                'should' => $should,
            ],
        ];
    }

    private function mapResults(array $results)
    {

        foreach ($results as $result) {
            switch (true) {
                case Str::startsWith($result['_index'], 'clients'):

                    if ($result['_source']['is_deleted']) { //do not return deleted results
                        break;
                    }

                    $this->clients[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/client',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/clients/{$result['_source']['hashed_id']}",
                    ];

                    break;
                case Str::startsWith($result['_index'], 'invoices'):

                    if ($result['_source']['is_deleted']) {  //do not return deleted invoices
                        break;
                    }


                    $this->invoices[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/invoice',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/invoices/{$result['_source']['hashed_id']}/edit",
                    ];
                    break;
                case Str::startsWith($result['_index'], 'client_contacts'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->client_contacts[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/client',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/clients/{$result['_source']['client_id']}",
                    ];
                    break;
                case Str::startsWith($result['_index'], 'quotes'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->quotes[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/quote',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/quotes/{$result['_source']['hashed_id']}",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'expenses'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->expenses[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/expense',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/expenses/{$result['_source']['hashed_id']}/edit",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'credits'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->credits[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/credit',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/credits/{$result['_source']['hashed_id']}",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'recurring_invoices'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->recurring_invoices[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/recurring_invoice',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/recurring_invoices/{$result['_source']['hashed_id']}",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'vendors'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->vendors[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/vendor',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/vendors/{$result['_source']['hashed_id']}",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'vendor_contacts'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->vendor_contacts[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/vendor',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/vendors/{$result['_source']['vendor_id']}",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'purchase_orders'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->purchase_orders[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/purchase_order',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/purchase_orders/{$result['_source']['hashed_id']}",
                    ];

                    break;

                case Str::startsWith($result['_index'], 'projects'):

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->projects[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/project',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/projects/{$result['_source']['hashed_id']}",
                    ];

                    break;
                case Str::startsWith($result['_index'], 'tasks'):

                    if ($result['_source']['is_deleted']) {
                        break;
                    }

                    $this->tasks[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/task',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/tasks/{$result['_source']['hashed_id']}/edit",
                    ];

                    break;
            }
        }
    }

    private function clientMap(User $user)
    {

        $clients =  Client::query()
                     ->withTrashed()
                     ->company()
                     ->where('is_deleted', 0)
                     ->when(!$user->hasPermission('view_all') || !$user->hasPermission('view_client'), function ($query) use ($user) {
                         $query->where('user_id', $user->id);
                     })
                     ->orderBy('updated_at', 'desc')
                     ->take(1000)
                     ->get();

        foreach ($clients as $client) {
            $this->clients[] = [
                'name' => $client->present()->name(),
                'type' => '/client',
                'id' => $client->hashed_id,
                'path' => "/clients/{$client->hashed_id}",
            ];

            $client->contacts->each(function ($contact) {
                $this->client_contacts[] = [
                    'name' => $contact->present()->search_display(),
                    'type' => '/client',
                    'id' => $contact->client->hashed_id,
                    'path' => "/clients/{$contact->client->hashed_id}",
                ];
            });
        }


    }

    private function projectMap(User $user)
    {

        $projects = Project::query()
                     ->withTrashed()
                     ->company()
                     ->with('client')
                     ->where('is_deleted', 0)
                     ->whereHas('client', function ($q) {
                         $q->where('is_deleted', 0);
                     })
                     ->when(!$user->hasPermission('view_all') || !$user->hasPermission('view_invoice'), function ($query) use ($user) {
                         $query->where('projects.user_id', $user->id);
                     })
                     ->orderBy('id', 'desc')
                    ->take(3000)
                    ->get();

        foreach ($projects as $project) {
            $this->projects[] = [
                'name' => $project->name . ' - ' . $project->number,
                'type' => '/project',
                'id' => $project->hashed_id,
                'path' => "/projects/{$project->hashed_id}",
            ];
        }

    }

    private function invoiceMap(User $user)
    {

        $invoices = Invoice::query()
                     ->withTrashed()
                     ->company()
                     ->with('client')
                     ->where('is_deleted', 0)
                     ->whereHas('client', function ($q) {
                         $q->where('is_deleted', 0);
                     })
                     ->when(!$user->hasPermission('view_all') || !$user->hasPermission('view_invoice'), function ($query) use ($user) {
                         $query->where('invoices.user_id', $user->id);
                     })
                     ->orderBy('id', 'desc')
                    ->take(3000)
                    ->get();

        foreach ($invoices as $invoice) {
            $this->invoices[] = [
                'name' => $invoice->client->present()->name() . ' - ' . $invoice->number,
                'type' => '/invoice',
                'id' => $invoice->hashed_id,
                'path' => "/invoices/{$invoice->hashed_id}/edit",
            ];
        }

    }

    /**
     * Builds the searchable settings catalogue for the given user.
     *
     * The translated catalogue is cached per locale; per-user permission and
     * per-deployment scope gating is then applied so we never surface a
     * destination the user cannot reach. Each entry carries a `keywords` field
     * (parent heading + curated synonyms) to widen client-side matching beyond
     * the literal label.
     *
     * @param  User $user
     * @return array<int, array{name: string, heading: string, type: string, id: string, path: string, keywords: string}>
     */
    private function settingsMap(User $user): array
    {
        $locale = app()->getLocale();

        $catalogue = Cache::remember("settings_search_map_{$locale}", 3600, function () {
            return array_map(function (array $entry): array {
                $heading = $entry['section'] ? ctrans("texts.{$entry['section']}") : ctrans('texts.settings');
                $name = ctrans("texts.{$entry['label']}");

                return [
                    'name' => $name,
                    'heading' => $heading,
                    'type' => '/settings',
                    'id' => $entry['path'],
                    'path' => $entry['path'],
                    'keywords' => trim($heading . ' ' . $entry['keywords']),
                    'permission' => $entry['permission'],
                    'scope' => $entry['scope'],
                ];
            }, SettingsSearchMap::all());
        });

        $is_hosted = Ninja::isHosted();

        $data = [];

        foreach ($catalogue as $entry) {

            if ($entry['scope'] === SettingsSearchMap::SCOPE_HOSTED && !$is_hosted) {
                continue;
            }

            if ($entry['scope'] === SettingsSearchMap::SCOPE_SELFHOST && $is_hosted) {
                continue;
            }

            if ($entry['permission'] === SettingsSearchMap::ADMIN && !$user->isSuperUser()) {
                continue;
            }

            unset($entry['permission'], $entry['scope']);

            $data[] = $entry;
        }

        usort($data, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $data;
    }

}
