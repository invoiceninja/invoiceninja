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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxRate extends BaseModel
{
    use MakesHash;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'rate',
    ];

    protected $appends = ['tax_rate_id'];

    public function getEntityType()
    {
        return self::class;
    }

    public function getRouteKeyName()
    {
        return 'tax_rate_id';
    }

    public function getTaxRateIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }
}
