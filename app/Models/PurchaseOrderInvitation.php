<?php


namespace App\Models;


use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderInvitation extends BaseModel
{
    use MakesDates;
    use SoftDeletes;
    use Inviteable;

    protected $fillable = [
        'id',
        'vendor_contact_id',
    ];

    protected $with = [
        'company',
        'contact',
    ];
    protected $touches = ['purchase_order'];

    public function getEntityType()
    {
        return self::class;
    }

    public function entityType()
    {
        return PurchaseOrder::class;
    }

    /**
     * @return mixed
     */
    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo(VendorContact::class, 'vendor_contact_id', 'id')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }


    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getName()
    {
        return $this->key;
    }

    public function markViewed()
    {
        $this->viewed_date = Carbon::now();
        $this->save();
    }


}
