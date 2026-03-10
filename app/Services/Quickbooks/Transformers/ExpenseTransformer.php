<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use Illuminate\Support\Carbon;
use App\DataMapper\ExpenseSync;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Currency;
use App\Factory\ExpenseCategoryFactory;

/**
 * Class ExpenseTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    /**
     * Transform an Invoice Ninja expense to QuickBooks Purchase/Expense format.
     *
     * @return array Payload for QuickBooks API (Add/Update Expense or Purchase)
     */
    public function ninjaToQb(Expense $expense): array
    {
        // QuickBooks DocNumber max length is 21 characters, PrivateNote max length is 4000 characters
        $payload = [
            'TotalAmt' => (float) $expense->amount,
            'TxnDate' => $expense->date ?? Carbon::now()->format('Y-m-d'),
            'PrivateNote' => mb_substr($expense->private_notes ?? '', 0, 4000),
            'DocNumber' => mb_substr($expense->transaction_reference ?? $expense->number ?? '', 0, 21),
        ];

        $currency = $expense->currency_id
            ? Currency::find($expense->currency_id)
            : null;
        if ($currency && $currency->code) {
            $payload['CurrencyRef'] = ['value' => $currency->code];
        }

        if ($expense->category_id) {
            $category = ExpenseCategory::withTrashed()
                ->where('company_id', $this->company->id)
                ->find($expense->category_id);
            if ($category && isset($category->sync->qb_id) && $category->sync->qb_id !== '') {
                $payload['AccountRef'] = ['value' => $category->sync->qb_id];
            }
        }


        // Vendors are not syncable - yet -.
        // if ($expense->vendor_id) {
        //     $vendor = $expense->vendor;
        //     if ($vendor && $vendor->number !== null && $vendor->number !== '') {
        //         $payload['EntityRef'] = [
        //             'type' => 'Vendor',
        //             'value' => $vendor->number,
        //         ];
        //     }
        // }

        if ($expense->client_id) {
            $client = $expense->client;
            if ($client && isset($client->sync->qb_id) && $client->sync->qb_id !== '') {
                $payload['EntityRef'] = [
                    'type' => 'Customer',
                    'value' => $client->sync->qb_id,
                ];
            }
        }

        return $payload;
    }

    public function transform(mixed $data): array
    {

        $expense = [
            'id' => data_get($data, 'Id', false),
            'amount' => data_get($data, 'TotalAmt'),
            'date' => Carbon::parse(data_get($data, 'TxnDate', ''))->format('Y-m-d'),
            'currency_id' => $this->resolveCurrency(data_get($data, 'CurrencyRef', '')),
            'private_notes' => data_get($data, 'PrivateNote', null),
            'public_notes' => null,
            'number' => data_get($data, 'Id', null),
            'transaction_reference' => data_get($data, 'DocNumber', ''),
            'should_be_invoiced' => false,
            'invoice_documents' => false,
            'uses_inclusive_taxes' => false,
            'calculate_tax_by_amount' => false,
            'category_id' => $this->getCategoryId($data),
        ];


        $mergeable = $this->resolveAttachedEntity($data);

        $expense =  array_merge($expense, $mergeable);

        nlog($expense);

        return $expense;

    }

    private function resolveAttachedEntity($entity)
    {

        $related = data_get($entity, 'EntityRef.type');
        $entity_id = data_get($entity, 'EntityRef');

        switch ($related) {
            case 'Vendor':
                return ['vendor_id' => $this->getVendorId($entity_id)];
            case 'Client':
                return ['client_id' => $this->getClientId($entity_id)];
            default:
                return [];
        }

    }

    /**
     * getCategoryId
     *
     * Gets the category ID based on the QB ID or creates a new category!
     *
     * @param  mixed $data
     * @return int
     */
    private function getCategoryId(mixed $data): int
    {

        $name = data_get($data, 'AccountRef.name', '');
        $qb_id = data_get($data, 'AccountRef.Id', false);

        if ($qb_id) {
            $category = ExpenseCategory::withTrashed()
                                    ->where('company_id', $this->company->id)
                                    ->where('sync->qb_id', $qb_id)
                                    ->first();

            if ($category) {
                return $category->id;
            }
        }

        $category = ExpenseCategoryFactory::create($this->company->id, $this->company->owner()->id);
        $category->name = $name;

        $sync = new ExpenseSync();
        $sync->qb_id = $qb_id;

        $category->sync = $sync;
        $category->save();

        return $category->id;
    }

}
