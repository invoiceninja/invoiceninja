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

namespace App\Services\Quickbooks\Models;

use Carbon\Carbon;
use App\Models\Expense;
use App\DataMapper\ExpenseSync;
use App\Factory\ExpenseFactory;
use App\Interfaces\SyncInterface;
use App\Repositories\ExpenseRepository;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ExpenseTransformer;

class QbExpense implements SyncInterface
{
    protected ExpenseTransformer $expense_transformer;

    protected ExpenseRepository $expense_repository;

    public function __construct(public QuickbooksService $service)
    {
        $this->expense_transformer = new ExpenseTransformer($this->service->company);
        $this->expense_repository = new ExpenseRepository();
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Expense', $id);
    }

    public function syncToNinja(array $records): void
    {

        foreach ($records as $record) {

            $this->syncNinjaExpense($record);

        }

    }

    public function importToNinja(array $records): void
    {

        foreach ($records as $record) {

            $ninja_expense_data = $this->expense_transformer->qbToNinja($record);

            if ($expense = $this->findExpense($ninja_expense_data['id'])) {

                if ($expense->id) {
                    $this->qbExpenseUpdate($ninja_expense_data, $expense);
                }

                if (Expense::where('company_id', $this->service->company->id)
                    ->whereNotNull('number')
                    ->where('number', $ninja_expense_data['number'])
                    ->exists()) {
                    $ninja_expense_data['number'] = 'qb_' . $ninja_expense_data['number'] . '_' . rand(1000, 99999);
                }

                $expense->fill($ninja_expense_data);
                $expense->saveQuietly();

            }

            $ninja_invoice_data = false;


        }

    }

    public function syncToForeign(array $records): void
    {
        foreach ($records as $expense) {
            if (!$expense instanceof Expense) {
                continue;
            }

            // Check if sync direction allows push
            if (!$this->service->syncable('expense', \App\Enum\SyncDirection::PUSH)) {
                continue;
            }

            try {
                // Transform expense to QuickBooks format
                $qb_expense_data = $this->expense_transformer->ninjaToQb($expense);

                // If updating, fetch SyncToken using existing find() method
                if (isset($expense->sync->qb_id) && !empty($expense->sync->qb_id)) {
                    $existing_qb_expense = $this->find($expense->sync->qb_id);
                    if ($existing_qb_expense) {
                        $qb_expense_data['SyncToken'] = $existing_qb_expense->SyncToken ?? '0';
                    }
                }

                // Create or update expense in QuickBooks
                $qb_expense = \QuickBooksOnline\API\Facades\Purchase::create($qb_expense_data);

                if (isset($expense->sync->qb_id) && !empty($expense->sync->qb_id)) {
                    // Update existing expense
                    $result = $this->service->sdk->Update($qb_expense);
                    nlog("QuickBooks: Updated expense {$expense->id} (QB ID: {$expense->sync->qb_id})");
                } else {
                    // Create new expense
                    $result = $this->service->sdk->Add($qb_expense);

                    // Store QB ID in expense sync
                    $sync = new ExpenseSync();
                    $sync->qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');
                    $expense->sync = $sync;
                    $expense->saveQuietly();

                    nlog("QuickBooks: Created expense {$expense->id} (QB ID: {$sync->qb_id})");
                }
            } catch (\Exception $e) {
                nlog("QuickBooks: Error pushing expense {$expense->id} to QuickBooks: {$e->getMessage()}");
                // Continue with next invoice instead of failing completely
                continue;
            }
        }
    }

    private function findExpense(string $id): ?Expense
    {
        $search = Expense::query()
                            ->withTrashed()
                            ->where('company_id', $this->service->company->id)
                            ->where('sync->qb_id', $id);

        if ($search->count() == 0) {
            $expense = ExpenseFactory::create($this->service->company->id, $this->service->company->owner()->id);

            $sync = new ExpenseSync();
            $sync->qb_id = $id;
            $expense->sync = $sync;

            return $expense;
        } elseif ($search->count() == 1) {
            return $this->service->syncable('expense', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
        }

        return null;

    }

    public function sync($id, string $last_updated): void
    {

        $qb_record = $this->find($id);


        if ($this->service->syncable('expense', \App\Enum\SyncDirection::PULL)) {

            $expense = $this->findExpense($id);

            nlog("Comparing QB last updated: " . $last_updated);
            nlog("Comparing Ninja last updated: " . $expense->updated_at);

            if (data_get($qb_record, 'TxnStatus') === 'Voided') {
                $this->delete($id);
                return;
            }

            if (!$expense->id) {
                $this->syncNinjaExpense($qb_record);
            } elseif (Carbon::parse($last_updated)->gt(Carbon::parse($expense->updated_at)) || $qb_record->SyncToken == '0') {
                $ninja_expense_data = $this->expense_transformer->qbToNinja($qb_record);

                $this->expense_repository->save($ninja_expense_data, $expense);

            }

        }
    }

    /**
     * Deletes the invoice from Ninja and sets the sync to null
     *
     * @param string $id
     * @return void
     */
    public function delete($id): void
    {
        $qb_record = $this->find($id);

        if ($this->service->syncable('expense', \App\Enum\SyncDirection::PULL) && $expense = $this->findExpense($id)) {
            $expense->sync = null;
            $expense->saveQuietly();
            $this->expense_repository->delete($expense);
        }
    }
}
