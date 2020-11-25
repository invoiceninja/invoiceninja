<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TaskStatus.
 */
class TaskStatus extends BaseModel
{
    use SoftDeletes;

    /**
     * @var bool
     */
    public $timestamps = true;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    protected $fillable = ['name'];
}
