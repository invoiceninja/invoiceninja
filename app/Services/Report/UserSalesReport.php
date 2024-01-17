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
use App\Models\Company;
use App\Models\Invoice;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class UserSalesReport extends BaseExport
{
    use MakesDates;
    //Name
    //Invoice count
    //Amount
    //Amount with Tax
    public Writer $csv;

    public string $date_key = 'created_at';

    public array $report_keys = [
        'name',
        'invoices',
        'invoice_amount',
        'total_taxes',
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

        $query = Invoice::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0)
                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID]);

        $query = $this->addDateRange($query);

        $query = $this->filterByClients($query);

        $this->csv->insertOne([ctrans('texts.user_sales_report_header', ['client' => $this->client_description, 'start_date' => $this->start_date, 'end_date' => $this->end_date])]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        $users = $this->company->users;

        $report = $users->map(function ($user) use ($query) {

            $new_query = $query;
            $new_query->where('user_id', $user->id);

            return [
                $user->present()->name(),
                $new_query->count(),
                Number::formatMoney($new_query->sum('amount'), $this->company),
                Number::formatMoney($new_query->sum('total_taxes'), $this->company),
            ];

        })->toArray();

        $key_values = array_column($report, 1);
        array_multisort($key_values, SORT_DESC, $report);

        $this->csv->insertAll($report);

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
