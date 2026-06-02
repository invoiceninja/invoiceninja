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

namespace App\Services\Report;

use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Libraries\MultiDB;
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;

class ClientSalesReport extends BaseExport
{
    use MakesDates;
    //Name
    //Invoice count
    //Amount
    //Amount with Tax

    public Writer $csv;

    public string $date_key = 'date';

    private string $template = '/views/templates/reports/client_sales_report.html';

    private array $clients = [];

    private array $client_groups = [];

    private array $invoiceData = [];

    private array $paymentData = [];

    /** @var array<string, string> Y-m => display label (e.g. "January-2026") */
    private array $monthAxis = [];

    private ?\Carbon\Carbon $monthAxisStart = null;

    private ?\Carbon\Carbon $monthAxisEnd = null;

    private bool $monthlySkipped = false;

    /** @var array<int, array<int, string>> CSV rows for the invoice matrix (PDF use) */
    private array $monthlyInvoiceRows = [];

    private array $monthlyInvoiceHeader = [];

    /** @var array<int, array{currency: string, rows: array<int, array<int, string>>}> */
    private array $monthly_invoice_groups = [];

    /** @var array<int, array<int, string>> CSV rows for the payment matrix (PDF use) */
    private array $monthlyPaymentRows = [];

    private array $monthlyPaymentHeader = [];

    /** @var array<int, array{currency: string, rows: array<int, array<int, string>>}> */
    private array $monthly_payment_groups = [];

    private const MAX_MONTHS = 24;

    public array $report_keys = [
        'client_name',
        'client_number',
        'id_number',
        'invoices',
        'amount',
        'balance',
        'total_taxes',
        'amount_paid',
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
    public function __construct(public Company $company, public array $input) {}

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
        $this->csv->insertOne([ctrans('texts.client_sales_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $query = Client::query()
            ->with('contacts')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $this->filterByUserPermissions($query);

        $clients = $query->orderBy('balance', 'desc')->get();

        $clientIds = $clients->pluck('id')->toArray();
        $this->invoiceData = $this->getInvoiceData($clientIds);
        $clients = $clients->filter(function (Client $client): bool {
            return (int) ($this->invoiceData[$client->id]['count'] ?? 0) > 0;
        });

        $clientIds = $clients->pluck('id')->toArray();
        $this->paymentData = $this->getPaymentData($clientIds);

        foreach ($clients as $client) {
            /** @var \App\Models\Client $client */
            $this->buildRow($client);
        }

        $this->writeCsvTables();

        $this->resolveMonthAxis();
        $this->emitMonthlySections($clients);

        return $this->csv->toString();
    }

    /**
     * Fetch invoice aggregates for every client in a single GROUP BY query.
     * Filters: status_id IN (sent, partial, paid) + the report's date range.
     *
     * @param  array $clientIds
     * @return array<int, array{count: int, amount: float, balance: float, total_taxes: float}>
     */
    private function getInvoiceData(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $query = Invoice::query()
            ->withTrashed()
            ->select('client_id')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('SUM(balance) as total_balance')
            ->selectRaw('SUM(total_taxes) as total_taxes_sum')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('client_id', $clientIds)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->groupBy('client_id');

        $query = $this->addDateRange($query, 'invoices');

        $data = [];

        foreach ($query->get() as $row) {
            $data[$row->client_id] = [ // @phpstan-ignore-line
                'count' => (int) $row->invoice_count, // @phpstan-ignore-line
                'amount' => (float) ($row->total_amount ?? 0),
                'balance' => (float) ($row->total_balance ?? 0),
                'total_taxes' => (float) ($row->total_taxes_sum ?? 0),
            ];
        }

        return $data;
    }

    /**
     * Fetch payment aggregates for every client in a single GROUP BY query.
     *
     * Payments are scoped by their own `date` column so the figure reflects
     * cash actually received in the reporting period, regardless of when the
     * related invoice was issued. Refunded amounts are subtracted.
     *
     * @param  array $clientIds
     * @return array<int, array{amount_paid: float}>
     */
    private function getPaymentData(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $query = Payment::query()
            ->withTrashed()
            ->select('client_id')
            ->selectRaw('SUM(amount - refunded) as total_paid')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('client_id', $clientIds)
            ->whereIn('status_id', [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ])
            ->groupBy('client_id');

        $previous_date_key = $this->date_key;
        $this->date_key = 'date';

        try {
            $query = $this->addDateRange($query, 'payments');
        } finally {
            $this->date_key = $previous_date_key;
        }

        $data = [];

        foreach ($query->get() as $row) {
            $data[$row->client_id] = [ // @phpstan-ignore-line
                'amount_paid' => (float) ($row->total_paid ?? 0), // @phpstan-ignore-line
            ];
        }

        return $data;
    }

    /**
     * Build a row using pre-fetched aggregate data from getInvoiceData()
     * and getPaymentData().
     */
    private function buildRow(Client $client): array
    {
        $invoiceData = $this->invoiceData[$client->id] ?? ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'total_taxes' => 0.0];
        $paymentData = $this->paymentData[$client->id] ?? ['amount_paid' => 0.0];

        $item = [
            $client->present()->name(),
            $client->number,
            $client->id_number,
            $invoiceData['count'],
            Number::formatMoney($invoiceData['amount'], $client),
            Number::formatMoney($invoiceData['balance'], $client),
            Number::formatMoney($invoiceData['total_taxes'], $client),
            Number::formatMoney($paymentData['amount_paid'], $client),
        ];

        $this->storeClientRow($client->currency()->code, $item);

        return $item;
    }

    private function storeClientRow(string $currency_code, array $row): void
    {
        $this->clients[] = $row;

        if (!isset($this->client_groups[$currency_code])) {
            $this->client_groups[$currency_code] = [
                'currency' => $currency_code,
                'clients' => [],
            ];
        }

        $this->client_groups[$currency_code]['clients'][] = $row;
    }

    private function writeCsvTables(): void
    {
        if (count($this->client_groups) <= 1) {
            $this->csv->insertOne($this->buildHeader());

            foreach ($this->clients as $row) {
                $this->csv->insertOne($row);
            }

            return;
        }

        foreach (array_values($this->client_groups) as $index => $group) {
            if ($index > 0) {
                $this->csv->insertOne([]);
            }

            $this->csv->insertOne([ctrans('texts.currency'), $group['currency']]);
            $this->csv->insertOne($this->buildHeader());

            foreach ($group['clients'] as $row) {
                $this->csv->insertOne($row);
            }
        }
    }

    /**
     * Build the month axis spanning the resolved date range.
     *
     * The axis is keyed by `Y-m` (sortable) with the locale-translated display
     * label as value (e.g. "January-2026"). When the range exceeds 24 months,
     * the lower bound is clipped so the axis covers the most recent 24 months
     * ending at the user's selected end date. Skips entirely for `all` or
     * unresolved ranges.
     */
    private function resolveMonthAxis(): void
    {
        $dateRange = $this->input['date_range'] ?? '';
        $unresolved = $dateRange === 'all' || $this->start_date === 'All available data' || empty($this->start_date) || empty($this->end_date);

        if ($unresolved) {
            // No explicit range: derive an end date from the most recent
            // invoice or payment in the company, then clip the lower bound
            // to the standard 24-month window. Skip only when there's no
            // data at all.
            $maxInvoice = Invoice::query()->withTrashed()
                ->where('company_id', $this->company->id)->where('is_deleted', 0)->max('date');
            $maxPayment = Payment::query()->withTrashed()
                ->where('company_id', $this->company->id)->where('is_deleted', 0)->max('date');

            $max = max((string) $maxInvoice, (string) $maxPayment);

            if (! $max) {
                $this->monthlySkipped = true;
                return;
            }

            try {
                $end = \Carbon\Carbon::parse($max)->endOfMonth();
                $cursor = $end->copy()->subMonths(self::MAX_MONTHS - 1)->startOfMonth();
            } catch (\Throwable) {
                $this->monthlySkipped = true;
                return;
            }
        } else {
            try {
                $cursor = \Carbon\Carbon::parse($this->start_date)->startOfMonth();
                $end = \Carbon\Carbon::parse($this->end_date)->endOfMonth();
            } catch (\Throwable) {
                $this->monthlySkipped = true;
                return;
            }

            if ($cursor->greaterThan($end)) {
                $this->monthlySkipped = true;
                return;
            }
        }

        // Clip the lower bound so the axis spans at most MAX_MONTHS, anchored
        // to the user's selected end date.
        $earliest = $end->copy()->subMonths(self::MAX_MONTHS - 1)->startOfMonth();
        if ($cursor->lessThan($earliest)) {
            $cursor = $earliest;
        }

        $this->monthAxisStart = $cursor->copy();
        $this->monthAxisEnd = $end->copy();

        $axis = [];
        $locale = $this->company->locale();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->format('Y-m');
            $axis[$key] = $cursor->copy()->locale($locale)->translatedFormat('F-Y');
            $cursor->addMonth();
        }

        $this->monthAxis = $axis;
    }

    /**
     * Aggregate invoice amounts by (client_id, period) — period is `Y-m`.
     *
     * @param  array<int, int> $clientIds
     * @return array<int, array<string, float>> [client_id => [Y-m => amount]]
     */
    private function getInvoiceMonthlyMatrix(array $clientIds): array
    {
        if (empty($clientIds) || empty($this->monthAxis) || ! $this->monthAxisStart || ! $this->monthAxisEnd) {
            return [];
        }

        $period = "DATE_FORMAT(invoices.date, '%Y-%m')";

        $query = Invoice::query()
            ->withTrashed()
            ->select('client_id')
            ->selectRaw("{$period} as period")
            ->selectRaw('SUM(amount) as total_amount')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('client_id', $clientIds)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->whereBetween('invoices.date', [
                $this->monthAxisStart->format('Y-m-d'),
                $this->monthAxisEnd->format('Y-m-d'),
            ])
            ->groupBy('client_id')
            ->groupByRaw($period);

        $matrix = [];

        foreach ($query->get() as $row) {
            $matrix[$row->client_id][$row->period] = (float) ($row->total_amount ?? 0); // @phpstan-ignore-line
        }

        return $matrix;
    }

    /**
     * Aggregate net payment amounts (amount - refunded) by (client_id, period).
     *
     * @param  array<int, int> $clientIds
     * @return array<int, array<string, float>> [client_id => [Y-m => amount]]
     */
    private function getPaymentMonthlyMatrix(array $clientIds): array
    {
        if (empty($clientIds) || empty($this->monthAxis) || ! $this->monthAxisStart || ! $this->monthAxisEnd) {
            return [];
        }

        $period = "DATE_FORMAT(payments.date, '%Y-%m')";

        $query = Payment::query()
            ->withTrashed()
            ->select('client_id')
            ->selectRaw("{$period} as period")
            ->selectRaw('SUM(amount - refunded) as total_paid')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('client_id', $clientIds)
            ->whereIn('status_id', [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ])
            ->whereBetween('payments.date', [
                $this->monthAxisStart->format('Y-m-d'),
                $this->monthAxisEnd->format('Y-m-d'),
            ])
            ->groupBy('client_id')
            ->groupByRaw($period);

        $matrix = [];

        foreach ($query->get() as $row) {
            $matrix[$row->client_id][$row->period] = (float) ($row->total_paid ?? 0); // @phpstan-ignore-line
        }

        return $matrix;
    }

    /**
     * Emit invoice and payment monthly sections to the CSV. Each section is a
     * pivot table: clients down the Y axis, months across the X axis. Clients
     * with no activity in a section are omitted from that section's row set.
     */
    private function emitMonthlySections(\Illuminate\Database\Eloquent\Collection $clients): void
    {
        if ($this->monthlySkipped) {
            $this->csv->insertOne([]);
            $this->csv->insertOne([ctrans('texts.monthly_breakdown_skipped')]);
            return;
        }

        if (empty($this->monthAxis) || $clients->isEmpty()) {
            return;
        }

        $clientIds = $clients->pluck('id')->toArray();
        $invoiceMatrix = $this->getInvoiceMonthlyMatrix($clientIds);
        $paymentMatrix = $this->getPaymentMonthlyMatrix($clientIds);
        $monthlyClients = $this->sortClientsForMonthlySections($clients);

        $this->emitMatrixSection($monthlyClients, $invoiceMatrix, ctrans('texts.invoices_by_month'), $this->monthlyInvoiceRows, $this->monthly_invoice_groups, $this->monthlyInvoiceHeader);
        $this->emitMatrixSection($monthlyClients, $paymentMatrix, ctrans('texts.payments_by_month'), $this->monthlyPaymentRows, $this->monthly_payment_groups, $this->monthlyPaymentHeader);
    }

    private function sortClientsForMonthlySections(\Illuminate\Database\Eloquent\Collection $clients): \Illuminate\Database\Eloquent\Collection
    {
        return $clients
            ->sortBy(function (Client $client): string {
                return $client->present()->name();
            }, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Trim only leading months that have no data anywhere in this section.
     *
     * @param array<int, array<string, float>> $matrix
     * @return array<string, string>
     */
    private function trimLeadingEmptyMonthAxis(array $matrix): array
    {
        foreach (array_keys($this->monthAxis) as $index => $ym) {
            foreach ($matrix as $cells) {
                if (array_key_exists($ym, $cells)) {
                    return array_slice($this->monthAxis, $index, null, true);
                }
            }
        }

        return [];
    }

    /**
     * @param array<int, array<string, float>> $matrix
     * @param array<int, array<int, string>>   $bag    Captured by reference for PDF use.
     * @param array<int, array{currency: string, rows: array<int, array<int, string>>}> $groupBag
     * @param array<int, string> $headerBag
     */
    private function emitMatrixSection(\Illuminate\Database\Eloquent\Collection $clients, array $matrix, string $title, array &$bag, array &$groupBag, array &$headerBag): void
    {
        $this->csv->insertOne([]);
        $this->csv->insertOne([$title]);

        $monthAxis = $this->trimLeadingEmptyMonthAxis($matrix);
        $headerBag = array_values($monthAxis);
        $header = array_merge([ctrans('texts.client_name')], $headerBag);
        $rowsByCurrency = [];

        foreach ($clients as $client) {
            /** @var \App\Models\Client $client */
            $cells = $matrix[$client->id] ?? [];

            if (empty($cells)) {
                continue;
            }

            /** @var array<int, string> $row */
            $row = [(string) $client->present()->name()];

            foreach (array_keys($monthAxis) as $ym) {
                $row[] = isset($cells[$ym])
                    ? Number::formatMoney($cells[$ym], $client)
                    : '';
            }

            $currency_code = (string) $client->currency()->code;

            if (! isset($rowsByCurrency[$currency_code])) {
                $rowsByCurrency[$currency_code] = [
                    'currency' => $currency_code,
                    'rows' => [],
                ];
            }

            $rowsByCurrency[$currency_code]['rows'][] = $row;
            $bag[] = $row;
        }

        $groupBag = array_values($rowsByCurrency);

        if (count($rowsByCurrency) <= 1) {
            $this->csv->insertOne($header);

            foreach ($bag as $row) {
                $this->csv->insertOne($row);
            }

            return;
        }

        foreach (array_values($rowsByCurrency) as $index => $group) {
            if ($index > 0) {
                $this->csv->insertOne([]);
            }

            $this->csv->insertOne([ctrans('texts.currency'), $group['currency']]);
            $this->csv->insertOne($header);

            foreach ($group['rows'] as $row) {
                $this->csv->insertOne($row);
            }
        }
    }

    public function getPdf()
    {
        $user = isset($this->input['user_id']) ? User::withTrashed()->find($this->input['user_id']) : $this->company->owner();

        $user_name = $user ? $user->present()->name() : '';

        $data = [
            'clients' => $this->clients,
            'client_groups' => array_values($this->client_groups),
            'company_logo' => $this->company->present()->logo(),
            'company_name' => $this->company->present()->name(),
            'created_on' => $this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale()),
            'created_by' => $user_name,
            'monthly_header' => array_values($this->monthAxis),
            'monthly_invoice_header' => $this->monthlyInvoiceHeader,
            'monthly_payment_header' => $this->monthlyPaymentHeader,
            'monthly_invoices' => $this->monthlyInvoiceRows,
            'monthly_payments' => $this->monthlyPaymentRows,
            'monthly_invoice_groups' => array_values($this->monthly_invoice_groups),
            'monthly_payment_groups' => array_values($this->monthly_payment_groups),
            'monthly_skipped' => $this->monthlySkipped,
        ];

        $ts = new TemplateService();

        $ts_instance = $ts->setCompany($this->company)
                    ->setData($data)
                    ->setRawTemplate(file_get_contents(resource_path($this->template)))
                    ->parseNinjaBlocks()
                    ->save();

        return $ts_instance->getPdf();
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
