<?php

namespace App\Services\Report;

use Carbon\Carbon;
use App\Utils\Ninja;
use App\Utils\Number;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Activity;
use App\Utils\Translator;
use App\Libraries\MultiDB;
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Query\Builder;

class EInvoiceReport extends BaseExport
{
    use MakesDates;

    public Writer $csv;

    public string $date_key = 'date';

    private string $template = '/views/templates/reports/einvoice_report.html';

    public array $report_keys = [
        'invoice_number',
        'client_name',
        'client_number',
        'invoice_date',
        'invoice_amount',
        'einvoice_status',
        'expense_number',
        'expense_date',
        'expense_amount',
    ];

    /**
     * @param array $input
     * [
     *     'date_range',
     *     'start_date',
     *     'end_date',
     *     'clients',
     *     'client_id',
     * ]
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
        $this->csv->insertOne([ctrans('texts.einvoice_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'), ' ', $this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        // Get all invoices with e-invoice status
        $query = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->with(['client']);

        $query = $this->addDateRange($query, 'invoices');

        $invoices = $query->cursor();

        // Process invoices
        foreach ($invoices as $invoice) {
            /** @var Invoice $invoice */
            $einvoiceStatus = $invoice->backup?->guid ? 'Sent via e-invoicing' : 'Not sent via e-invoicing';

            $this->csv->insertOne([
                $invoice->number,
                $invoice->client->present()->name(),
                $invoice->client->number,
                $this->translateDate($invoice->date, $this->company->date_format(), $this->company->locale()),
                Number::formatMoney($invoice->amount, $this->company),
                $einvoiceStatus,
                '', // Empty for expenses
                '', // Empty for expenses
                '', // Empty for expenses
            ]);
        }

        $this->date_key = 'created_at';

        // Get all expenses received via e-invoicing
        $query = Activity::query()
            ->where('company_id', $this->company->id)
            ->where('activity_type_id', 148) // Received via e-invoicing
            ->with('expense');

        $query = $this->addDateRange($query, 'activities');

        $expenseActivityIds = $query->pluck('expense_id')
                                    ->toArray();

        $expenses = Expense::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->whereIn('id', $expenseActivityIds)
            ->cursor();

        // Process expenses
        foreach ($expenses as $expense) {
            $this->csv->insertOne([
                '', // Empty for invoices
                '', // Empty for invoices
                '', // Empty for invoices
                '', // Empty for invoices
                '', // Empty for invoices
                '', // Empty for invoices
                $expense->number,
                $this->translateDate($expense->date, $this->company->date_format(), $this->company->locale()),
                Number::formatMoney($expense->amount, $this->company),
            ]);
        }

        return $this->csv->toString();
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
