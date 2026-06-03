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

use Carbon\Carbon;
use App\Utils\Ninja;
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
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Listeners\Invoice\InvoiceTransactionEventEntry;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Services\Report\TaxPeriod\TaxSummary;
use App\Services\Report\TaxPeriod\TaxDetail;
use App\Services\Report\TaxPeriod\InvoiceReportRow;
use App\Services\Report\TaxPeriod\InvoiceItemReportRow;
use App\Services\Report\TaxPeriod\RegionalTaxCalculator;
use App\Services\Report\TaxPeriod\SalesBreakdownCalculator;
use App\Services\Report\TaxPeriod\RegionalTaxCalculatorFactory;
use App\DataMapper\TaxReport\PaymentHistory;

class TaxPeriodReport extends BaseExport
{
    use MakesDates;

    private Spreadsheet $spreadsheet;

    private array $data = [];

    private string $currency_format;

    private string $number_format;

    private string $date_format;

    private bool $cash_accounting = false;

    private ?RegionalTaxCalculator $regional_calculator = null;

    private bool $report_context_initialized = false;

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
        $this->prepareReportContext();
        $this->spreadsheet = new Spreadsheet();

        return
                $this->boot()
                    ->writeToSpreadsheet()
                    ->getXlsFile();

    }

    private function prepareReportContext(): void
    {
        if ($this->report_context_initialized) {
            return;
        }

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());

        $translator = app('translator');
        $translator->replace(Ninja::transformTranslations($this->company->settings));

        $this->report_context_initialized = true;
    }

    /**
     * boot the main methods
     * that initialize the report
     *
     * @return self
     */
    public function boot(): self
    {
        $this->prepareReportContext();

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

        // First sweep. Active SENT/PARTIAL/PAID invoices always need an event for
        // end_date if missing. CANCELLED/REVERSED/deleted invoices only need
        // processing when no terminal-state event exists yet — once that event is
        // written they would otherwise be reprocessed on every report run because
        // InvoiceTransactionEventEntry::run early-returns without writing a new
        // event for end_date when status already matches.
        $q = Invoice::withTrashed()
            ->with('payments')
            ->where('company_id', $this->company->id)
            ->whereBetween('date', ['1970-01-01', $this->end_date])
            ->where(function ($q) {
                $q->where(function ($q) {
                    $q->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
                      ->where('is_deleted', false);
                })->orWhere(function ($q) {
                    $q->where(function ($q) {
                        $q->whereIn('status_id', [Invoice::STATUS_CANCELLED, Invoice::STATUS_REVERSED])
                          ->orWhere('is_deleted', true);
                    })->whereDoesntHave('transaction_events', function ($query) {
                        $query->whereIn('metadata->tax_report->tax_summary->status', ['cancelled', 'reversed', 'deleted']);
                    });
                });
            })
            ->whereDoesntHave('transaction_events', function ($query) {
                $query->where('period', $this->end_date);
            });

        $this->streamQuery($q)
        ->each(function ($invoice) {

            (new InvoiceTransactionEventEntry())->run($invoice, $this->end_date);


            if (in_array($invoice->status_id, [Invoice::STATUS_PAID, Invoice::STATUS_PARTIAL])) {

                //Harvest point in time records for cash payments.
                \App\Models\Paymentable::where('paymentable_type', 'invoices')
                    ->where('paymentable_id', $invoice->id)
                    ->whereIn('payment_id', $invoice->payments->pluck('id'))
                    ->get()
                    ->groupBy(function ($paymentable) {
                        return $paymentable->paymentable_id . '-' . \Carbon\Carbon::parse($paymentable->created_at)->format('Y-m');
                    })
                    ->map(function ($group) {
                        return $group->first();
                    })->each(function (\App\Models\Paymentable $pp) use ($invoice) {
                        // Pass $invoice directly to avoid morph lazy-load on $pp->paymentable.
                        (new InvoiceTransactionEventEntryCash())->run($invoice, \Carbon\Carbon::parse($pp->created_at)->startOfMonth()->format('Y-m-d'), \Carbon\Carbon::parse($pp->created_at)->endOfMonth()->format('Y-m-d'));
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

        $this->streamQuery($ii)
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

        $this->backfillClassificationBreakdown(); // TEMP disabled to test perf impact
        $this->backfillSalesBreakdown();

        return $this;
    }

    /**
     * Lazy backfill: any TransactionEvent within the report's window whose
     * metadata lacks tax_details_by_classification has the breakdown
     * recomputed in-place from the persisted aggregate tax_details and the
     * current invoice's line items.
     *
     * Caveat: historical events are computed against the *current* line
     * composition, so accuracy depends on the invoice not having been
     * reclassified after the event period. This is consistent with the
     * snapshot model used elsewhere in the report.
     */
    private function backfillClassificationBreakdown(): void
    {
        $this->streamQuery(TransactionEvent::query()
            ->whereBetween('period', [$this->start_date, $this->end_date])
            ->whereNull('metadata->tax_report->tax_details_by_classification')
            ->with('invoice'))
            ->each(function (TransactionEvent $event) {
                $invoice = $event->invoice;
                if (!$invoice) {
                    return;
                }

                $aggregate = collect($event->metadata->tax_report->tax_details ?? [])
                    ->map(function ($detail) {
                        if (is_array($detail)) {
                            return $detail;
                        }
                        if (is_object($detail) && method_exists($detail, 'toArray')) {
                            return $detail->toArray();
                        }
                        return (array) $detail;
                    })
                    ->all();

                $multiplier = $this->multiplierForEvent($event);

                $by_classification = \App\Services\Report\TaxPeriod\TaxClassificationCalculator::calculate(
                    $invoice,
                    $multiplier,
                    $aggregate,
                );

                $metadata = $event->metadata;
                $metadata->tax_report->tax_details_by_classification = $by_classification;
                $event->metadata = $metadata;
                $event->saveQuietly();
            });
    }


    /**
     * Conservatively forward-fill sales_breakdown only when the persisted event
     * still matches the current invoice snapshot. Anything that looks like a
     * historical correction or post-event invoice edit remains legacy-sourced.
     */
    private function backfillSalesBreakdown(): void
    {
        $this->streamQuery(TransactionEvent::query()
            ->whereBetween('period', [$this->start_date, $this->end_date])
            ->whereIn('event_id', [TransactionEvent::INVOICE_UPDATED, TransactionEvent::PAYMENT_CASH])
            ->whereNull('metadata->tax_report->sales_breakdown')
            ->with('invoice'))
            ->each(function (TransactionEvent $event) {
                $invoice = $event->invoice;

                if (!$invoice || !$this->canBackfillSalesBreakdown($event, $invoice)) {
                    return;
                }

                $metadata = $event->metadata;
                $metadata->tax_report->sales_breakdown = SalesBreakdownCalculator::calculate(
                    $invoice,
                    $this->salesBreakdownMultiplierForEvent($event),
                );
                $event->metadata = $metadata;
                $event->saveQuietly();
            });
    }

    private function canBackfillSalesBreakdown(TransactionEvent $event, Invoice $invoice): bool
    {
        $status = $event->metadata->tax_report->tax_summary->status ?? 'updated';

        if (in_array($status, ['delta', 'adjustment', 'restored'], true)) {
            return false;
        }

        if (round((float) $event->invoice_amount, 2) !== round((float) $invoice->amount, 2)) {
            return false;
        }

        $current_status = $invoice->is_deleted ? 7 : (int) $invoice->status_id;
        if ((int) $event->invoice_status !== $current_status) {
            return false;
        }

        if ($invoice->updated_at && $event->timestamp && $this->timestampValue($invoice->updated_at) > (int) $event->timestamp) {
            return false;
        }

        return $event->event_id !== TransactionEvent::PAYMENT_CASH || $this->periodPaymentAmount($event) > 0.0;
    }

    private function timestampValue(mixed $value): int
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->timestamp;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return \Carbon\Carbon::parse($value)->timestamp;
    }

    private function salesBreakdownMultiplierForEvent(TransactionEvent $event): float
    {
        $status = $event->metadata->tax_report->tax_summary->status ?? 'updated';

        return match ($status) {
            'reversed' => $this->eventPaidRatio($event) * -1,
            'cancelled' => $this->eventPaidRatio($event),
            'deleted' => -1.0,
            default => $event->event_id === TransactionEvent::PAYMENT_CASH
                ? $this->periodPaymentAmount($event) / max((float) $event->invoice_amount, 1)
                : 1.0,
        };
    }

    private function eventPaidRatio(TransactionEvent $event): float
    {
        $invoice_amount = (float) $event->invoice_amount;

        if ($invoice_amount <= 0) {
            return 0.0;
        }

        return (float) $event->invoice_paid_to_date / $invoice_amount;
    }

    private function periodPaymentAmount(TransactionEvent $event): float
    {
        $payment_history = $event->metadata->tax_report->payment_history ?? null;

        if (!$payment_history) {
            return 0.0;
        }

        return (float) $payment_history->sum(fn (PaymentHistory $payment): float => $payment->amount - $payment->refunded);
    }

    /**
     * Reverse-engineer the multiplier that was applied when the event was
     * recorded so the recomputed by-classification snapshot ties back to
     * the persisted aggregate tax_details.
     */
    private function multiplierForEvent(TransactionEvent $event): float
    {
        $status = $event->metadata->tax_report->tax_summary->status ?? 'updated';

        $invoice = $event->invoice;
        if (!$invoice) {
            return 1.0;
        }

        $paid_ratio = ($invoice->amount > 0) ? ($invoice->paid_to_date / $invoice->amount) : 0.0;

        return match ($status) {
            'reversed' => $paid_ratio * -1,
            'cancelled' => $paid_ratio,
            'deleted' => -1.0,
            default => $event->event_id === TransactionEvent::PAYMENT_CASH ? $paid_ratio : 1.0,
        };
    }

    /**
     * Build the query for fetching transaction events
     */
    private function resolveQuery(): Builder
    {

        $query = Invoice::query()
            ->withTrashed()
            ->with('client')
            ->where('company_id', $this->company->id);

        if ($this->cash_accounting) { //cash

            $query->whereIn('status_id', [2,3,4,5,6])
                ->whereHas('transaction_events', function ($query) {
                    $query->where(function ($sub_q) {
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

        $decimal_places = str_repeat('0', $currency->precision);
        $this->number_format = '#,##0' . ($currency->precision > 0 ? '.' . $decimal_places : '');
        $this->currency_format = '"' . $currency->symbol . '"' . $this->number_format;
        $this->date_format = $this->convertPhpDateFormatToExcel($this->company->date_format());

        return $this;
    }

    /**
     * Convert a PHP date format string to an Excel-compatible format code.
     *
     * @param string $php_format PHP date() format string (e.g. 'd/m/Y', 'M j, Y')
     * @return string Excel format code (e.g. 'dd/mm/yyyy', 'mmm d, yyyy')
     */
    private function convertPhpDateFormatToExcel(string $php_format): string
    {
        $replacements = [
            'Y' => 'yyyy',
            'y' => 'yy',
            'F' => 'mmmm',
            'M' => 'mmm',
            'm' => 'mm',
            'n' => 'm',
            'd' => 'dd',
            'j' => 'd',
            'D' => 'ddd',
            'l' => 'dddd',
        ];

        return strtr($php_format, $replacements);
    }


    private function writeToSpreadsheet()
    {
        $this->createFilingOverviewSheet()
            ->createSummarySheet()
            ->createMonthlyFilingSummarySheet()
            ->createStateBreakdownSheet()
            ->createJurisdictionBreakdownSheet()
            ->createCorrectionsReviewSheet()
            ->createInvoiceSummarySheet()
            ->createInvoiceItemSummarySheet();

        return $this;
    }

    public function createFilingOverviewSheet()
    {
        $worksheet = $this->spreadsheet->getActiveSheet();
        $worksheet->setTitle(substr(ctrans('texts.filing_overview'), 0, 31));

        $overview_data = $this->data['filing_overview'] ?? $this->buildFilingOverviewRows();
        $worksheet->fromArray($overview_data, null, 'A1');
        $this->autoSizeColumns($worksheet, $overview_data[0] ?? []);

        return $this;
    }

    public function createSummarySheet()
    {

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(ctrans('texts.tax_summary'));

        $summary_data = $this->data['summary'] ?? $this->buildSummaryRows();
        $worksheet->fromArray($summary_data, null, 'A1');

        $this->formatColumnsByHeaders(
            $worksheet,
            $summary_data[0] ?? [],
            array_merge($this->filingPeriodColumnFormats(), [
                ctrans('texts.tax_rate') => '0.00',
                ctrans('texts.gross_sales') => $this->currency_format,
                ctrans('texts.taxable_amount') => $this->currency_format,
                ctrans('texts.exempt_sales') => $this->currency_format,
                ctrans('texts.non_taxable_sales') => $this->currency_format,
                ctrans('texts.zero_rated_sales') => $this->currency_format,
                ctrans('texts.tax_amount') => $this->currency_format,
                ctrans('texts.invoice_count') => '0',
            ], $this->regionalColumnFormats())
        );
        $this->autoSizeColumns($worksheet, $summary_data[0] ?? []);

        return $this;
    }

    public function createMonthlyFilingSummarySheet()
    {
        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.monthly_filing_summary'), 0, 31));

        $monthly_data = $this->data['monthly_filing_summary'] ?? $this->buildMonthlyFilingSummaryRows();
        $worksheet->fromArray($monthly_data, null, 'A1');

        $this->formatColumnsByHeaders(
            $worksheet,
            $monthly_data[0] ?? [],
            array_merge($this->filingPeriodColumnFormats(), [
                ctrans('texts.gross_sales') => $this->currency_format,
                ctrans('texts.taxable_amount') => $this->currency_format,
                ctrans('texts.exempt_sales') => $this->currency_format,
                ctrans('texts.non_taxable_sales') => $this->currency_format,
                ctrans('texts.zero_rated_sales') => $this->currency_format,
                ctrans('texts.tax_amount') => $this->currency_format,
                ctrans('texts.invoice_count') => '0',
            ])
        );
        $this->autoSizeColumns($worksheet, $monthly_data[0] ?? []);

        return $this;
    }

    public function createStateBreakdownSheet()
    {
        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.state_breakdown'), 0, 31));

        $state_data = $this->data['state_breakdown'] ?? $this->buildStateBreakdownRows();
        $worksheet->fromArray($state_data, null, 'A1');

        $this->formatColumnsByHeaders(
            $worksheet,
            $state_data[0] ?? [],
            array_merge($this->filingPeriodColumnFormats(), [
                ctrans('texts.gross_sales') => $this->currency_format,
                ctrans('texts.taxable_amount') => $this->currency_format,
                ctrans('texts.exempt_sales') => $this->currency_format,
                ctrans('texts.non_taxable_sales') => $this->currency_format,
                ctrans('texts.zero_rated_sales') => $this->currency_format,
                ctrans('texts.tax_amount') => $this->currency_format,
                'State Tax Amount' => $this->currency_format,
                'County Tax Amount' => $this->currency_format,
                'City Tax Amount' => $this->currency_format,
                'District Tax Amount' => $this->currency_format,
                ctrans('texts.invoice_count') => '0',
            ])
        );
        $this->autoSizeColumns($worksheet, $state_data[0] ?? []);

        return $this;
    }

    public function createJurisdictionBreakdownSheet()
    {
        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.jurisdiction_breakdown'), 0, 31));

        $jurisdiction_data = $this->data['jurisdiction_breakdown'] ?? $this->buildJurisdictionBreakdownRows();
        $worksheet->fromArray($jurisdiction_data, null, 'A1');

        $this->formatColumnsByHeaders(
            $worksheet,
            $jurisdiction_data[0] ?? [],
            array_merge($this->filingPeriodColumnFormats(), [
                ctrans('texts.gross_sales') => $this->currency_format,
                ctrans('texts.taxable_amount') => $this->currency_format,
                ctrans('texts.exempt_sales') => $this->currency_format,
                ctrans('texts.non_taxable_sales') => $this->currency_format,
                ctrans('texts.zero_rated_sales') => $this->currency_format,
                ctrans('texts.tax_amount') => $this->currency_format,
                'State Tax Amount' => $this->currency_format,
                'County Tax Amount' => $this->currency_format,
                'City Tax Amount' => $this->currency_format,
                'District Tax Amount' => $this->currency_format,
                ctrans('texts.invoice_count') => '0',
            ])
        );
        $this->autoSizeColumns($worksheet, $jurisdiction_data[0] ?? []);

        return $this;
    }

    public function createCorrectionsReviewSheet()
    {
        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.corrections_review'), 0, 31));

        $correction_data = $this->data['corrections'] ?? $this->buildCorrectionRows();
        $worksheet->fromArray($correction_data, null, 'A1');

        $this->formatColumnsByHeaders(
            $worksheet,
            $correction_data[0] ?? [],
            array_merge($this->filingPeriodColumnFormats(), [
                ctrans('texts.original_tax_period') => $this->date_format,
                ctrans('texts.correction_recorded_period') => $this->date_format,
                ctrans('texts.tax_rate') => '0.00',
                ctrans('texts.gross_sales') => $this->currency_format,
                ctrans('texts.taxable_amount') => $this->currency_format,
                ctrans('texts.exempt_sales') => $this->currency_format,
                ctrans('texts.non_taxable_sales') => $this->currency_format,
                ctrans('texts.zero_rated_sales') => $this->currency_format,
                ctrans('texts.tax_amount') => $this->currency_format,
                ctrans('texts.invoice_count') => '0',
            ], $this->regionalColumnFormats())
        );
        $this->autoSizeColumns($worksheet, $correction_data[0] ?? []);

        return $this;
    }

    /**
     * Create invoice-level summary sheet
     */
    public function createInvoiceSummarySheet()
    {

        $worksheet_title = $this->cash_accounting ? ctrans('texts.cash_accounting') : ctrans('texts.cash_vs_accrual');

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.invoice') . " " . $worksheet_title, 0, 31));
        $worksheet->fromArray($this->data['invoices'], null, 'A1');

        $this->formatColumnsByHeaders($worksheet, $this->data['invoices'][0] ?? [], array_merge([
            ctrans('texts.invoice_date') => $this->date_format,
            ctrans('texts.invoice_total') => $this->currency_format,
            ctrans('texts.paid') => $this->currency_format,
            ctrans('texts.tax_amount') => $this->currency_format,
            ctrans('texts.taxable_amount') => $this->currency_format,
        ], $this->regionalColumnFormats()));
        $this->autoSizeColumns($worksheet, $this->data['invoices'][0] ?? []);

        return $this;
    }

    /**
     * Create invoice item (tax detail) summary sheet
     */
    public function createInvoiceItemSummarySheet()
    {
        $worksheet_title = $this->cash_accounting ? ctrans('texts.cash_accounting') : ctrans('texts.cash_vs_accrual');

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle(substr(ctrans('texts.invoice_item') . " " . $worksheet_title, 0, 31));
        $worksheet->fromArray($this->data['invoice_items'], null, 'A1');

        $this->formatColumnsByHeaders($worksheet, $this->data['invoice_items'][0] ?? [], array_merge([
            ctrans('texts.invoice_date') => $this->date_format,
            ctrans('texts.tax_rate') => '0.00',
            ctrans('texts.tax_amount') => $this->currency_format,
            ctrans('texts.taxable_amount') => $this->currency_format,
            ctrans('texts.payment_date') => $this->date_format,
            ctrans('texts.payment_amount') => $this->currency_format,
            ctrans('texts.refunded') => $this->currency_format,
        ], $this->regionalColumnFormats()));
        $this->autoSizeColumns($worksheet, $this->data['invoice_items'][0] ?? []);

        return $this;
    }

    /**
     * Convert a 0-based column index to its spreadsheet letter (A, B, ..., Z, AA, AB, ...).
     */
    private function columnLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    }


    /**
     * Build report data from transaction events
     */
    private function buildData(): self
    {

        $query = $this->resolveQuery();

        // Initialize with headers
        $invoice_item_headers = InvoiceItemReportRow::getHeaders($this->regional_calculator, $this->cash_accounting);
        $this->data['invoices'] = [InvoiceReportRow::getHeaders($this->regional_calculator)];
        $this->data['invoice_items'] = [$invoice_item_headers];
        $this->data['legacy_summary_items'] = [$invoice_item_headers];
        $this->data['sales_breakdown'] = [];
        $this->data['correction_review'] = [];

        $this->streamQuery($query)->each(function ($invoice) {

            $invoice->transaction_events()
            ->when(!$this->cash_accounting, function ($query) {
                $query->where('event_id', TransactionEvent::INVOICE_UPDATED);
            })
            ->when($this->cash_accounting, function ($query) {
                $query->where(function ($sub_q) {
                    $sub_q->where('event_id', '!=', TransactionEvent::INVOICE_UPDATED)
                        ->orWhere('metadata->tax_report->tax_summary->status', 'reversed');
                });
            })
            ->whereBetween('period', [$this->start_date, $this->end_date])
            ->orderBy('timestamp', 'desc')
            ->lazy(500)
            ->each(function ($event) use ($invoice) {
                /** @var Invoice $invoice */
                $this->processTransactionEvent($event, $invoice);
            });
        });

        $this->data['summary'] = $this->buildSummaryRows();
        $this->data['monthly_filing_summary'] = $this->buildMonthlyFilingSummaryRows();
        $this->data['state_breakdown'] = $this->buildStateBreakdownRows();
        $this->data['jurisdiction_breakdown'] = $this->buildJurisdictionBreakdownRows();
        $this->data['corrections'] = $this->buildCorrectionRows();
        $this->data['filing_overview'] = $this->buildFilingOverviewRows();

        return $this;
    }

    /**
     * Process a single transaction event and add to report data
     */
    private function processTransactionEvent(TransactionEvent $event, Invoice $invoice): void
    {
        $tax_summary = TaxSummary::fromMetadata($event->metadata->tax_report->tax_summary);
        $correction_context = $this->correctionContext($event);

        $invoice_row_builder = new InvoiceReportRow(
            $invoice,
            $event,
            $tax_summary,
            $this->regional_calculator
        );

        $this->data['invoices'][] = $invoice_row_builder->build();
        $has_sales_breakdown = $this->appendSalesBreakdownRows($event, $invoice, $tax_summary, $correction_context);

        $tax_details = $event->metadata->tax_report->tax_details_by_classification
            ?? $event->metadata->tax_report->tax_details
            ?? [];
        $payments = $this->orderedPaymentHistory($event);

        if ($this->shouldUnwrapByPayment($event, $payments)) {
            $this->emitItemRowsPerPayment($invoice, $tax_summary, $tax_details, $payments, ! $has_sales_breakdown, $correction_context);
            return;
        }

        foreach ($tax_details as $tax_detail_data) {
            $tax_detail = TaxDetail::fromMetadata($tax_detail_data);

            $item_row_builder = new InvoiceItemReportRow(
                $invoice,
                $tax_detail,
                $tax_summary->status,
                $this->regional_calculator
            );

            $row = $item_row_builder->buildForStatus();
            $this->data['invoice_items'][] = $row;

            if (! $has_sales_breakdown) {
                $this->appendLegacySummarySourceRow($row, $correction_context);
            }
        }
    }

    private function appendSalesBreakdownRows(TransactionEvent $event, Invoice $invoice, TaxSummary $tax_summary, array $correction_context): bool
    {
        $sales_breakdown = $event->metadata->tax_report->sales_breakdown ?? [];

        if (empty($sales_breakdown)) {
            return false;
        }

        foreach ($sales_breakdown as $sales_row_data) {
            $sales_row = is_array($sales_row_data) ? $sales_row_data : (array) $sales_row_data;
            $tax_detail = new TaxDetail(
                tax_name: (string) ($sales_row['tax_name'] ?? ''),
                tax_rate: (float) ($sales_row['tax_rate'] ?? 0),
                taxable_amount: (float) ($sales_row['taxable_sales'] ?? 0),
                tax_amount: (float) ($sales_row['tax_amount'] ?? 0),
                line_total: (float) ($sales_row['total_taxable_sales'] ?? 0),
                total_tax: (float) ($sales_row['total_tax_amount'] ?? 0),
                postal_code: (string) ($sales_row['postal_code'] ?? ''),
                classification: (string) ($sales_row['classification'] ?? ''),
            );

            $summary_row = array_fill_keys($this->summaryHeaders(), '');
            $summary_row[ctrans('texts.reporting_bucket')] = $this->regional_calculator?->reportingBucket($invoice, $tax_detail) ?? '';
            $summary_row[ctrans('texts.jurisdiction_source')] = $this->regional_calculator?->jurisdictionSource($invoice, $tax_detail) ?? ctrans('texts.unknown_source');
            $summary_row[ctrans('texts.summary_source')] = ctrans('texts.sales_breakdown_source');
            $summary_row[ctrans('texts.accounting_basis')] = $this->accountingBasisLabel();
            $summary_row[ctrans('texts.activity')] = $tax_summary->status->value;
            $summary_row[ctrans('texts.tax_treatment')] = $this->taxTreatmentLabel((string) ($sales_row['tax_treatment'] ?? ''));
            $summary_row[ctrans('texts.tax_name')] = $tax_detail->tax_name;
            $summary_row[ctrans('texts.tax_rate')] = $tax_detail->tax_rate;
            $summary_row[ctrans('texts.type')] = $tax_detail->classification ?: ctrans('texts.unknown');
            $summary_row[ctrans('texts.postal_code')] = $tax_detail->postal_code;
            $summary_row[ctrans('texts.gross_sales')] = (float) ($sales_row['gross_sales'] ?? 0);
            $summary_row[ctrans('texts.taxable_amount')] = (float) ($sales_row['taxable_sales'] ?? 0);
            $summary_row[ctrans('texts.exempt_sales')] = (float) ($sales_row['exempt_sales'] ?? 0);
            $summary_row[ctrans('texts.non_taxable_sales')] = (float) ($sales_row['non_taxable_sales'] ?? 0);
            $summary_row[ctrans('texts.zero_rated_sales')] = (float) ($sales_row['zero_rated_sales'] ?? 0);
            $summary_row[ctrans('texts.tax_amount')] = $tax_detail->tax_amount;
            $summary_row['_invoice_number'] = $invoice->number;

            $regional_columns = $this->regional_calculator?->calculateColumns($invoice, $tax_detail->tax_amount) ?? [];
            foreach ($this->regional_calculator?->getHeaders() ?? [] as $index => $header) {
                $summary_row[$header] = $regional_columns[$index] ?? '';
            }

            $this->appendSummarySourceRow($summary_row, $correction_context);
        }

        return true;
    }

    /**
     * Cartesian unwrap: emit one row per (tax_detail, payment) with pro-rata tax/taxable.
     * Last payment for each tax_detail absorbs the rounding remainder so column sums match.
     */
    private function emitItemRowsPerPayment(Invoice $invoice, TaxSummary $tax_summary, array $tax_details, array $payments, bool $include_legacy_summary = false, array $correction_context = []): void
    {
        $total_payment_amount = array_sum(array_map(fn (PaymentHistory $p) => $p->amount, $payments));

        if ($total_payment_amount <= 0) {
            foreach ($tax_details as $tax_detail_data) {
                $tax_detail = TaxDetail::fromMetadata($tax_detail_data);
                $row = (new InvoiceItemReportRow(
                    $invoice,
                    $tax_detail,
                    $tax_summary->status,
                    $this->regional_calculator
                ))->buildForStatus();

                $this->data['invoice_items'][] = $row;

                if ($include_legacy_summary) {
                    $this->appendLegacySummarySourceRow($row, $correction_context);
                }
            }
            return;
        }

        $precision = $invoice->client->currency()->precision ?? 2;
        $payment_count = count($payments);

        foreach ($tax_details as $tax_detail_data) {
            $tax_detail = TaxDetail::fromMetadata($tax_detail_data);

            $running_taxable = 0.0;
            $running_tax = 0.0;

            foreach ($payments as $i => $payment) {
                $is_last = ($i === $payment_count - 1);
                $ratio = $payment->amount / $total_payment_amount;

                if ($is_last) {
                    $row_taxable = round($tax_detail->taxable_amount - $running_taxable, $precision);
                    $row_tax = round($tax_detail->tax_amount - $running_tax, $precision);
                } else {
                    $row_taxable = round($tax_detail->taxable_amount * $ratio, $precision);
                    $row_tax = round($tax_detail->tax_amount * $ratio, $precision);
                    $running_taxable += $row_taxable;
                    $running_tax += $row_tax;
                }

                $prorated_detail = new TaxDetail(
                    tax_name: $tax_detail->tax_name,
                    tax_rate: $tax_detail->tax_rate,
                    taxable_amount: $row_taxable,
                    tax_amount: $row_tax,
                    line_total: $tax_detail->line_total,
                    total_tax: $tax_detail->total_tax,
                    postal_code: $tax_detail->postal_code,
                    classification: $tax_detail->classification,
                );

                $row = (new InvoiceItemReportRow(
                    $invoice,
                    $prorated_detail,
                    $tax_summary->status,
                    $this->regional_calculator,
                    $payment,
                ))->buildForStatus();

                $this->data['invoice_items'][] = $row;

                if ($include_legacy_summary) {
                    $this->appendLegacySummarySourceRow($row, $correction_context);
                }
            }
        }
    }


    private function appendSummarySourceRow(array $summary_row, array $correction_context): void
    {
        $summary_row = $this->withFilingPeriodContext($summary_row, $correction_context);

        if ($this->requiresCorrectionReview($correction_context)) {
            $this->data['correction_review'][] = $this->correctionReviewRow($summary_row, $correction_context);
            return;
        }

        $this->data['sales_breakdown'][] = $summary_row;
    }

    private function appendLegacySummarySourceRow(array $row, array $correction_context): void
    {
        $summary_row = $this->withFilingPeriodContext(
            $this->legacySummaryRowForItemRow($row),
            $correction_context,
        );

        if ($this->requiresCorrectionReview($correction_context)) {
            $this->data['correction_review'][] = $this->correctionReviewRow($summary_row, $correction_context);
            return;
        }

        $this->data['sales_breakdown'][] = $summary_row;
    }

    private function requiresCorrectionReview(array $correction_context): bool
    {
        return (bool) ($correction_context['requires_review'] ?? false);
    }

    private function withFilingPeriodContext(array $summary_row, array $correction_context): array
    {
        $summary_row[ctrans('texts.filing_period')] = $correction_context['filing_period'] ?? '';
        $summary_row[ctrans('texts.period_start')] = $correction_context['period_start'] ?? '';
        $summary_row[ctrans('texts.period_end')] = $correction_context['period_end'] ?? '';

        return $summary_row;
    }

    private function correctionReviewRow(array $summary_row, array $correction_context): array
    {
        $correction_row = array_fill_keys($this->correctionHeaders(), '');

        foreach ($this->summaryHeaders() as $header) {
            if (array_key_exists($header, $correction_row)) {
                $correction_row[$header] = $summary_row[$header] ?? '';
            }
        }

        $correction_row[ctrans('texts.original_tax_period')] = $correction_context['target_period'] ?: ctrans('texts.unknown');
        $correction_row[ctrans('texts.correction_recorded_period')] = $correction_context['recorded_period'] ?: ctrans('texts.unknown');
        $correction_row[ctrans('texts.correction_type')] = $correction_context['correction_type'] ?: ctrans('texts.legacy_unknown');
        $correction_row[ctrans('texts.requires_review')] = ($correction_context['requires_review'] ?? false) ? ctrans('texts.yes') : ctrans('texts.no');
        $correction_row[ctrans('texts.invoice_count')] = 1;

        return $correction_row;
    }

    private function legacySummaryRowForItemRow(array $row): array
    {
        $item_headers = $this->data['legacy_summary_items'][0] ?? $this->data['invoice_items'][0] ?? [];

        return $this->legacySummaryRow($row, $this->headerIndexes($item_headers));
    }

    private function legacySummaryRow(array $row, array $indexes): array
    {
        $summary_row = array_fill_keys($this->summaryHeaders(), '');
        $summary_row[ctrans('texts.reporting_bucket')] = (string) ($this->valueForHeader($row, $indexes, ctrans('texts.reporting_bucket')) ?? '');
        $summary_row[ctrans('texts.jurisdiction_source')] = (string) ($this->valueForHeader($row, $indexes, ctrans('texts.jurisdiction_source')) ?? ctrans('texts.unknown_source'));
        $summary_row[ctrans('texts.summary_source')] = ctrans('texts.legacy_tax_detail_source');
        $summary_row[ctrans('texts.accounting_basis')] = $this->accountingBasisLabel();
        $summary_row[ctrans('texts.activity')] = (string) ($this->valueForHeader($row, $indexes, ctrans('texts.status')) ?? '');
        $summary_row[ctrans('texts.tax_treatment')] = $this->taxTreatmentLabel('taxable');
        $summary_row[ctrans('texts.tax_name')] = $this->valueForHeader($row, $indexes, ctrans('texts.tax_name'));
        $summary_row[ctrans('texts.tax_rate')] = $this->valueForHeader($row, $indexes, ctrans('texts.tax_rate'));
        $summary_row[ctrans('texts.type')] = $this->valueForHeader($row, $indexes, ctrans('texts.type'));
        $summary_row[ctrans('texts.postal_code')] = $this->valueForHeader($row, $indexes, ctrans('texts.postal_code'));

        foreach ($this->regional_calculator?->getHeaders() ?? [] as $header) {
            $summary_row[$header] = $this->valueForHeader($row, $indexes, $header);
        }

        $taxable_amount = (float) ($this->valueForHeader($row, $indexes, ctrans('texts.taxable_amount')) ?: 0);
        $summary_row[ctrans('texts.gross_sales')] = $taxable_amount;
        $summary_row[ctrans('texts.taxable_amount')] = $taxable_amount;
        $summary_row[ctrans('texts.exempt_sales')] = 0.0;
        $summary_row[ctrans('texts.non_taxable_sales')] = 0.0;
        $summary_row[ctrans('texts.zero_rated_sales')] = 0.0;
        $summary_row[ctrans('texts.tax_amount')] = (float) ($this->valueForHeader($row, $indexes, ctrans('texts.tax_amount')) ?: 0);
        $summary_row['_invoice_number'] = (string) ($this->valueForHeader($row, $indexes, ctrans('texts.invoice_number')) ?? '');

        return $summary_row;
    }

    /**
     * @return array<string, mixed>
     */
    private function correctionContext(TransactionEvent $event): array
    {
        $recorded_period = $this->periodString($event->period);
        $target_period = $this->correctionTargetPeriod($event);
        $is_correction = $this->isCorrectionEvent($event);
        $target_is_prior = $target_period !== '' && Carbon::parse($target_period)->lt(Carbon::parse($this->start_date));
        $requires_review = $is_correction && ($target_period === '' || $target_is_prior);

        return array_merge($this->filingPeriodContext($recorded_period), [
            'target_period' => $target_period,
            'recorded_period' => $recorded_period,
            'correction_type' => $requires_review ? $this->correctionType($event) : ctrans('texts.current_period_activity'),
            'requires_review' => $requires_review,
        ]);
    }

    /**
     * @return array{filing_period:string, period_start:string, period_end:string}
     */
    private function filingPeriodContext(string $period): array
    {
        if ($period === '') {
            return [
                'filing_period' => '',
                'period_start' => '',
                'period_end' => '',
            ];
        }

        $date = Carbon::parse($period);

        return [
            'filing_period' => $date->format('Y-m'),
            'period_start' => $date->copy()->startOfMonth()->format('Y-m-d'),
            'period_end' => $date->copy()->endOfMonth()->format('Y-m-d'),
        ];
    }

    private function correctionTargetPeriod(TransactionEvent $event): string
    {
        if (in_array($event->event_id, [TransactionEvent::PAYMENT_REFUNDED, TransactionEvent::PAYMENT_DELETED], true)) {
            return $this->paymentHistoryTargetPeriod($event) ?? '';
        }

        if ($event->event_id === TransactionEvent::INVOICE_UPDATED && $this->isCorrectionEvent($event)) {
            return $this->previousInvoiceEventPeriod($event) ?? $this->periodString($event->period);
        }

        return $this->periodString($event->period);
    }

    private function paymentHistoryTargetPeriod(TransactionEvent $event): ?string
    {
        $payment_history = $event->metadata->tax_report->payment_history ?? null;

        if (!$payment_history) {
            return null;
        }

        $periods = $payment_history
            ->map(fn (PaymentHistory $payment): string => Carbon::parse($payment->date)->endOfMonth()->format('Y-m-d'))
            ->unique()
            ->values();

        return $periods->count() === 1 ? $periods->first() : null;
    }

    private function previousInvoiceEventPeriod(TransactionEvent $event): ?string
    {
        $previous_event = TransactionEvent::query()
            ->where('invoice_id', $event->invoice_id)
            ->where('event_id', TransactionEvent::INVOICE_UPDATED)
            ->where('id', '!=', $event->id)
            ->where(function ($query) use ($event) {
                $query->where('timestamp', '<', (int) $event->timestamp)
                    ->orWhere(function ($query) use ($event) {
                        $query->where('timestamp', (int) $event->timestamp)
                            ->where('id', '<', (int) $event->id);
                    });
            })
            ->orderBy('timestamp', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $previous_event ? $this->periodString($previous_event->period) : null;
    }

    private function isCorrectionEvent(TransactionEvent $event): bool
    {
        $status = $event->metadata->tax_report->tax_summary->status ?? 'updated';

        return in_array($event->event_id, [TransactionEvent::PAYMENT_REFUNDED, TransactionEvent::PAYMENT_DELETED], true)
            || in_array($status, ['delta', 'adjustment', 'cancelled', 'deleted', 'reversed', 'restored'], true);
    }

    private function correctionType(TransactionEvent $event): string
    {
        $status = $event->metadata->tax_report->tax_summary->status ?? 'updated';

        if (in_array($event->event_id, [TransactionEvent::PAYMENT_REFUNDED, TransactionEvent::PAYMENT_DELETED], true)) {
            return ctrans('texts.payment_correction');
        }

        if (in_array($status, ['cancelled', 'deleted', 'reversed', 'restored'], true)) {
            return ctrans('texts.status_correction');
        }

        if ($status === 'delta') {
            return ctrans('texts.invoice_correction');
        }

        return ctrans('texts.legacy_unknown');
    }

    private function periodString(mixed $period): string
    {
        if ($period instanceof \Carbon\CarbonInterface) {
            return $period->format('Y-m-d');
        }

        if ($period === null || $period === '') {
            return '';
        }

        return Carbon::parse($period)->format('Y-m-d');
    }

    /**
     * @return array<int, PaymentHistory>
     */
    private function orderedPaymentHistory(TransactionEvent $event): array
    {
        $payment_history = $event->metadata->tax_report->payment_history ?? null;

        if (! $payment_history) {
            return [];
        }

        return $payment_history
            ->sortBy([['date', 'asc'], ['number', 'asc']])
            ->values()
            ->all();
    }

    private function shouldUnwrapByPayment(TransactionEvent $event, array $payments): bool
    {
        return $this->cash_accounting
            && $event->event_id === TransactionEvent::PAYMENT_CASH
            && count($payments) > 0;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildSummaryRows(): array
    {
        $summary_headers = $this->summaryHeaders();
        $source_rows = $this->summarySourceRows();

        if ($source_rows === []) {
            return [$summary_headers];
        }

        $summary = [];
        $invoice_numbers = [];
        $row_counts = [];
        $currency_precision = $this->company->currency()->precision ?? 2;

        foreach ($source_rows as $row) {
            $bucket = trim((string) ($row[ctrans('texts.reporting_bucket')] ?? ''));

            if ($bucket === '') {
                $bucket = ctrans('texts.unknown');
                $row[ctrans('texts.reporting_bucket')] = $bucket;
            }

            $group_key = $this->summaryGroupKey($row);

            if (!isset($summary[$group_key])) {
                $summary[$group_key] = array_fill_keys($summary_headers, '');
                $summary[$group_key][ctrans('texts.reporting_bucket')] = $bucket;
                $summary[$group_key][ctrans('texts.invoice_count')] = 0;

                foreach ($this->summaryAmountHeaders() as $header) {
                    $summary[$group_key][$header] = 0.0;
                }

                $invoice_numbers[$group_key] = [];
                $row_counts[$group_key] = 0;
            }

            $row_counts[$group_key]++;

            foreach ($this->summaryDescriptorHeaders() as $header) {
                $this->setFirstSummaryValue($summary[$group_key], $header, $row[$header] ?? null);
            }

            foreach ($this->regional_calculator?->getHeaders() ?? [] as $header) {
                $value = $row[$header] ?? null;

                if (str_ends_with($header, 'Tax Amount')) {
                    $summary[$group_key][$header] = round(
                        (float) ($summary[$group_key][$header] ?: 0) + (float) ($value ?: 0),
                        $currency_precision,
                    );
                    continue;
                }

                $this->setFirstSummaryValue($summary[$group_key], $header, $value);
            }

            foreach ($this->summaryAmountHeaders() as $header) {
                $summary[$group_key][$header] = round(
                    (float) $summary[$group_key][$header] + (float) (($row[$header] ?? 0) ?: 0),
                    $currency_precision,
                );
            }

            $invoice_number = trim((string) ($row['_invoice_number'] ?? ''));

            if ($invoice_number !== '') {
                $invoice_numbers[$group_key][$invoice_number] = true;
            }
        }

        foreach ($summary as $group_key => $row) {
            $summary[$group_key][ctrans('texts.invoice_count')] = count($invoice_numbers[$group_key]) ?: $row_counts[$group_key];
        }

        return array_merge([$summary_headers], array_map(
            fn (array $row): array => array_map(fn (string $header): mixed => $row[$header] ?? '', $summary_headers),
            array_values($summary),
        ));
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildFilingOverviewRows(): array
    {
        $source_rows = $this->summarySourceRows();
        $correction_rows = $this->data['correction_review'] ?? [];
        $amounts = $this->amountTotals($source_rows, $this->summaryAmountHeaders());
        $states = $this->uniqueNonEmptyValues($source_rows, 'State');
        $jurisdictions = [];
        $missing_state_rows = 0;
        $fallback_rows = 0;

        foreach ($source_rows as $row) {
            $state = trim((string) ($row['State'] ?? ''));
            $source = trim((string) ($row[ctrans('texts.jurisdiction_source')] ?? ''));

            if ($state === '' || $state === ctrans('texts.unknown')) {
                $missing_state_rows++;
            }

            if (in_array($source, [ctrans('texts.client_shipping_source'), ctrans('texts.client_billing_source')], true)) {
                $fallback_rows++;
            }

            $jurisdiction_key = $this->jurisdictionKey($row);

            if ($jurisdiction_key !== '') {
                $jurisdictions[$jurisdiction_key] = true;
            }
        }

        return [
            [ctrans('texts.metric'), ctrans('texts.value')],
            [ctrans('texts.report_period'), $this->start_date . ' - ' . $this->end_date],
            [ctrans('texts.accounting_basis'), $this->accountingBasisLabel()],
            [ctrans('texts.gross_sales'), $amounts[ctrans('texts.gross_sales')] ?? 0.0],
            [ctrans('texts.taxable_amount'), $amounts[ctrans('texts.taxable_amount')] ?? 0.0],
            [ctrans('texts.exempt_sales'), $amounts[ctrans('texts.exempt_sales')] ?? 0.0],
            [ctrans('texts.non_taxable_sales'), $amounts[ctrans('texts.non_taxable_sales')] ?? 0.0],
            [ctrans('texts.zero_rated_sales'), $amounts[ctrans('texts.zero_rated_sales')] ?? 0.0],
            [ctrans('texts.tax_amount'), $amounts[ctrans('texts.tax_amount')] ?? 0.0],
            [ctrans('texts.current_filing_rows'), count($source_rows)],
            [ctrans('texts.correction_rows'), count($correction_rows)],
            [ctrans('texts.states_reported'), count($states)],
            [ctrans('texts.jurisdictions_reported'), count($jurisdictions)],
            [ctrans('texts.rows_missing_state'), $missing_state_rows],
            [ctrans('texts.rows_using_client_fallback'), $fallback_rows],
            [ctrans('texts.prior_period_corrections'), count($correction_rows)],
        ];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildMonthlyFilingSummaryRows(): array
    {
        return $this->buildGroupedFilingRows(
            $this->monthlyFilingSummaryHeaders(),
            [
                ctrans('texts.filing_period'),
                ctrans('texts.period_start'),
                ctrans('texts.period_end'),
                ctrans('texts.accounting_basis'),
                ctrans('texts.tax_treatment'),
            ],
            $this->summaryAmountHeaders(),
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildJurisdictionBreakdownRows(): array
    {
        return $this->buildGroupedFilingRows(
            $this->jurisdictionBreakdownHeaders(),
            [
                ctrans('texts.filing_period'),
                ctrans('texts.period_start'),
                ctrans('texts.period_end'),
                'State',
                'County',
                'City',
                ctrans('texts.district'),
                ctrans('texts.postal_code'),
                ctrans('texts.jurisdiction_source'),
                ctrans('texts.accounting_basis'),
                ctrans('texts.tax_treatment'),
            ],
            $this->stateBreakdownAmountHeaders(),
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildStateBreakdownRows(): array
    {
        return $this->buildGroupedFilingRows(
            $this->stateBreakdownHeaders(),
            [
                ctrans('texts.filing_period'),
                ctrans('texts.period_start'),
                ctrans('texts.period_end'),
                'State',
                ctrans('texts.jurisdiction_source'),
                ctrans('texts.accounting_basis'),
                ctrans('texts.tax_treatment'),
            ],
            $this->stateBreakdownAmountHeaders(),
        );
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $group_headers
     * @param array<int, string> $amount_headers
     * @return array<int, array<int, mixed>>
     */
    private function buildGroupedFilingRows(array $headers, array $group_headers, array $amount_headers): array
    {
        $source_rows = $this->summarySourceRows();

        if ($source_rows === []) {
            return [$headers];
        }

        $summary = [];
        $invoice_numbers = [];
        $row_counts = [];
        $currency_precision = $this->company->currency()->precision ?? 2;

        foreach ($source_rows as $row) {
            $group_key = implode('|', array_map(
                fn (string $header): string => (string) $this->groupedFilingValue($row, $header),
                $group_headers,
            ));

            if (!isset($summary[$group_key])) {
                $summary[$group_key] = array_fill_keys($headers, '');
                $summary[$group_key][ctrans('texts.invoice_count')] = 0;

                foreach ($group_headers as $header) {
                    $summary[$group_key][$header] = $this->groupedFilingValue($row, $header);
                }

                foreach ($amount_headers as $header) {
                    if (array_key_exists($header, $summary[$group_key])) {
                        $summary[$group_key][$header] = 0.0;
                    }
                }

                $invoice_numbers[$group_key] = [];
                $row_counts[$group_key] = 0;
            }

            $row_counts[$group_key]++;

            foreach ($amount_headers as $header) {
                if (!array_key_exists($header, $summary[$group_key])) {
                    continue;
                }

                $summary[$group_key][$header] = round(
                    (float) $summary[$group_key][$header] + (float) (($row[$header] ?? 0) ?: 0),
                    $currency_precision,
                );
            }

            $invoice_number = trim((string) ($row['_invoice_number'] ?? ''));

            if ($invoice_number !== '') {
                $invoice_numbers[$group_key][$invoice_number] = true;
            }
        }

        foreach ($summary as $group_key => $row) {
            $summary[$group_key][ctrans('texts.invoice_count')] = count($invoice_numbers[$group_key]) ?: $row_counts[$group_key];
        }

        return array_merge([$headers], array_map(
            fn (array $row): array => array_map(fn (string $header): mixed => $row[$header] ?? '', $headers),
            array_values($summary),
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function groupedFilingValue(array $row, string $header): mixed
    {
        $value = $row[$header] ?? '';

        if ($header === 'State' && trim((string) $value) === '') {
            return ctrans('texts.unknown');
        }

        if ($header === ctrans('texts.jurisdiction_source') && trim((string) $value) === '') {
            return ctrans('texts.unknown_source');
        }

        if ($header === ctrans('texts.district')) {
            return $this->districtFromReportingBucket($row);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function districtFromReportingBucket(array $row): string
    {
        foreach (explode(' | ', (string) ($row[ctrans('texts.reporting_bucket')] ?? '')) as $part) {
            if (str_starts_with($part, 'Districts ')) {
                return $part;
            }
        }

        return '';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $amount_headers
     * @return array<string, float>
     */
    private function amountTotals(array $rows, array $amount_headers): array
    {
        $totals = array_fill_keys($amount_headers, 0.0);
        $currency_precision = $this->company->currency()->precision ?? 2;

        foreach ($rows as $row) {
            foreach ($amount_headers as $header) {
                $totals[$header] = round(
                    (float) $totals[$header] + (float) (($row[$header] ?? 0) ?: 0),
                    $currency_precision,
                );
            }
        }

        return $totals;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function uniqueNonEmptyValues(array $rows, string $header): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = trim((string) ($row[$header] ?? ''));

            if ($value !== '' && $value !== ctrans('texts.unknown')) {
                $values[$value] = true;
            }
        }

        return array_keys($values);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function jurisdictionKey(array $row): string
    {
        $parts = array_filter([
            $row['State'] ?? '',
            $row['County'] ?? '',
            $row['City'] ?? '',
            $this->districtFromReportingBucket($row),
            $row[ctrans('texts.postal_code')] ?? '',
        ], fn (mixed $value): bool => trim((string) $value) !== '');

        return implode('|', $parts);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function summarySourceRows(): array
    {
        return array_merge(
            $this->data['sales_breakdown'] ?? [],
            $this->legacySummaryRows($this->data['legacy_summary_items'] ?? []),
        );
    }

    /**
     * @param array<int, array<int, mixed>> $item_rows
     * @return array<int, array<string, mixed>>
     */
    private function legacySummaryRows(array $item_rows): array
    {
        $item_headers = $item_rows[0] ?? [];

        if (count($item_rows) <= 1 || empty($item_headers)) {
            return [];
        }

        $indexes = $this->headerIndexes($item_headers);
        $rows = [];

        foreach (array_slice($item_rows, 1) as $row) {
            $rows[] = $this->legacySummaryRow($row, $indexes);
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function buildCorrectionRows(): array
    {
        $correction_headers = $this->correctionHeaders();
        $correction_rows = $this->data['correction_review'] ?? [];

        if ($correction_rows === []) {
            return [$correction_headers];
        }

        return array_merge([$correction_headers], array_map(
            fn (array $row): array => array_map(fn (string $header): mixed => $row[$header] ?? '', $correction_headers),
            $correction_rows,
        ));
    }

    /**
     * @return array<int, string>
     */
    private function correctionHeaders(): array
    {
        return array_merge(
            [
                ctrans('texts.filing_period'),
                ctrans('texts.period_start'),
                ctrans('texts.period_end'),
                ctrans('texts.reporting_bucket'),
                ctrans('texts.jurisdiction_source'),
                ctrans('texts.summary_source'),
                ctrans('texts.original_tax_period'),
                ctrans('texts.correction_recorded_period'),
                ctrans('texts.correction_type'),
                ctrans('texts.requires_review'),
                ctrans('texts.accounting_basis'),
                ctrans('texts.activity'),
                ctrans('texts.tax_treatment'),
                ctrans('texts.tax_name'),
                ctrans('texts.tax_rate'),
                ctrans('texts.type'),
                ctrans('texts.postal_code'),
            ],
            $this->regional_calculator?->getHeaders() ?? [],
            [
                ctrans('texts.gross_sales'),
                ctrans('texts.taxable_amount'),
                ctrans('texts.exempt_sales'),
                ctrans('texts.non_taxable_sales'),
                ctrans('texts.zero_rated_sales'),
                ctrans('texts.tax_amount'),
                ctrans('texts.invoice_count'),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function monthlyFilingSummaryHeaders(): array
    {
        return [
            ctrans('texts.filing_period'),
            ctrans('texts.period_start'),
            ctrans('texts.period_end'),
            ctrans('texts.accounting_basis'),
            ctrans('texts.tax_treatment'),
            ctrans('texts.gross_sales'),
            ctrans('texts.taxable_amount'),
            ctrans('texts.exempt_sales'),
            ctrans('texts.non_taxable_sales'),
            ctrans('texts.zero_rated_sales'),
            ctrans('texts.tax_amount'),
            ctrans('texts.invoice_count'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function jurisdictionBreakdownHeaders(): array
    {
        return [
            ctrans('texts.filing_period'),
            ctrans('texts.period_start'),
            ctrans('texts.period_end'),
            'State',
            'County',
            'City',
            ctrans('texts.district'),
            ctrans('texts.postal_code'),
            ctrans('texts.jurisdiction_source'),
            ctrans('texts.accounting_basis'),
            ctrans('texts.tax_treatment'),
            ctrans('texts.gross_sales'),
            ctrans('texts.taxable_amount'),
            ctrans('texts.exempt_sales'),
            ctrans('texts.non_taxable_sales'),
            ctrans('texts.zero_rated_sales'),
            ctrans('texts.tax_amount'),
            'State Tax Amount',
            'County Tax Amount',
            'City Tax Amount',
            'District Tax Amount',
            ctrans('texts.invoice_count'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stateBreakdownHeaders(): array
    {
        return [
            ctrans('texts.filing_period'),
            ctrans('texts.period_start'),
            ctrans('texts.period_end'),
            'State',
            ctrans('texts.jurisdiction_source'),
            ctrans('texts.accounting_basis'),
            ctrans('texts.tax_treatment'),
            ctrans('texts.gross_sales'),
            ctrans('texts.taxable_amount'),
            ctrans('texts.exempt_sales'),
            ctrans('texts.non_taxable_sales'),
            ctrans('texts.zero_rated_sales'),
            ctrans('texts.tax_amount'),
            'State Tax Amount',
            'County Tax Amount',
            'City Tax Amount',
            'District Tax Amount',
            ctrans('texts.invoice_count'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function summaryHeaders(): array
    {
        return array_merge(
            [
                ctrans('texts.filing_period'),
                ctrans('texts.period_start'),
                ctrans('texts.period_end'),
                ctrans('texts.reporting_bucket'),
                ctrans('texts.jurisdiction_source'),
                ctrans('texts.summary_source'),
                ctrans('texts.accounting_basis'),
                ctrans('texts.activity'),
                ctrans('texts.tax_treatment'),
                ctrans('texts.tax_name'),
                ctrans('texts.tax_rate'),
                ctrans('texts.type'),
                ctrans('texts.postal_code'),
            ],
            $this->regional_calculator?->getHeaders() ?? [],
            [
                ctrans('texts.gross_sales'),
                ctrans('texts.taxable_amount'),
                ctrans('texts.exempt_sales'),
                ctrans('texts.non_taxable_sales'),
                ctrans('texts.zero_rated_sales'),
                ctrans('texts.tax_amount'),
                ctrans('texts.invoice_count'),
            ],
        );
    }

    /**
     * @return array<int, string>
     */
    private function summaryDescriptorHeaders(): array
    {
        return [
            ctrans('texts.filing_period'),
            ctrans('texts.period_start'),
            ctrans('texts.period_end'),
            ctrans('texts.jurisdiction_source'),
            ctrans('texts.summary_source'),
            ctrans('texts.accounting_basis'),
            ctrans('texts.activity'),
            ctrans('texts.tax_treatment'),
            ctrans('texts.tax_name'),
            ctrans('texts.tax_rate'),
            ctrans('texts.type'),
            ctrans('texts.postal_code'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function stateBreakdownAmountHeaders(): array
    {
        return array_values(array_unique(array_merge(
            $this->summaryAmountHeaders(),
            [
                'State Tax Amount',
                'County Tax Amount',
                'City Tax Amount',
                'District Tax Amount',
            ],
        )));
    }

    /**
     * @return array<int, string>
     */
    private function summaryAmountHeaders(): array
    {
        return [
            ctrans('texts.gross_sales'),
            ctrans('texts.taxable_amount'),
            ctrans('texts.exempt_sales'),
            ctrans('texts.non_taxable_sales'),
            ctrans('texts.zero_rated_sales'),
            ctrans('texts.tax_amount'),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function summaryGroupKey(array $row): string
    {
        $headers = array_merge([ctrans('texts.reporting_bucket')], $this->summaryDescriptorHeaders());

        foreach ($this->regional_calculator?->getHeaders() ?? [] as $header) {
            if (!str_ends_with($header, 'Tax Amount')) {
                $headers[] = $header;
            }
        }

        return implode('|', array_map(fn (string $header): string => (string) ($row[$header] ?? ''), $headers));
    }

    private function accountingBasisLabel(): string
    {
        return $this->cash_accounting ? ctrans('texts.cash_accounting') : ctrans('texts.cash_vs_accrual');
    }

    private function taxTreatmentLabel(string $tax_treatment): string
    {
        return match ($tax_treatment) {
            'taxable' => ctrans('texts.taxable_sales'),
            'exempt' => ctrans('texts.exempt_sales'),
            'zero_rated' => ctrans('texts.zero_rated_sales'),
            'non_taxable' => ctrans('texts.non_taxable_sales'),
            default => ctrans('texts.unknown'),
        };
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    private function headerIndexes(array $headers): array
    {
        $indexes = [];

        foreach ($headers as $index => $header) {
            $indexes[(string) $header] = $index;
        }

        return $indexes;
    }

    private function valueForHeader(array $row, array $indexes, string $header): mixed
    {
        if (!array_key_exists($header, $indexes)) {
            return null;
        }

        return $row[$indexes[$header]] ?? null;
    }

    private function setFirstSummaryValue(array &$summary_row, string $header, mixed $value): void
    {
        if (!array_key_exists($header, $summary_row)) {
            return;
        }

        if ($summary_row[$header] !== '' || $value === null || $value === '') {
            return;
        }

        $summary_row[$header] = $value;
    }

    /**
     * @param array<int, string> $headers
     * @param array<string, string> $formats
     */
    private function formatColumnsByHeaders(Worksheet $worksheet, array $headers, array $formats): void
    {
        foreach ($headers as $index => $header) {
            $format = $formats[(string) $header] ?? null;

            if ($format === null) {
                continue;
            }

            $letter = $this->columnLetter($index);
            $worksheet->getStyle("{$letter}:{$letter}")->getNumberFormat()->setFormatCode($format);
        }
    }

    /**
     * @return array<string, string>
     */
    private function filingPeriodColumnFormats(): array
    {
        return [
            ctrans('texts.period_start') => $this->date_format,
            ctrans('texts.period_end') => $this->date_format,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function regionalColumnFormats(): array
    {
        $formats = [];

        foreach ($this->regional_calculator?->getHeaders() ?? [] as $header) {
            if (str_ends_with($header, 'Tax Amount')) {
                $formats[$header] = $this->currency_format;
            } elseif (str_ends_with($header, 'Tax Rate')) {
                $formats[$header] = '0.00%';
            }
        }

        return $formats;
    }

    /**
     * @param array<int, string> $headers
     */
    private function autoSizeColumns(Worksheet $worksheet, array $headers): void
    {
        foreach (array_keys($headers) as $index) {
            $worksheet->getColumnDimension($this->columnLetter((int) $index))->setAutoSize(true);
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
