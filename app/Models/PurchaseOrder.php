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


use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Services\PurchaseOrder\PurchaseOrderService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class PurchaseOrder extends BaseModel
{
    use Filterable;
    use SoftDeletes;
    use MakesDates;

    protected $fillable = [
        'number',
        'discount',
        'status_id',
        'last_sent_date',
        'is_deleted',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'total_taxes',
        'uses_inclusive_taxes',
        'is_amount_discount',
        'partial',
        'recurring_id',
        'next_send_date',
        'reminder1_sent',
        'reminder2_sent',
        'reminder3_sent',
        'reminder_last_sent',
        'partial_due_date',
        'project_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'backup',
        'footer',
        'line_items',
        'client_id',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
        'design_id',
        'invoice_id',
        'assigned_user_id',
        'exchange_rate',
        'balance',
        'partial',
        'paid_to_date',
        'vendor_id',
        'last_viewed'
    ];

    protected $casts = [
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_amount_discount' => 'bool',

    ];

    const STATUS_DRAFT = 1;
    const STATUS_SENT = 2;
    const STATUS_ACCEPTED = 3;
    const STATUS_RECEIVED = 4;
    const STATUS_CANCELLED = 5;

    public static function stringStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return ctrans('texts.draft');
                break;
            case self::STATUS_SENT:
                return ctrans('texts.sent');
                break;
            case self::STATUS_ACCEPTED:
                return ctrans('texts.accepted');
                break;
            case self::STATUS_CANCELLED:
                return ctrans('texts.cancelled');
                break;
                // code...
                break;
        }
    }


    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
                break;
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
                break;
            case self::STATUS_ACCEPTED:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.accepted').'</span></h5>';
                break;
            case self::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
                break;
            default:
                // code...
                break;
        }
    }


    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }
    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->save();
            }
        });
    }

    public function pdf_file_path($invitation = null, string $type = 'path', bool $portal = false)
    {
        if (! $invitation) {

            if($this->invitations()->exists())
                $invitation = $this->invitations()->first();
            else{
                $this->service()->createInvitations();
                $invitation = $this->invitations()->first();
            }

        }

        if(!$invitation)
            throw new \Exception('Hard fail, could not create an invitation - is there a valid contact?');

        $file_path = $this->vendor->purchase_order_filepath($invitation).$this->numberFormatter().'.pdf';

        if(Ninja::isHosted() && $portal && Storage::disk(config('filesystems.default'))->exists($file_path)){
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        }
        elseif(Ninja::isHosted() && $portal){
            $file_path = CreatePurchaseOrderPdf::dispatchNow($invitation,config('filesystems.default'));
            return Storage::disk(config('filesystems.default'))->{$type}($file_path);
        }

        if(Storage::disk('public')->exists($file_path))
            return Storage::disk('public')->{$type}($file_path);

        $file_path = CreatePurchaseOrderPdf::dispatchNow($invitation);
        return Storage::disk('public')->{$type}($file_path);
    }

    public function invitations()
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function service()
    {
        return new PurchaseOrderService($this);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->using(Paymentable::class);
    }

    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function calc()
    {
        $purchase_order_calc = null;

        if ($this->uses_inclusive_taxes) {
            $purchase_order_calc = new InvoiceSumInclusive($this);
        } else {
            $purchase_order_calc = new InvoiceSum($this);
        }

        return $purchase_order_calc->build();
    }

    public function translate_entity()
    {
        return ctrans('texts.purchase_order');
    }
}
