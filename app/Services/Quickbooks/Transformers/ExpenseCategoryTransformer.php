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

namespace App\Services\Quickbooks\Transformers;

/**
 * Maps QuickBooks expense account objects (IPPAccount) to a simple structure.
 *
 * Used for expense categories: retains Id, Name, FullyQualifiedName, and ParentRef
 * (AccountType = Expense / Cost of Goods Sold).
 */
class ExpenseCategoryTransformer
{
    /**
     * Transform a single QuickBooks expense account (IPPAccount or array) to a simple map.
     *
     * @param  object|array  $account  Raw account from QuickBooks API (e.g. IPPAccount)
     * @return array{id: string, name: string, fully_qualified_name: string}
     */
    public function transform(object|array $account): array
    {
        return [
            'id' => (string) (data_get($account, 'Id.value') ?? data_get($account, 'Id') ?? ''),
            'name' => (string) (data_get($account, 'Name') ?? ''),
            'fully_qualified_name' => (string) (data_get($account, 'FullyQualifiedName') ?? ''),
            // 'parent_ref' => (string) (data_get($account, 'ParentRef.value') ?? data_get($account, 'ParentRef') ?? ''),
        ];
    }

    /**
     * Transform an array of QuickBooks expense account objects.
     *
     * @param  array  $accounts  Array of IPPAccount (or array) from QuickBooks API
     * @return array
     */
    public function transformMany(array $accounts): array
    {
        $result = [];
        foreach ($accounts as $account) {
            $mapped = $this->transform($account);
            if ($mapped['id'] !== '') {
                $result[] = $mapped;
            }
        }
        return $result;
    }
}
