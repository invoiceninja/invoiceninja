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
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ARSummaryReport extends BaseExport
{
    use MakesDates;

    public Writer $csv;

    // 2026-01-16: Changed from created_at to date to match the invoice date
    public string $date_key = 'date';

    public Client $client;

    private float $total = 0;

    private array $clients = [];

    private string $template = '/views/templates/reports/ar_summary_report.html';
    
    /**
     * Flag to use optimized query (single query vs N+1).
     * Set to false to rollback to legacy implementation.
     */
    private bool $useOptimizedQuery = true;
    
    /**
     * Chunk size for whereIn queries to avoid SQL limits.
     * MySQL has max_allowed_packet and whereIn performance degrades with large arrays.
     * Set to 1000 to safely handle 100,000+ clients without hitting SQL limits.
     */
    private int $chunkSize = 1000;

    public array $report_keys = [
        'client_name',
        'client_number',
        'id_number',
        'current',
        'age_group_0',
        'age_group_30',
        'age_group_60',
        'age_group_90',
        'age_group_120',
        'total',
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
        $this->csv->insertOne([ctrans('texts.aged_receivable_summary_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        if ($this->useOptimizedQuery) {
            $this->runOptimized();
        } else {
            $this->runLegacy();
        }

        return $this->csv->toString();
    }

    /**
     * Legacy implementation: N+1 query approach (6 queries per client).
     * Preserved for easy rollback if needed.
     */
    private function runLegacy(): void
    {
        $query = Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $this->filterByUserPermissions($query);

        $query->orderBy('balance', 'desc')
            ->cursor()
            ->each(function ($client) {
                /** @var \App\Models\Client $client */
                $this->csv->insertOne($this->buildRow($client));
            });
    }

    /**
     * Optimized implementation: Single query with CASE statements.
     * Reduces 6 queries per client to 1 query total.
     */
    private function runOptimized(): void
    {
        // Get all client IDs with permission filtering
        $query = Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $this->filterByUserPermissions($query);

        // Process clients in chunks to avoid whereIn() SQL limits
        // For 100,000 clients, this creates 100 chunks with 1 query each
        $query->orderBy('balance', 'desc')
            ->chunk($this->chunkSize, function ($clientChunk) {
                $clientIds = $clientChunk->pluck('id')->toArray();
                
                if (empty($clientIds)) {
                    return true; // Continue to next chunk
                }
                
                // Fetch aging data for this chunk (1 query per chunk)
                $agingData = $this->getAgingDataOptimized($clientIds);
                
                // Build rows from cached data
                foreach ($clientChunk as $client) {
                    /** @var \App\Models\Client $client */
                    $this->csv->insertOne($this->buildRowOptimized($client, $agingData));
                }
                
                return true; // Continue to next chunk
            });
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
     * Fetch all aging data for multiple clients in a single query.
     * Uses CASE statements to calculate all aging buckets in one pass.
     * 
     * @param array $clientIds Array of client IDs (should be ≤ 1000 from chunking)
     * @return Collection Aging data keyed by client_id
     */
    private function getAgingDataOptimized(array $clientIds): Collection
    {
        if (empty($clientIds)) {
            return collect([]);
        }
        
        $now = now()->startOfDay();
        $nowStr = $now->toDateString();
        $date_30 = $now->copy()->subDays(30)->toDateString();
        $date_31 = $now->copy()->subDays(31)->toDateString();
        $date_60 = $now->copy()->subDays(60)->toDateString();
        $date_61 = $now->copy()->subDays(61)->toDateString();
        $date_90 = $now->copy()->subDays(90)->toDateString();
        $date_91 = $now->copy()->subDays(91)->toDateString();
        $date_120 = $now->copy()->subDays(120)->toDateString();
        $date_121 = $now->copy()->subDays(121)->toDateString();
        $pastDate = $now->copy()->subYears(20)->toDateString();

        return DB::table('invoices')
            ->selectRaw('
                client_id,
                SUM(CASE 
                    WHEN (due_date > ? OR due_date IS NULL) 
                    THEN balance 
                    ELSE 0 
                END) as current,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_30,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_60,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_90,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_120,
                SUM(CASE 
                    WHEN due_date BETWEEN ? AND ? 
                    THEN balance 
                    ELSE 0 
                END) as age_120_plus,
                SUM(balance) as total
            ', [
                $nowStr,                // current > now
                $date_30, $nowStr,     // 0-30 days
                $date_60, $date_31,    // 31-60 days
                $date_90, $date_61,    // 61-90 days
                $date_120, $date_91,   // 91-120 days
                $pastDate, $date_121,  // 120+ days
            ])
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->whereIn('client_id', $clientIds)
            ->groupBy('client_id')
            ->get()
            ->keyBy('client_id');
    }

    /**
     * Build row using pre-fetched aging data (optimized).
     */
    private function buildRowOptimized(Client $client, Collection $agingData): array
    {
        $data = $agingData->get($client->id);

        // If no invoices for this client, use zeros
        if (!$data) {
            $row = [
                $client->present()->name(),
                $client->number,
                $client->id_number,
                Number::formatMoney(0, $this->company),
                Number::formatMoney(0, $this->company),
                Number::formatMoney(0, $this->company),
                Number::formatMoney(0, $this->company),
                Number::formatMoney(0, $this->company),
                Number::formatMoney(0, $this->company),
                Number::formatMoney(0, $this->company),
            ];
        } else {
            $row = [
                $client->present()->name(),
                $client->number,
                $client->id_number,
                Number::formatMoney($data->current, $this->company),
                Number::formatMoney($data->age_30, $this->company),
                Number::formatMoney($data->age_60, $this->company),
                Number::formatMoney($data->age_90, $this->company),
                Number::formatMoney($data->age_120, $this->company),
                Number::formatMoney($data->age_120_plus, $this->company),
                Number::formatMoney($data->total, $this->company),
            ];
        }

        $this->clients[] = $row;

        return $row;
    }

    private function buildRow(Client $client): array
    {
        $this->client = $client;

        $row = [
            $this->client->present()->name(),
            $this->client->number,
            $this->client->id_number,
            $this->getCurrent(),
            $this->getAgingAmount('30'),
            $this->getAgingAmount('60'),
            $this->getAgingAmount('90'),
            $this->getAgingAmount('120'),
            $this->getAgingAmount('120+'),
            Number::formatMoney($this->total, $this->company),
        ];

        $this->total = 0;

        $this->clients[] = $row;

        return $row;
    }

    private function getCurrent(): string
    {
        $amount = Invoice::withTrashed()
            ->where('client_id', $this->client->id)
            ->where('company_id', $this->client->company_id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where(function ($query) {
                $query->where('due_date', '>', now()->startOfDay())
                    ->orWhereNull('due_date');
            })
            ->sum('balance');

        $this->total += $amount;

        return Number::formatMoney($amount, $this->client);

    }
    /**
     * Generate aging amount.
     *
     * @param mixed $range
     * @return string
     */
    private function getAgingAmount($range)
    {
        $ranges = $this->calculateDateRanges($range);

        $from = $ranges[0];
        $to = $ranges[1];

        $amount = Invoice::withTrashed()
            ->where('client_id', $this->client->id)
            ->where('company_id', $this->client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$to, $from])
            ->sum('balance');

        $this->total += $amount;

        return Number::formatMoney($amount, $this->client);
    }

    /**
     * Calculate date ranges for aging.
     *
     * @param mixed $range
     * @return array
     */
    private function calculateDateRanges($range)
    {
        $ranges = [];

        switch ($range) {
            case '30':
                $ranges[0] = now()->startOfDay();
                $ranges[1] = now()->startOfDay()->subDays(30);

                return $ranges;
            case '60':
                $ranges[0] = now()->startOfDay()->subDays(31);
                $ranges[1] = now()->startOfDay()->subDays(60);

                return $ranges;
            case '90':
                $ranges[0] = now()->startOfDay()->subDays(61);
                $ranges[1] = now()->startOfDay()->subDays(90);

                return $ranges;
            case '120':
                $ranges[0] = now()->startOfDay()->subDays(91);
                $ranges[1] = now()->startOfDay()->subDays(120);

                return $ranges;
            case '120+':
                $ranges[0] = now()->startOfDay()->subDays(121);
                $ranges[1] = now()->startOfDay()->subYears(20);

                return $ranges;
            default:
                $ranges[0] = now()->startOfDay()->subDays(0);
                $ranges[1] = now()->subDays(30);

                return $ranges;
        }
    }

    public function buildHeader(): array
    {
        $header = [];

        foreach ($this->input['report_keys'] as $value) {

            $header[] = ctrans("texts.{$value}");
        }

        return $header;
    }

}
