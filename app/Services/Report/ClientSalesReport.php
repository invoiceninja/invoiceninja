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

    public string $date_key = 'created_at';

    private string $template = '/views/templates/reports/client_sales_report.html';

    private array $clients = [];

    private array $invoiceData = [];

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

        $this->csv->insertOne($this->buildHeader());

        $query = Client::query()
            ->with('contacts')
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0);

        $query = $this->filterByUserPermissions($query);

        $clients = $query->orderBy('balance', 'desc')->get();

        $this->invoiceData = $this->getInvoiceData($clients->pluck('id')->toArray());

        foreach ($clients as $client) {
            /** @var \App\Models\Client $client */
            $this->csv->insertOne($this->buildRow($client));
        }

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
            ->select('client_id')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('SUM(amount) as total_amount')
            ->selectRaw('SUM(balance) as total_balance')
            ->selectRaw('SUM(total_taxes) as total_taxes_sum')
            ->where('company_id', $this->company->id)
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
     * Build a row using pre-fetched aggregate data from getInvoiceData().
     */
    private function buildRow(Client $client): array
    {
        $invoiceData = $this->invoiceData[$client->id] ?? ['count' => 0, 'amount' => 0.0, 'balance' => 0.0, 'total_taxes' => 0.0];

        $amount = $invoiceData['amount'];
        $balance = $invoiceData['balance'];

        $item = [
            $client->present()->name(),
            $client->number,
            $client->id_number,
            $invoiceData['count'],
            Number::formatMoney($amount, $this->company),
            Number::formatMoney($balance, $this->company),
            Number::formatMoney($invoiceData['total_taxes'], $this->company),
            Number::formatMoney($amount - $balance, $this->company),
        ];

        $this->clients[] = $item;

        return $item;
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

    public function buildHeader(): array
    {
        $header = [];

        foreach ($this->input['report_keys'] as $value) {

            $header[] = ctrans("texts.{$value}");
        }

        return $header;
    }

}
