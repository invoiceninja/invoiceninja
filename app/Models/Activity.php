<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
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

class Activity extends StaticModel
{

    use MakesHash;
    
    const CREATE_CLIENT=1; //
    const ARCHIVE_CLIENT=2; //
    const DELETE_CLIENT=3; //
    const CREATE_INVOICE=4; //
    const UPDATE_INVOICE=5; //
    const EMAIL_INVOICE=6; //
    const VIEW_INVOICE=7; //
    const ARCHIVE_INVOICE=8; //
    const DELETE_INVOICE=9; //
    const CREATE_PAYMENT=10; //
    const UPDATE_PAYMENT=11; //
    const ARCHIVE_PAYMENT=12; //
    const DELETE_PAYMENT=13; //
    const CREATE_CREDIT=14; //
    const UPDATE_CREDIT=15; //
    const ARCHIVE_CREDIT=16; //
    const DELETE_CREDIT=17; //
    const CREATE_QUOTE=18; //
    const UPDATE_QUOTE=19; //
    const EMAIL_QUOTE=20; //
    const VIEW_QUOTE=21; //
    const ARCHIVE_QUOTE=22; //
    const DELETE_QUOTE=23; //
    const RESTORE_QUOTE=24; //
    const RESTORE_INVOICE=25; //
    const RESTORE_CLIENT=26; //
    const RESTORE_PAYMENT=27; //
    const RESTORE_CREDIT=28; //
    const APPROVE_QUOTE=29; //
    const CREATE_VENDOR=30;
    const ARCHIVE_VENDOR=31;
    const DELETE_VENDOR=32;
    const RESTORE_VENDOR=33;
    const CREATE_EXPENSE=34;
    const ARCHIVE_EXPENSE=35;
    const DELETE_EXPENSE=36;
    const RESTORE_EXPENSE=37;

    const VOIDED_PAYMENT=39; //
    const REFUNDED_PAYMENT=40; //
    const FAILED_PAYMENT=41;
    const CREATE_TASK=42;
    const UPDATE_TASK=43;
    const ARCHIVE_TASK=44;
    const DELETE_TASK=45;
    const RESTORE_TASK=46;
    const UPDATE_EXPENSE=47;

    const CREATE_USER=48; // only used in CreateUser::job
    const UPDATE_USER=49; // not needed?
    const ARCHIVE_USER=50; // not needed?
    const DELETE_USER=51; // not needed?
    const RESTORE_USER=52; // not needed?
    const MARK_SENT_INVOICE=53; // not needed?
    const PAID_INVOICE=54;
    const EMAIL_INVOICE_FAILED=57;
    const REVERSED_INVOICE=58;
    const CANCELLED_INVOICE=59;
    
    protected $casts = [
        'is_system' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    public function getEntityType()
    {
        return Activity::class;
    }


    public function backup()
    {
        return $this->hasOne(Backup::class);
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function contact()
    {
        return $this->belongsTo(ClientContact::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return mixed
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    // public function task()
    // {
    //     return $this->belongsTo(Task::class)->withTrashed();
    // }

    // public function expense()
    // {
    //     return $this->belongsTo(Expense::class)->withTrashed();
    // }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }


    public function resolveRouteBinding($value)
    {
        if (is_numeric($value)) {
            throw new ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            //->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }
}
