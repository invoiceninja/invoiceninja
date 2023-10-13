<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;
use League\CommonMark\CommonMarkConverter;

/**
 * App\Models\Product
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $product_key
 * @property string|null $notes
 * @property float $cost
 * @property float $price
 * @property float $quantity
 * @property string|null $tax_name1
 * @property float $tax_rate1
 * @property string|null $tax_name2
 * @property float $tax_rate2
 * @property string|null $tax_name3
 * @property float $tax_rate3
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property bool $is_deleted
 * @property float $in_stock_quantity
 * @property bool $stock_notification
 * @property int $stock_notification_threshold
 * @property int|null $max_quantity
 * @property string|null $product_image
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Company $company
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @property int|null $tax_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @method static \Illuminate\Database\Eloquent\Builder|Product whereTaxId($value)
 * @mixin \Eloquent
 */
class Product extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    public const PRODUCT_TYPE_PHYSICAL = 1;
    public const PRODUCT_TYPE_SERVICE = 2;
    public const PRODUCT_TYPE_DIGITAL = 3;
    public const PRODUCT_TYPE_SHIPPING = 4;
    public const PRODUCT_TYPE_EXEMPT = 5;
    public const PRODUCT_TYPE_REDUCED_TAX = 6;
    public const PRODUCT_TYPE_OVERRIDE_TAX = 7;
    public const PRODUCT_TYPE_ZERO_RATED = 8;
    public const PRODUCT_TYPE_REVERSE_TAX = 9;

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
        'max_quantity',
        'product_image',
        'tax_id',
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

    public function markdownNotes()
    {
        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
            'renderer' => [
                'soft_break' => '<br>',
            ],
        ]);

        return $converter->convert($this->notes ?? '');
    }

    public function portalUrl($use_react_url): string
    {
        return $use_react_url ? config('ninja.react_url') . "/#/products/{$this->hashed_id}/edit" : config('ninja.app_url');
    }
}
