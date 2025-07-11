<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\ProductEquipment
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $product_id
 * @property int|null $assigned_user_id
 * @property int|null $product_id
 * @property int|string $serial_number
 * @property int|null $client_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $private_notes
 * @property string|null $public_notes
 * @property int|null $deleted_at
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property bool $is_deleted
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User $user
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Product $product
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\ProductAllocation|null $product_allocations
 * @mixin \Eloquent
 */
class ProductEquipment extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'product_id',
        'serial_number',
        'client_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'public_notes',
        'private_notes',
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

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

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function product_allocations()
    {
        return $this->hasMany(ProductAllocation::class)->withTrashed();
    }
}
