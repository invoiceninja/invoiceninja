<?php
/**
 * Expense Ninja (https://expenseninja.com).
 *
 * @link https://github.com/expenseninja/expenseninja source repository
 *
 * @copyright Copyright (c) 2022. Expense Ninja LLC (https://expenseninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Transformers\ExpenseTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ExpenseExport extends BaseExport
{
    private Company $company;

    protected array $input;

    private $expense_transformer;

    protected $date_key = 'date';

    protected array $entity_keys = [
        'amount' => 'amount',
        'category' => 'category_id',
        'client' => 'client_id',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
        'currency' => 'currency_id',
        'date' => 'date',
        'exchange_rate' => 'exchange_rate',
        'converted_amount' => 'foreign_amount',
        'invoice_currency_id' => 'invoice_currency_id',
        'payment_date' => 'payment_date',
        'number' => 'number',
        'payment_type_id' => 'payment_type_id',
        'private_notes' => 'private_notes',
        'project' => 'project_id',
        'public_notes' => 'public_notes',
        'tax_amount1' => 'tax_amount1',
        'tax_amount2' => 'tax_amount2',
        'tax_amount3' => 'tax_amount3',
        'tax_name1' => 'tax_name1',
        'tax_name2' => 'tax_name2',
        'tax_name3' => 'tax_name3',
        'tax_rate1' => 'tax_rate1',
        'tax_rate2' => 'tax_rate2',
        'tax_rate3' => 'tax_rate3',
        'transaction_reference' => 'transaction_reference',
        'vendor' => 'vendor_id',
        'invoice' => 'invoice_id',
    ];

    private array $decorate_keys = [
        'client',
        'currency',
        'invoice',
        'category',
        'vendor',
        'project',
        'payment_type_id',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->expense_transformer = new ExpenseTransformer();
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

        $query = Expense::query()
                        ->with('client')
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        $query->cursor()
                ->each(function ($expense) {
                    $this->csv->insertOne($this->buildRow($expense));
                });

        return $this->csv->toString();
    }

    private function buildRow(Expense $expense) :array
    {
        $transformed_expense = $this->expense_transformer->transform($expense);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if (array_key_exists($key, $transformed_expense)) {
                $entity[$keyval] = $transformed_expense[$key];
            } else {
                $entity[$keyval] = '';
            }
        }

        return $this->decorateAdvancedFields($expense, $entity);
    }

    private function decorateAdvancedFields(Expense $expense, array $entity) :array
    {
        if (in_array('currency_id', $this->input['report_keys'])) {
            $entity['currency'] = $expense->currency ? $expense->currency->code : '';
        }

        if (in_array('client_id', $this->input['report_keys'])) {
            $entity['client'] = $expense->client ? $expense->client->present()->name() : '';
        }

        if (in_array('invoice_id', $this->input['report_keys'])) {
            $entity['invoice'] = $expense->invoice ? $expense->invoice->number : '';
        }

        if (in_array('category_id', $this->input['report_keys'])) {
            $entity['category'] = $expense->category ? $expense->category->name : '';
        }

        if (in_array('vendor_id', $this->input['report_keys'])) {
            $entity['vendor'] = $expense->vendor ? $expense->vendor->name : '';
        }

        if (in_array('payment_type_id', $this->input['report_keys'])) {
            $entity['payment_type'] = $expense->payment_type ? $expense->payment_type->name : '';
        }

        if (in_array('project_id', $this->input['report_keys'])) {
            $entity['project'] = $expense->project ? $expense->project->name : '';
        }

        return $entity;
    }
}
