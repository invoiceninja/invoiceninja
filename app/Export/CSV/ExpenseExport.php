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

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Expense;
use App\Transformers\ExpenseTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ExpenseExport extends BaseExport
{

    private $expense_transformer;

    private Decorator $decorator;

    public string $date_key = 'date';

    public Writer $csv;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->expense_transformer = new ExpenseTransformer();
        $this->decorator = new Decorator();
    }


    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $query->cursor()
                ->map(function ($resource) {
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();
        
        return array_merge(['columns' => $header], $report);
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->expense_report_keys);
        }

        $query = Expense::query()
                        ->with('client')
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        return $query;

    }

    public function run()
    {
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

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
            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'expense' && array_key_exists($parts[1], $transformed_expense)) {
                $entity[$key] = $transformed_expense[$parts[1]];
            } elseif (array_key_exists($key, $transformed_expense)) {
                $entity[$key] = $transformed_expense[$key];
            } else {
                // nlog($key);
                $entity[$key] = $this->decorator->transform($key, $expense);
                // $entity[$key] = '';
                // $entity[$key] = $this->resolveKey($key, $expense, $this->expense_transformer);
            }

        }

        return $entity;
        // return $this->decorateAdvancedFields($expense, $entity);
    }

    private function decorateAdvancedFields(Expense $expense, array $entity) :array
    {
        if (in_array('expense.currency_id', $this->input['report_keys'])) {
            $entity['expense.currency_id'] = $expense->currency ? $expense->currency->code : '';
        }

        if (in_array('expense.client_id', $this->input['report_keys'])) {
            $entity['expense.client'] = $expense->client ? $expense->client->present()->name() : '';
        }

        if (in_array('expense.invoice_id', $this->input['report_keys'])) {
            $entity['expense.invoice_id'] = $expense->invoice ? $expense->invoice->number : '';
        }

        if (in_array('expense.category', $this->input['report_keys'])) {
            $entity['expense.category'] = $expense->category ? $expense->category->name : '';
        }

        if (in_array('expense.vendor_id', $this->input['report_keys'])) {
            $entity['expense.vendor'] = $expense->vendor ? $expense->vendor->name : '';
        }

        if (in_array('expense.payment_type_id', $this->input['report_keys'])) {
            $entity['expense.payment_type_id'] = $expense->payment_type ? $expense->payment_type->name : '';
        }

        if (in_array('expense.project_id', $this->input['report_keys'])) {
            $entity['expense.project_id'] = $expense->project ? $expense->project->name : '';
        }

        if (in_array('expense.user', $this->input['report_keys'])) {
            $entity['expense.user'] = $expense->user ? $expense->user->present()->name() : '';
        }

        if (in_array('expense.assigned_user', $this->input['report_keys'])) {
            $entity['expense.assigned_user'] = $expense->assigned_user ? $expense->assigned_user->present()->name() : '';
        }

        if (in_array('expense.category_id', $this->input['report_keys'])) {
            $entity['expense.category_id'] = $expense->category ? $expense->category->name : '';
        }

        return $entity;
    }
}
