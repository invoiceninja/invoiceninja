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
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Services\Template\TemplateService;
use Carbon\CarbonImmutable;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Services\Payment\PaymentApplicationDateResolver;
use App\Services\Report\TaxPeriod\CashTaxEventProjector;
use App\Services\Report\TaxPeriod\SalesBreakdownCalculator;

class TaxSummaryReport extends BaseExport
{
    use MakesDates;
    //Name
    //Invoice count
    //Amount
    //Amount with Tax

    public Writer $csv;

    public string $date_key = 'date';

    private array $taxes = [];

    private string $template = '/views/templates/reports/tax_summary_report.html';

    public array $report_keys = [
        'tax_name',
        'tax_amount',
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

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $query = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->company->id)
            ->whereIn('status_id', [2,3,4])
            ->where('is_deleted', 0)
            ->orderBy('balance', 'desc');

        $query = $this->addDateRange($query, 'invoices');
        $query = $this->filterByUserPermissions($query);

        $this->csv->insertOne([ctrans('texts.tax_summary')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if ($this->input['date_range'] != 'all') {
            $this->csv->insertOne([ctrans('texts.date_range'),' ',$this->translateDate($this->start_date, $this->company->date_format(), $this->company->locale()),' - ',$this->translateDate($this->end_date, $this->company->date_format(), $this->company->locale())]);
        }

        $query = $this->filterByClients($query);
        $accrual_map = [];
        $cash_map = [];

        $accrual_invoice_map = [];
        $cash_invoice_map = [];

        // Initialize cash variables
        $cash_gross_sales = 0;
        $cash_taxable_sales = 0;
        $cash_exempt_sales = 0;

        $gross_sales = 0.0;
        $taxable_sales = 0.0;
        $exempt_sales = 0.0;

        // Accrual: iterate invoices filtered by invoice date (the existing query)
        foreach ($query->cursor() as $invoice) {
            $calc = $invoice->calc();
            $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());
            $exchange_rate = (float) ($invoice->exchange_rate ?: 1);
            $multiplier = 1 / $exchange_rate;
            $sales_totals = SalesBreakdownCalculator::summaryTotals($invoice, $multiplier);
            $gross_sales += $sales_totals['gross_sales'];
            $taxable_sales += $sales_totals['taxable_sales'];
            $exempt_sales += $sales_totals['exempt_sales']
                + $sales_totals['non_taxable_sales']
                + $sales_totals['zero_rated_sales'];

            if (empty($taxes)) {
                $accrual_invoice_map[] = [
                    'number' => ctrans('texts.invoice') . " " . $invoice->number,
                    'date' => $this->translateDate($invoice->date, $this->company->date_format(), $this->company->locale()),
                    'formatted' => Number::formatMoney(0, $this->company),
                    'tax' => Number::formatValue(0, $this->company->currency()),
                    'name' => ctrans('texts.tax_exempt'),
                    'rate' => 0,
                    'base_amount' => 0,
                ];
            }

            foreach ($taxes as $tax) {
                $key = $tax['name'];
                $tax_amount = (float) $tax['total'] * $multiplier;
                $base_amount = (float) ($tax['base_amount'] ?? $calc->getNetSubtotal()) * $multiplier;

                if (!isset($accrual_map[$key])) {
                    $accrual_map[$key]['tax_amount'] = 0;
                }

                $accrual_map[$key]['tax_amount'] += $tax_amount;
                $accrual_invoice_map[] = [
                    'number' => ctrans('texts.invoice') . " " . $invoice->number,
                    'date' => $this->translateDate($invoice->date, $this->company->date_format(), $this->company->locale()),
                    'formatted' => Number::formatMoney($tax_amount, $this->company),
                    'tax' => Number::formatValue($tax_amount, $this->company->currency()),
                    'name' => $tax['name'],
                    'rate' => $tax['tax_rate'],
                    'base_amount' => $base_amount,
                ];
            }
        }

        $gross_sales = round($gross_sales, 2);
        $taxable_sales = round($taxable_sales, 2);
        $exempt_sales = round($exempt_sales, 2);
        $gross_sales_money = Number::formatMoney($gross_sales, $this->company);
        $taxable_sales_money = Number::formatMoney($taxable_sales, $this->company);
        $exempt_sales_money = Number::formatMoney($exempt_sales, $this->company);
        $gross_sales_formatted = Number::formatValue($gross_sales, $this->company->currency());
        $taxable_sales_formatted = Number::formatValue($taxable_sales, $this->company->currency());
        $exempt_sales_formatted = Number::formatValue($exempt_sales, $this->company->currency());

        // Cash activity is initialized from paymentables, then projected from immutable events.
        $is_all = $this->input['date_range'] == 'all';
        $timezone = $this->company->timezone()?->name ?: config('app.timezone');
        $paymentable_query = Paymentable::query()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->whereNull('deleted_at')
            ->whereHas('payment', fn ($query) => $query
                ->withTrashed()
                ->where('company_id', $this->company->id)
                ->where('is_deleted', false));

        if (! $is_all) {
            [$cash_start, $cash_end] = app(PaymentApplicationDateResolver::class)
                ->candidateBounds($this->start_date, $this->end_date, $timezone);
            $paymentable_query
                ->where('created_at', '>=', $cash_start)
                ->where('created_at', '<', $cash_end);
        }

        $paymentable_query
            ->orderBy('id')
            ->lazyById(500)
            ->filter(function (Paymentable $paymentable) use ($is_all, $timezone): bool {
                if ($is_all) {
                    return true;
                }

                $date = app(PaymentApplicationDateResolver::class)->resolve($paymentable, $timezone);

                return $date !== null && $date >= $this->start_date && $date <= $this->end_date;
            })
            ->each(function (Paymentable $paymentable): void {
                $invoice = Invoice::withTrashed()
                    ->where('company_id', $this->company->id)
                    ->find($paymentable->paymentable_id);

                if ($invoice) {
                    app(InvoiceTransactionEventEntryCash::class)->runForPaymentable($invoice, $paymentable);
                }
            });

        $eligible_invoices = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->company->id);
        $eligible_invoices = $this->filterByUserPermissions($eligible_invoices);
        $eligible_invoices = $this->filterByClients($eligible_invoices);
        $event_query = TransactionEvent::query()
            ->with('invoice')
            ->where('company_id', $this->company->id)
            ->whereIn('event_id', [
                TransactionEvent::PAYMENT_CASH,
                TransactionEvent::PAYMENT_REFUNDED,
                TransactionEvent::PAYMENT_DELETED,
            ])
            ->whereIn('invoice_id', $eligible_invoices->select('id'));

        if (! $is_all) {
            $event_query->whereBetween('period', [
                CarbonImmutable::parse($this->start_date)->startOfMonth()->toDateString(),
                CarbonImmutable::parse($this->end_date)->endOfMonth()->toDateString(),
            ]);
        }

        $events = $event_query->orderBy('period')->orderBy('id')->get();
        $invoices = $events->pluck('invoice')->filter()->keyBy('id');
        $cash_rows = app(CashTaxEventProjector::class)->project(
            $events,
            $is_all ? null : $this->start_date,
            $is_all ? null : $this->end_date,
        );

        foreach ($cash_rows as $row) {
            $invoice = $invoices->get($row['invoice_id']);

            if (! $invoice) {
                continue;
            }

            if ($row['sales_totals'] !== []) {
                $cash_gross_sales += (float) ($row['sales_totals']['gross_sales'] ?? 0);
                $cash_taxable_sales += (float) ($row['sales_totals']['taxable_sales'] ?? 0);
                $cash_exempt_sales += (float) ($row['sales_totals']['exempt_sales'] ?? 0)
                    + (float) ($row['sales_totals']['non_taxable_sales'] ?? 0)
                    + (float) ($row['sales_totals']['zero_rated_sales'] ?? 0);
            } else {
                $cash_gross_sales += $row['gross_amount'];
                $cash_taxable_sales += abs($row['tax_amount']) > 0.0001 ? $row['gross_amount'] : 0;
                $cash_exempt_sales += abs($row['tax_amount']) > 0.0001 ? 0 : $row['gross_amount'];
            }

            if ($row['tax_details'] === []) {
                $cash_invoice_map[] = [
                    'number' => ctrans('texts.invoice').' '.$invoice->number,
                    'date' => $this->translateDate($row['effective_date'], $this->company->date_format(), $this->company->locale()),
                    'formatted' => Number::formatMoney(0, $this->company),
                    'tax' => Number::formatValue(0, $this->company->currency()),
                    'name' => ctrans('texts.tax_exempt'),
                    'rate' => 0,
                    'base_amount' => 0,
                ];
            }

            foreach ($row['tax_details'] as $tax) {
                $key = (string) ($tax['tax_name'] ?? ctrans('texts.tax'));
                $cash_map[$key]['tax_amount'] = ($cash_map[$key]['tax_amount'] ?? 0) + (float) ($tax['tax_amount'] ?? 0);
                $cash_invoice_map[] = [
                    'number' => ctrans('texts.invoice').' '.$invoice->number,
                    'date' => $this->translateDate($row['effective_date'], $this->company->date_format(), $this->company->locale()),
                    'formatted' => Number::formatMoney((float) ($tax['tax_amount'] ?? 0), $this->company),
                    'tax' => Number::formatValue((float) ($tax['tax_amount'] ?? 0), $this->company->currency()),
                    'name' => $key,
                    'rate' => (float) ($tax['tax_rate'] ?? 0),
                    'base_amount' => (float) ($tax['taxable_amount'] ?? 0),
                ];
            }
        }

        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.cash_vs_accrual')]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.gross'), $gross_sales_money, $gross_sales_formatted]);
        $this->csv->insertOne([ctrans('texts.taxable_amount'), $taxable_sales_money, $taxable_sales_formatted]);
        $this->csv->insertOne([ctrans('texts.tax_exempt'), $exempt_sales_money, $exempt_sales_formatted]);
        $this->csv->insertOne([]);

        $this->csv->insertOne($this->buildHeader());
        foreach ($accrual_map as $key => &$value) {
            $formatted_value = Number::formatValue($value['tax_amount'], $this->company->currency());
            $formatted_money = Number::formatMoney($value['tax_amount'], $this->company);
            $value['tax_amount'] = $formatted_money;
            $this->csv->insertOne([$key, $formatted_money, $formatted_value]);
        }
        unset($value);

        $cash_gross_sales_money = Number::formatMoney($cash_gross_sales, $this->company);
        $cash_taxable_sales_money = Number::formatMoney($cash_taxable_sales, $this->company);
        $cash_exempt_sales_money = Number::formatMoney($cash_exempt_sales, $this->company);

        $cash_gross_sales_formatted = Number::formatValue($cash_gross_sales, $this->company->currency());
        $cash_taxable_sales_formatted = Number::formatValue($cash_taxable_sales, $this->company->currency());
        $cash_exempt_sales_formatted = Number::formatValue($cash_exempt_sales, $this->company->currency());

        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.cash_accounting')]);
        $this->csv->insertOne([]);

        $this->csv->insertOne([ctrans('texts.gross'), $cash_gross_sales_money, $cash_gross_sales_formatted]);
        $this->csv->insertOne([ctrans('texts.taxable_amount'), $cash_taxable_sales_money, $cash_taxable_sales_formatted]);
        $this->csv->insertOne([ctrans('texts.tax_exempt'), $cash_exempt_sales_money, $cash_exempt_sales_formatted]);
        $this->csv->insertOne([]);

        $this->csv->insertOne($this->buildHeader());

        foreach ($cash_map as $key => &$value) {
            $formatted_value = Number::formatValue($value['tax_amount'], $this->company->currency());
            $formatted_money = Number::formatMoney($value['tax_amount'], $this->company);
            $value['tax_amount'] = $formatted_money;
            $this->csv->insertOne([$key, $formatted_money, $formatted_value]);
        }
        unset($value);

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.cash_vs_accrual'), ctrans('texts.date'), ctrans('texts.amount'), ctrans('texts.amount'), ctrans('texts.tax_name'), ctrans('texts.tax_rate'), ctrans('texts.taxable_amount')]); // for the summary add in the tax rates as headers also

        foreach ($accrual_invoice_map as $map) {
            $this->csv->insertOne($map);
        }

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.cash_accounting'), ctrans('texts.date'), ctrans('texts.amount'), ctrans('texts.amount'), ctrans('texts.tax_name'), ctrans('texts.tax_rate'), ctrans('texts.taxable_amount')]); // for the summary add in the tax rates as headers also


        foreach ($cash_invoice_map as $map) {
            $this->csv->insertOne($map);
        }

        $this->taxes['accrual_map'] = $accrual_map;
        $this->taxes['accrual_invoice_map'] = $accrual_invoice_map;

        $this->taxes['cash_map'] = $cash_map;
        $this->taxes['cash_invoice_map'] = $cash_invoice_map;

        $this->taxes['cash_gross_sales'] = $cash_gross_sales_money;
        $this->taxes['cash_taxable_sales'] = $cash_taxable_sales_money;
        $this->taxes['cash_exempt_sales'] = $cash_exempt_sales_money;

        $this->taxes['gross_sales'] = $gross_sales_money;
        $this->taxes['taxable_sales'] = $taxable_sales_money;
        $this->taxes['exempt_sales'] = $exempt_sales_money;

        return $this->csv->toString();

    }

    public function getPdf()
    {
        $user = isset($this->input['user_id']) ? User::withTrashed()->find($this->input['user_id']) : $this->company->owner();

        $user_name = $user ? $user->present()->name() : '';

        $data = [
            'taxes' => $this->taxes,
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
