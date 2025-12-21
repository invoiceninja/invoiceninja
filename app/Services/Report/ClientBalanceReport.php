<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report;

use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\DB;
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;

class ClientBalanceReport extends BaseExport
{
    use MakesDates;
    //Name
    //Invoice count
    //Amount
    //Amount with Tax
    public Writer $csv;

    public string $date_key = 'created_at';

    /**
     * Toggle between optimized and legacy implementation
     * Set to false to rollback to legacy per-client queries
     */
    private bool $useOptimizedQuery = true;

    private string $template = '/views/templates/reports/client_balance_report.html';

    private array $clients = [];

    private array $invoiceData = [];

    public array $report_keys = [
        'client_name',
        'client_number',
        'id_number',
        'invoices',
        'invoice_balance',
        'credit_balance',
        'payment_balance',
    ];

    /**
        @param array $input
        [
            'date_range',
            'start_date',
            'end_date',
            'clients',
            'client_id',
        ]
    */
    public function __construct(public Company $company, public array $input)
    {
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->csv = Writer::fromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.client_balance_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        if ($this->useOptimizedQuery) {
            return $this->runOptimized();
        }

        return $this->runLegacy();
    }

    /**
     * Optimized implementation: Single query for all invoice aggregates
     * Reduces N+1 queries to 1 query total
     */
    private function runOptimized(): string
    {
        // Fetch all clients
        $query = Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $this->filterByUserPermissions($query);

        $clients = $query->orderBy('balance', 'desc')->get();

        // Fetch all invoice aggregates in a single query
        $this->invoiceData = $this->getInvoiceDataOptimized($clients->pluck('id')->toArray());

        // Build rows using pre-fetched data
        foreach ($clients as $client) {
            /** @var \App\Models\Client $client */
            $this->csv->insertOne($this->buildRowOptimized($client));
        }

        return $this->csv->toString();
    }

    /**
     * Legacy implementation: Preserved for rollback
     * Makes 2 queries per client (count + sum)
     */
    private function runLegacy(): string
    {
        $query = Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $this->filterByUserPermissions($query);

        $query->where('balance', '!=', 0)
            ->orderBy('balance', 'desc')
            ->cursor()
            ->each(function ($client) {
                /** @var \App\Models\Client $client */
                $this->csv->insertOne($this->buildRow($client));
            });

        return $this->csv->toString();
    }

    /**
     * Fetch invoice aggregates for all clients in a single query
     */
    private function getInvoiceDataOptimized(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        // Build base query
        $query = Invoice::query()
            ->select('client_id')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(balance) as total_balance')
            ->where('company_id', $this->company->id)
            ->whereIn('client_id', $clientIds)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('is_deleted', 0)
            ->groupBy('client_id');

        // Apply date filtering using the same logic as legacy
        $query = $this->addDateRange($query, 'invoices');

        // Execute and index by client_id
        $results = $query->get();

        $data = [];
        foreach ($results as $row) {
            $data[$row->client_id] = [ // @phpstan-ignore-line
                'count' => $row->invoice_count, // @phpstan-ignore-line
                'balance' => $row->total_balance ?? 0,
            ];
        }

        return $data;
    }

    /**
     * Build row using pre-fetched invoice data (optimized path)
     */
    private function buildRowOptimized(Client $client): array
    {
        $invoiceData = $this->invoiceData[$client->id] ?? ['count' => 0, 'balance' => 0];

        $item = [
            $client->present()->name(),
            $client->number,
            $client->id_number,
            $invoiceData['count'],
            $invoiceData['balance'],
            Number::formatMoney($client->credit_balance, $this->company),
            Number::formatMoney($client->payment_balance, $this->company),
        ];

        $this->clients[] = $item;

        return $item;
    }

    public function buildHeader(): array
    {
        $headers = [];

        foreach ($this->report_keys as $key) {
            $headers[] = ctrans("texts.{$key}");
        }

        return $headers;

    }

    public function getPdf()
    {
        $user = isset($this->input['user_id']) ? User::withTrashed()->find($this->input['user_id']) : $this->company->owner();

        $user_name = $user ? $user->present()->name() : '';

        $data = [
            'clients' => $this->clients,
            'company_logo' => $this->company->present()->logo(),
            'company_name' => $this->company->present()->name(),
            'created_on' => $this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale()),
            'created_by' => $user_name,
        ];

        $ts = new TemplateService();

        $ts_instance = $ts->setCompany($this->company)
                    ->setData($data)
                    ->setRawTemplate(file_get_contents(resource_path($this->template)))
                    ->parseNinjaBlocks()
                    ->save();

        return $ts_instance->getPdf();
    }

    /**
     * Legacy row builder: Preserved for rollback
     * Makes 2 queries per client
     */
    private function buildRow(Client $client): array
    {
        $query = Invoice::query()->where('client_id', $client->id)
                                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL]);

        $query = $this->addDateRange($query, 'invoices');

        $item = [
            $client->present()->name(),
            $client->number,
            $client->id_number,
            $query->count(),
            $query->sum('balance'),
            Number::formatMoney($client->credit_balance, $this->company),
            Number::formatMoney($client->payment_balance, $this->company),
        ];

        $this->clients[] = $item;

        return $item;
    }
}
