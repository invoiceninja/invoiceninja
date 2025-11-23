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

use Carbon\Carbon;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use App\Export\CSV\BaseExport;
use App\Models\TransactionEvent;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Services\Template\TemplateService;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Services\Report\TaxPeriod\TaxSummary;
use App\Services\Report\TaxPeriod\TaxDetail;
use App\Services\Report\TaxPeriod\InvoiceReportRow;
use App\Services\Report\TaxPeriod\InvoiceItemReportRow;
use App\Services\Report\TaxPeriod\RegionalTaxCalculator;
use App\Services\Report\TaxPeriod\RegionalTaxCalculatorFactory;

class TaxPeriodReport extends BaseExport
{
    use MakesDates;

    private Spreadsheet $spreadsheet;

    private array $data = [];

    private string $currency_format;

    private string $number_format;

    private bool $cash_accounting = false;

    private ?RegionalTaxCalculator $regional_calculator = null;

    /**
        @param array $input
        [
            'date_range',
            'start_date',
            'end_date',
            'client_id',
            'is_income_billed',
        ]
        @param bool $skip_initialization Skip prophylactic transaction event creation (primarily for testing)
    */
    public function __construct(public Company $company, public array $input, private bool $skip_initialization = false)
    {
        $this->regional_calculator = RegionalTaxCalculatorFactory::create($company);
    }

    public function run()
    {
        // nlog($this->input);
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->spreadsheet = new Spreadsheet();

        return
                $this->boot()
                    ->writeToSpreadsheet()
                    ->getXlsFile();

    }

    /**
     * boot the main methods
     * that initialize the report
     *
     * @return self
     */
    public function boot(): self
    {
        $this->setAccountingType()
            ->setCurrencyFormat()
            ->calculateDateRange();

        if (!$this->skip_initialization) {
            $this->initializeData();
        }

        $this->buildData();

        return $this;
    }

    /**
     * setAccountingType
     *
     * When input var is TRUE, this means that we are dealing with accrual accounting.
     * When input var is FALSE, this means that we are dealing with cash accounting.
     *
     * @return self
     */
    private function setAccountingType(): self
    {
        $this->cash_accounting = $this->input['is_income_billed'] ? false : true;

        return $this;
    }

    /**
     * initializeData
     *
     * Ensure our dataset has the appropriate transaction events.
     * This runs prophylactically to ensure all invoices have transaction state.
     *
     * @return self
     */
    private function initializeData(): self
    {

        $q = Invoice::withTrashed()
            ->where('company_id', $this->company->id)
            ->whereIn('status_id', [2,3,4,5,6])
            ->whereBetween('date', ['1970-01-01', $this->end_date])
            // ->whereDoesntHave('transaction_events'); //filter by no transaction events for THIS month.
            ->whereDoesntHave('transaction_events', function ($query) {
                $query->where('period', $this->end_date);
            });

        $q->cursor()
        ->each(function ($invoice) {

            (new InvoiceTransactionEventEntry())->run($invoice, $this->end_date);


            if (in_array($invoice->status_id, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIAL])) {

                //Harvest point in time records for cash payments.
                \App\Models\Paymentable::where('paymentable_type', 'invoices')
                    ->whereIn('payment_id', $invoice->payments->pluck('id'))
                    ->get()
                    ->groupBy(function ($paymentable) {
                        return $paymentable->paymentable_id . '-' . \Carbon\Carbon::parse($paymentable->created_at)->format('Y-m');
                    })
                    ->map(function ($group) {
                        return $group->first();
                    })->each(function ($pp) {
                        (new InvoiceTransactionEventEntryCash())->run($pp->paymentable, \Carbon\Carbon::parse($pp->created_at)->startOfMonth()->format('Y-m-d'), \Carbon\Carbon::parse($pp->created_at)->endOfMonth()->format('Y-m-d'));
                    });

            }
        });

        $ii = Invoice::withTrashed()
                ->whereHas('transaction_events', function ($query) {
                    $query->where('period', '<=', $this->end_date);
                })
                ->where(function ($q) {
                    $q->whereIn('status_id', [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])
                    ->orWhere('is_deleted', true);
                })
                ->whereDoesntHave('transaction_events', function ($query) {
                    $query->where('period', $this->end_date)
                        ->whereIn('metadata->tax_report->tax_summary->status', ['cancelled', 'deleted']);
                });

                $ii->cursor()
                ->each(function ($invoice) {

                    // Iterate through each month between start_date and end_date
                    // $current_date = Carbon::parse($this->start_date);
                    // $end_date_carbon = Carbon::parse($this->end_date);

                    // while ($current_date->lte($end_date_carbon)) {
                    //     $last_day_of_month = $current_date->copy()->endOfMonth()->format('Y-m-d');
                    //     (new InvoiceTransactionEventEntry())->run($invoice, $last_day_of_month);
                    //     $current_date->addMonth();
                    // }

                    (new InvoiceTransactionEventEntry())->run($invoice, $this->end_date);

                });

        return $this;
    }

    /**
     * Build the query for fetching transaction events
     */
    private function resolveQuery(): Builder
    {

        $query = Invoice::query()
            ->withTrashed()
            ->with('transaction_events')
            ->where('company_id', $this->company->id);

        if ($this->cash_accounting) { //cash

            $query->whereIn('status_id', [2,3,4,5,6])
                ->whereHas('transaction_events', function ($query) {
                    $query->where(function ($sub_q){
                        $sub_q->where('event_id', '!=', TransactionEvent::INVOICE_UPDATED)
                            ->orWhere('metadata->tax_report->tax_summary->status', 'reversed');

                    })->whereBetween('period', [$this->start_date, $this->end_date]);
                });

        } else { //accrual

            $query->whereIn('status_id', [2,3,4,5])
                ->whereHas('transaction_events', function ($query) {
                    $query->where('event_id', TransactionEvent::INVOICE_UPDATED)
                        ->whereBetween('period', [$this->start_date, $this->end_date]);
                });

        }

        $query->orderBy('balance', 'desc');

        return $query;
    }

    /**
     * calculateDateRange
     *
     * We only support dates as of the end of the last month.
     * @return self
     */
    private function calculateDateRange(): self
    {

        switch ($this->input['date_range']) {
            case 'last7':
            case 'last30':
            case 'this_month':
            case 'last_month':
                $this->start_date = now()->startOfMonth()->subMonth()->format('Y-m-d');
                $this->end_date = now()->startOfMonth()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'this_quarter':
                $this->start_date = (new \Carbon\Carbon('0 months'))->startOfQuarter()->format('Y-m-d');
                $this->end_date = (new \Carbon\Carbon('0 months'))->endOfQuarter()->format('Y-m-d');
                break;
            case 'last_quarter':
                $this->start_date = (new \Carbon\Carbon('-3 months'))->startOfQuarter()->format('Y-m-d');
                $this->end_date = (new \Carbon\Carbon('-3 months'))->endOfQuarter()->format('Y-m-d');
                break;
            case 'last365_days':
                $this->start_date = now()->startOfDay()->subDays(365)->format('Y-m-d');
                $this->end_date = now()->startOfDay()->format('Y-m-d');
                break;
            case 'this_year':

                $first_month_of_year = $this->company->first_month_of_year ?? 1;
                $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

                if (now()->lt($fin_year_start)) {
                    $fin_year_start->subYearNoOverflow();
                }

                $this->start_date = $fin_year_start->format('Y-m-d');
                $this->end_date = $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d');
                break;
            case 'last_year':

                $first_month_of_year = $this->company->first_month_of_year ?? 1;
                $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);
                $fin_year_start->subYearNoOverflow();

                if (now()->subYear()->lt($fin_year_start)) {
                    $fin_year_start->subYearNoOverflow();
                }

                $this->start_date = $fin_year_start->format('Y-m-d');
                $this->end_date = $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d');

                break;
            case 'custom':

                try {
                    $custom_start_date = Carbon::parse($this->input['start_date']);
                    $custom_end_date = Carbon::parse($this->input['end_date']);
                } catch (\Exception $e) {
                    $custom_start_date = now()->startOfYear();
                    $custom_end_date = now();
                }

                $this->start_date = $custom_start_date->format('Y-m-d');
                $this->end_date = $custom_end_date->format('Y-m-d');
                break;
            case 'all':
            default:
                $this->start_date = now()->startOfYear()->format('Y-m-d');
                $this->end_date = now()->format('Y-m-d');
        }

        return $this;
    }

    public function setCurrencyFormat()
    {
        $currency = $this->company->currency();

        $formatted = number_format(90.00, $currency->precision, $currency->decimal_separator, $currency->thousand_separator);
        $formatted = str_replace('9', '#', $formatted);
        $this->number_format = $formatted;

        $formatted = "{$currency->symbol}{$formatted}";
        $this->currency_format = $formatted;

        return $this;
    }


    private function writeToSpreadsheet()
    {
        $this->createSummarySheet()
            ->createInvoiceSummarySheet()
            ->createInvoiceItemSummarySheet();

        return $this;
    }

    public function createSummarySheet()
    {

        $worksheet = $this->spreadsheet->getActiveSheet();
        $worksheet->setTitle(ctrans('texts.tax_summary'));

        // Add summary data and formatting here if needed
        // For now, this sheet is empty but could be populated with summary statistics

        return $this;
    }

    /**
     * Create invoice-level summary sheet
     */
    public function createInvoiceSummarySheet()
    {

        $worksheet_title = $this->cash_accounting ? ctrans('texts.cash_accounting') : ctrans('texts.accrual_accounting');

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.invoice')." ".$worksheet_title, 0, 30));
        $worksheet->fromArray($this->data['invoices'], null, 'A1');

        $worksheet->getStyle('B:B')->getNumberFormat()->setFormatCode($this->company->date_format());
        $worksheet->getStyle('C:C')->getNumberFormat()->setFormatCode($this->currency_format);
        $worksheet->getStyle('D:D')->getNumberFormat()->setFormatCode($this->currency_format);
        $worksheet->getStyle('E:E')->getNumberFormat()->setFormatCode($this->currency_format);
        $worksheet->getStyle('F:F')->getNumberFormat()->setFormatCode($this->currency_format);

        return $this;
    }

    /**
     * Create invoice item (tax detail) summary sheet
     */
    public function createInvoiceItemSummarySheet()
    {
        $worksheet_title = $this->cash_accounting ? ctrans('texts.cash_accounting') : ctrans('texts.accrual_accounting');

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.invoice_item')." ".$worksheet_title, 0, 30));
        $worksheet->fromArray($this->data['invoice_items'], null, 'A1');

        $worksheet->getStyle('B:B')->getNumberFormat()->setFormatCode($this->company->date_format());
        $worksheet->getStyle('D:D')->getNumberFormat()->setFormatCode($this->number_format);
        $worksheet->getStyle('E:E')->getNumberFormat()->setFormatCode($this->currency_format);
        $worksheet->getStyle('F:F')->getNumberFormat()->setFormatCode($this->currency_format);

        return $this;
    }


    /**
     * Build report data from transaction events
     */
    private function buildData(): self
    {

        $query = $this->resolveQuery();

        // Initialize with headers
        $this->data['invoices'] = [InvoiceReportRow::getHeaders($this->regional_calculator)];
        $this->data['invoice_items'] = [InvoiceItemReportRow::getHeaders($this->regional_calculator)];

        $query->cursor()->each(function ($invoice) {

            $invoice->transaction_events()
            ->when(!$this->cash_accounting, function ($query) {
                $query->where('event_id', TransactionEvent::INVOICE_UPDATED);
            })
            ->when($this->cash_accounting, function ($query) {
                $query->where(function ($sub_q){
                    $sub_q->where('event_id', '!=', TransactionEvent::INVOICE_UPDATED)
                        ->orWhere('metadata->tax_report->tax_summary->status', 'reversed');

                });

                // $query->where('event_id', '!=', TransactionEvent::INVOICE_UPDATED);
            })
            ->whereBetween('period', [$this->start_date, $this->end_date])
            ->orderBy('timestamp', 'desc')
            ->cursor()
            ->each(function ($event) use ($invoice) {

                /** @var Invoice $invoice */
                $this->processTransactionEvent($event, $invoice);

            });
        });

        return $this;
    }

    /**
     * Process a single transaction event and add to report data
     */
    private function processTransactionEvent(TransactionEvent $event, Invoice $invoice): void
    {
        $tax_summary = TaxSummary::fromMetadata($event->metadata->tax_report->tax_summary);

        // Build and add invoice row
        $invoice_row_builder = new InvoiceReportRow(
            $invoice,
            $event,
            $tax_summary,
            $this->regional_calculator
        );

        $this->data['invoices'][] = $invoice_row_builder->build();

        // Build and add invoice item rows for each tax detail
        foreach ($event->metadata->tax_report->tax_details as $tax_detail_data) {
            $tax_detail = TaxDetail::fromMetadata($tax_detail_data);

            $item_row_builder = new InvoiceItemReportRow(
                $invoice,
                $tax_detail,
                $tax_summary->status,
                $this->regional_calculator
            );

            $this->data['invoice_items'][] = $item_row_builder->buildForStatus();
        }
    }

    public function getData()
    {
        return $this->data;
    }

    public function getXlsFile()
    {

        $tempFile = tempnam(sys_get_temp_dir(), 'tax_report_');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($this->spreadsheet);
        $writer->save($tempFile);

        $fileContent = file_get_contents($tempFile);

        unlink($tempFile);

        return $fileContent;

    }

}
