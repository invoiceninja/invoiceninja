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

use App\Models\Presenters\VendorPresenter;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

class Vendor extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use GeneratesCounter;
    use PresentableTrait;

    protected $fillable = [
        'name',
        'assigned_user_id',
        'id_number',
        'vat_number',
        'phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'public_notes',
        'currency_id',
        'website',
        'transaction_name',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'number',
    ];

    protected $casts = [
        'country_id' => 'string',
        'currency_id' => 'string',
        'is_deleted' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    protected $with = [
    //    'contacts',
    ];

    protected $presenter = VendorPresenter::class;

    public function getEntityType()
    {
        return self::class;
    }

    public function primary_contact()
    {
        return $this->hasMany(VendorContact::class)->where('is_primary', true);
    }


    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id');
    }

    public function contacts()
    {
        return $this->hasMany(VendorContact::class)->orderBy('is_primary', 'desc');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
