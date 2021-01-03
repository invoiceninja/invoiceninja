<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function getEntityType()
    {
        return self::class;
    }

    /**
     * @return BelongsTo
     */
    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
