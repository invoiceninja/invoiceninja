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

        $gross_sales = round($query->sum('amount'), 2);
        $taxable_sales = round($query->where('total_taxes', '>', 0)->sum('amount'), 2);
        $exempt_sales = round(($gross_sales - $taxable_sales), 2);

        $gross_sales_money = Number::formatMoney($gross_sales, $this->company);
        $taxable_sales_money = Number::formatMoney($taxable_sales, $this->company);
        $exempt_sales_money = Number::formatMoney($exempt_sales, $this->company);

        $gross_sales_formatted = Number::formatValue($gross_sales, $this->company->currency());
        $taxable_sales_formatted = Number::formatValue($taxable_sales, $this->company->currency());
        $exempt_sales_formatted = Number::formatValue($exempt_sales, $this->company->currency());

        foreach ($query->cursor() as $invoice) {
            $calc = $invoice->calc();

            //Combine the line taxes with invoice taxes here to get a total tax amount
            $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

            //filter into two arrays for accrual + cash
            foreach ($taxes as $tax) {
                $key = $tax['name'];
                $tax_prorata = 0;

                if (!isset($accrual_map[$key])) {
                    $accrual_map[$key]['tax_amount'] = 0;
                }

                $accrual_map[$key]['tax_amount'] += $tax['total'];
                $accrual_invoice_map[] = [
                    'number' => ctrans('texts.invoice') . " " . $invoice->number,
                    'date' => $this->translateDate($invoice->date, $this->company->date_format(), $this->company->locale()),
                    'formatted' => Number::formatMoney($tax['total'], $this->company),
                    'tax' => Number::formatValue($tax['total'], $this->company->currency()),
                    'name' => $tax['name'],
                    'rate' => $tax['tax_rate'],
                    'base_amount' => $tax['base_amount'] ?? $calc->getNetSubtotal(),
                ];

                //cash
                $key = $tax['name'];

                if (!isset($cash_map[$key])) {
                    $cash_map[$key]['tax_amount'] = 0;
                }

                if (in_array($invoice->status_id, [Invoice::STATUS_PARTIAL,Invoice::STATUS_PAID])) {

                    try {
                        if ($invoice->status_id == Invoice::STATUS_PAID) {
                            $tax_prorata = $tax['total'];
                            $cash_map[$key]['tax_amount'] += $tax['total'];
                            $cash_gross_sales += $invoice->amount;
                            $cash_taxable_sales += $invoice->total_taxes > 0 ? $invoice->amount : 0;
                            $cash_exempt_sales += $invoice->total_taxes == 0 ? $invoice->amount : 0;
                        } else {

                            $paid_amount = $invoice->amount - $invoice->balance;
                            $payment_ratio = $invoice->amount > 0 ? $paid_amount / $invoice->amount : 0;
                            $tax_prorata = round($payment_ratio * ($tax['total'] ?? 0), 2);

                            $cash_map[$key]['tax_amount'] += $tax_prorata;

                            $cash_gross_sales += $invoice->amount;
                            $cash_taxable_sales += $tax_prorata > 0 ? $paid_amount : 0;
                            $cash_exempt_sales += $tax_prorata == 0 ? $paid_amount : 0;
                        }

                        $cash_invoice_map[] = [
                            'number' => ctrans('texts.invoice') . " " . $invoice->number,
                            'date' => $this->translateDate($invoice->date, $this->company->date_format(), $this->company->locale()),
                            'formatted' => Number::formatMoney($tax_prorata, $this->company),
                            'tax' => Number::formatValue($tax_prorata, $this->company->currency()),
                            'name' => $tax['name'],
                            'rate' => $tax['tax_rate'],
                            'base_amount' => $tax['base_amount'] ?? $calc->getNetSubtotal(),
                        ];

                    } catch (\DivisionByZeroError $e) {
                        $cash_map[$key]['tax_amount'] += 0;
                    }
                }
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
