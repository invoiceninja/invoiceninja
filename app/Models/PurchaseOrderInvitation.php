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

use App\Utils\Ninja;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

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

    public function getPortalLink() :string
    {
        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/vendor/';
                break;
            case 'iframe':
                return $domain.'/vendor/';
                break;
            case 'domain':
                return $domain.'/vendor/';
                break;

            default:
                return '';
                break;
        }
    }

    public function getLink() :string
    {
        $entity_type = Str::snake(class_basename($this->entityType()));

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        switch ($this->company->portal_mode) {
            case 'subdomain':
                return $domain.'/vendor/'.$entity_type.'/'.$this->key;
                break;
            case 'iframe':
                return $domain.'/vendor/'.$entity_type.'/'.$this->key;
                break;
            case 'domain':
                return $domain.'/vendor/'.$entity_type.'/'.$this->key;
                break;

            default:
                return '';
                break;
        }
    }

    public function getAdminLink() :string
    {
        return $this->getLink().'?silent=true';
    }
}
