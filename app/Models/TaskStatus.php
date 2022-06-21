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

namespace App\Models;

use App\Models\Filterable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TaskStatus.
 */
class TaskStatus extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'color',
        'status_order',
    ];
}
