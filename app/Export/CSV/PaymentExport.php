<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Payment;
use App\Transformers\PaymentTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class PaymentExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private $entity_transformer;

    protected $date_key = 'date';

    protected array $entity_keys = [
        'amount' => 'amount',
        'applied' => 'applied',
        'client' => 'client_id',
        'currency' => 'currency_id',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'date' => 'date',
        'exchange_currency' => 'exchange_currency_id',
        'gateway' => 'gateway_type_id',
        'number' => 'number',
        'private_notes' => 'private_notes',
        'project' => 'project_id',
        'refunded' => 'refunded',
        'status' => 'status_id',
        'transaction_reference' => 'transaction_reference',
        'type' => 'type_id',
        'vendor' => 'vendor_id',
    ];

    private array $decorate_keys = [
        'vendor',
        'status',
        'project',
        'client',
        'currency',
        'exchange_currency',
        'type',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new PaymentTransformer();
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query = Payment::query()->where('company_id', $this->company->id)->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
              ->each(function ($entity) {
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    private function buildRow(Payment $payment) :array
    {
        $transformed_entity = $this->entity_transformer->transform($payment);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                $entity[$keyval] = '';
            }
        }

        return $this->decorateAdvancedFields($payment, $entity);
    }

    private function decorateAdvancedFields(Payment $payment, array $entity) :array
    {
        if (in_array('status_id', $this->input['report_keys'])) {
            $entity['status'] = $payment->stringStatus($payment->status_id);
        }

        if (in_array('vendor_id', $this->input['report_keys'])) {
            $entity['vendor'] = $payment->vendor()->exists() ? $payment->vendor->name : '';
        }

        if (in_array('project_id', $this->input['report_keys'])) {
            $entity['project'] = $payment->project()->exists() ? $payment->project->name : '';
        }

        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency'] = $payment->currency()->exists() ? $payment->currency->code : '';
        }

        if (in_array('exchange_currency_id', $this->input['report_keys'])) {
            $entity['exchange_currency'] = $payment->exchange_currency()->exists() ? $payment->exchange_currency->code : '';
        }

        if (in_array('client_id', $this->input['report_keys'])) {
            $entity['client'] = $payment->client->present()->name();
        }

        if (in_array('type_id', $this->input['report_keys'])) {
            $entity['type'] = $payment->translatedType();
        }

        if (in_array('gateway_type_id', $this->input['report_keys'])) {
            $entity['gateway'] = $payment->gateway_type ? $payment->gateway_type->name : 'Unknown Type';
        }

        return $entity;
    }
}
