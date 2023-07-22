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
use App\Models\Company;
use App\Models\Expense;
use App\Transformers\ExpenseTransformer;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ExpenseExport extends BaseExport
{

    private $expense_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    public array $entity_keys = [
        'amount' => 'expense.amount',
        'category' => 'expense.category',
        'client' => 'expense.client_id',
        'custom_value1' => 'expense.custom_value1',
        'custom_value2' => 'expense.custom_value2',
        'custom_value3' => 'expense.custom_value3',
        'custom_value4' => 'expense.custom_value4',
        'currency' => 'expense.currency_id',
        'date' => 'expense.date',
        'exchange_rate' => 'expense.exchange_rate',
        'converted_amount' => 'expense.foreign_amount',
        'invoice_currency_id' => 'expense.invoice_currency_id',
        'payment_date' => 'expense.payment_date',
        'number' => 'expense.number',
        'payment_type_id' => 'expense.payment_type_id',
        'private_notes' => 'expense.private_notes',
        'project' => 'expense.project_id',
        'public_notes' => 'expense.public_notes',
        'tax_amount1' => 'expense.tax_amount1',
        'tax_amount2' => 'expense.tax_amount2',
        'tax_amount3' => 'expense.tax_amount3',
        'tax_name1' => 'expense.tax_name1',
        'tax_name2' => 'expense.tax_name2',
        'tax_name3' => 'expense.tax_name3',
        'tax_rate1' => 'expense.tax_rate1',
        'tax_rate2' => 'expense.tax_rate2',
        'tax_rate3' => 'expense.tax_rate3',
        'transaction_reference' => 'expense.transaction_reference',
        'vendor' => 'expense.vendor_id',
        'invoice' => 'expense.invoice_id',
        'user' => 'expense.user',
        'assigned_user' => 'expense.assigned_user',
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
            $parts = explode('.', $key);
            $keyval = array_search($key, $this->entity_keys);

            if (is_array($parts) && $parts[0] == 'expense' && array_key_exists($parts[1], $transformed_expense)) {
                $entity[$key] = $transformed_expense[$parts[1]];
            } elseif (array_key_exists($key, $transformed_expense)) {
                $entity[$key] = $transformed_expense[$key];
            } else {
                $entity[$key] = $this->resolveKey($key, $expense, $this->expense_transformer);
            }

        }

        return $this->decorateAdvancedFields($expense, $entity);
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

        return $entity;
    }
}
