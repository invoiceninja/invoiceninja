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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'product_key',
        'notes',
        'cost',
        'price',
        'quantity',
        'tax_name1',
        'tax_name2',
        'tax_name3',
        'tax_rate1',
        'tax_rate2',
        'tax_rate3',
        'in_stock_quantity',
        'stock_notification_threshold',
        'stock_notification',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function translate_entity()
    {
        return ctrans('texts.product');
    }
}
