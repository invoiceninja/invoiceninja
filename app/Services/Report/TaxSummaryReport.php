<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Report;

use App\Export\CSV\BaseExport;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class TaxSummaryReport extends BaseExport
{
    use MakesDates;
    //Name
    //Invoice count
    //Amount
    //Amount with Tax

    public Writer $csv;

    public string $date_key = 'created_at';

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

        $this->csv = Writer::createFromString();

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

        $query = $this->addDateRange($query);

        $this->csv->insertOne([ctrans('texts.tax_summary')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if($this->input['date_range'] != 'all') {
            $this->csv->insertOne([ctrans('texts.date_range'),' ',$this->translateDate($this->start_date, $this->company->date_format(), $this->company->locale()),' - ',$this->translateDate($this->end_date, $this->company->date_format(), $this->company->locale())]);
        }



        $query = $this->filterByClients($query);
        $accrual_map = [];
        $cash_map = [];

        foreach($query->cursor() as $invoice) {
            $calc = $invoice->calc();

            //Combine the line taxes with invoice taxes here to get a total tax amount
            $taxes = array_merge($calc->getTaxMap()->merge($calc->getTotalTaxMap())->toArray());

            //filter into two arrays for accrual + cash
            foreach($taxes as $tax) {
                $key = $tax['name'];

                if(!isset($accrual_map[$key])) {
                    $accrual_map[$key]['tax_amount'] = 0;
                }

                $accrual_map[$key]['tax_amount'] += $tax['total'];

                //cash
                $key = $tax['name'];

                if(!isset($cash_map[$key])) {
                    $cash_map[$key]['tax_amount'] = 0;
                }

                if(in_array($invoice->status_id, [Invoice::STATUS_PARTIAL,Invoice::STATUS_PAID])) {

                    try {
                        if($invoice->status_id == Invoice::STATUS_PAID) {
                            $cash_map[$key]['tax_amount'] += $tax['total'];
                        } else {
                            $cash_map[$key]['tax_amount'] += (($invoice->amount - $invoice->balance) / $invoice->balance) * $tax['total'] ?? 0;
                        }
                    } catch(\DivisionByZeroError $e) {
                        $cash_map[$key]['tax_amount'] += 0;
                    }
                }
            }

        }

        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.cash_vs_accrual')]);
        $this->csv->insertOne($this->buildHeader());


        foreach($accrual_map as $key => $value) {
            $this->csv->insertOne([$key, Number::formatMoney($value['tax_amount'], $this->company)]);
        }

        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.cash_accounting')]);
        $this->csv->insertOne($this->buildHeader());

        foreach($cash_map as $key => $value) {
            $this->csv->insertOne([$key, Number::formatMoney($value['tax_amount'], $this->company)]);
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
