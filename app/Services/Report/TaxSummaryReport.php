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
        // 'taxable_amount',
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
        $this->csv->insertOne([ctrans('texts.tax_summary')]);
        $this->csv->insertOne([ctrans('texts.created_on'),' ',$this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        $query = Invoice::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', 0)
            ->orderBy('balance', 'desc');

        $query = $this->addDateRange($query);

        $query = $this->filterByClients($query);
        $map = [];

        foreach($query->cursor() as $invoice) 
        {

            $taxes = $invoice->calc()->getTaxMap();

            foreach($taxes as $tax) 
            {
                $key = $tax['name'];

                if(!isset($map[$key])) {
                    $map[$key]['tax_amount'] = 0;
                    // $map[$key]['taxable_amount'] = 0;
                }

                $map[$key]['tax_amount'] += $tax['total'];
                // $map[$key]['taxable_amount'] += $invoice->amount;

            }

        }

        foreach($map as $key => $value)
        {
            $this->csv->insertOne([$key, Number::formatMoney($value['tax_amount'], $this->company)]);
            // $this->csv->insertOne([$key, Number::formatMoney($value['taxable_amount'], $this->company), Number::formatMoney($value['tax_amount'], $this->company)]);
        }


        return $this->csv->toString();
        
    }

    
    public function buildHeader() :array
    {
        $header = [];

        foreach ($this->input['report_keys'] as $value) {

            $header[] = ctrans("texts.{$value}");
        }

        return $header;
    }

}
