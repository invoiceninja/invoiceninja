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

namespace App\Export\CSV;

use App\Models\Task;
use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Activity;
use App\Libraries\MultiDB;
use App\Models\DateFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use App\Transformers\ActivityTransformer;

class ActivityExport extends BaseExport
{
    
    private $entity_transformer;

    public string $date_key = 'created_at';

    private string $date_format = 'YYYY-MM-DD';

    public Writer $csv;

    public array $entity_keys = [
        'date' => 'date',
        'activity' => 'activity',
        'address' => 'address',
    ];

    private array $decorate_keys = [

    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new ActivityTransformer();
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->date_format = DateFormat::find($this->company->settings->date_format_id)->format;

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        ksort($this->entity_keys);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = Activity::query()
                        ->where('company_id', $this->company->id);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($entity) {
                  $this->buildRow($entity);
              });

        return $this->csv->toString();
    }

    private function buildRow(Activity $activity)
    {
       
        $this->csv->insertOne([
            Carbon::parse($activity->created_at)->format($this->date_format),
            ctrans("texts.activity_{$activity->activity_type_id}",[
                'client' => $activity->client ? $activity->client->present()->name() : '',
                'contact' => $activity->contact ? $activity->contact->present()->name() : '',
                'quote' => $activity->quote ? $activity->quote->number : '',
                'user' => $activity->user ? $activity->user->present()->name() : 'System',
                'expense' => $activity->expense ? $activity->expense->number : '',
                'invoice' => $activity->invoice ? $activity->invoice->number : '',
                'recurring_invoice' => $activity->recurring_invoice ? $activity->recurring_invoice->number : '',
                'payment' => $activity->payment ? $activity->payment->number : '',
                'credit' => $activity->credit ? $activity->credit->number : '',
                'task' => $activity->task ? $activity->task->number : '',
                'vendor' => $activity->vendor ? $activity->vendor->present()->name() : '',
                'purchase_order' => $activity->purchase_order ? $activity->purchase_order->number : '',
                'subscription' => $activity->subscription ? $activity->subscription->name : '',
                'vendor_contact' => $activity->vendor_contact ? $activity->vendor_contact->present()->name() : '',
                'recurring_expense' => $activity->recurring_expense ? $activity->recurring_expense->number : '',
            ]),
            $activity->ip,
        ]);


    }

    private function decorateAdvancedFields(Task $task, array $entity) :array
    {
        return $entity;
    }
}
