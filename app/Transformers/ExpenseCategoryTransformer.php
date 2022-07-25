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

namespace App\Transformers;

use App\Models\ExpenseCategory;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * class ExpenseCategoryTransformer.
 */
class ExpenseCategoryTransformer extends EntityTransformer
{
    use MakesHash;
    use SoftDeletes;

    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    /**
     * @param ExpenseCategory $expense_category
     *
     * @return array
     */
    public function transform(ExpenseCategory $expense_category)
    {
        return [
            'id' => $this->encodePrimaryKey($expense_category->id),
            'user_id' => $this->encodePrimaryKey($expense_category->user_id),
            'name' => (string) $expense_category->name ?: '',
            'color' => (string) $expense_category->color,
            'is_deleted' => (bool) $expense_category->is_deleted,
            'updated_at' => (int) $expense_category->updated_at,
            'archived_at' => (int) $expense_category->deleted_at,
            'created_at' => (int) $expense_category->created_at,
        ];
    }
}
