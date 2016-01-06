<?php namespace App\Ninja\Repositories;

use DB;
use Auth;
use Utils;
use Request;
use App\Models\VendorActivity;
use App\Models\Vendor;

class VendorActivityRepository
{
    private $vendor;
    
    public function create($entity, $activityTypeId, $balanceChange = 0, $paidToDateChange = 0, $altEntity = null)
    {
        if ($entity instanceof Vendor) {
            $vendor = $entity;
        } elseif ($entity instanceof Invitation) {
            $vendor = $entity->invoice->vendor;
        } else {
            $vendor = $entity->vendor;
        }

        $this->vendor = $vendor;
        
        // init activity and copy over context
        $activity = self::getBlank($altEntity ?: $vendor);
        $activity = Utils::copyContext($activity, $entity);
        $activity = Utils::copyContext($activity, $altEntity);

        $activity->vendor_id = $vendor->id;
        $activity->activity_type_id = $activityTypeId;
        $activity->adjustment = $balanceChange;
        $activity->balance = $vendor->balance + $balanceChange;

        $keyField = $entity->getKeyField();
        $activity->$keyField = $entity->id;

        $activity->ip = Request::getClientIp();
        $activity->save();

        $vendor->updateBalances($balanceChange, $paidToDateChange);

        return $activity;
    }

    private function getBlank($entity)
    {
        $activity = new VendorActivity();

        if (Auth::check() && Auth::user()->account_id == $entity->account_id) {
            $activity->user_id = Auth::user()->id;
            $activity->account_id = Auth::user()->account_id;
        } else {
            $activity->user_id = $entity->user_id;
            $activity->account_id = $entity->account_id;

            if ( ! $entity instanceof Invitation) {
                $activity->is_system = true;
            }
        }

        $activity->token_id = session('token_id');

        return $activity;
    }

    public function findByVendorId($vendorId)
    {
        return DB::table('vendor_activities')
                    ->join('accounts', 'accounts.id', '=', 'vendor_activities.account_id')
                    ->join('users', 'users.id', '=', 'vendor_activities.user_id')
                    ->join('vendors', 'vendors.id', '=', 'vendor_activities.vendor_id')
                    ->leftJoin('vendor_contacts', 'vendor_contacts.vendor_id', '=', 'vendors.id')
                    ->where('vendors.id', '=', $vendorId)
                    ->where('vendor_contacts.is_primary', '=', 1)
                    ->whereNull('vendor_contacts.deleted_at')
                    ->select(
                        DB::raw('COALESCE(vendors.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(vendors.country_id, accounts.country_id) country_id'),
                        'vendor_activities.id',
                        'vendor_activities.created_at',
                        'vendor_activities.contact_id',
                        'vendor_activities.activity_type_id',
                        'vendor_activities.is_system',
                        'vendor_activities.balance',
                        'vendor_activities.adjustment',
                        'users.first_name as user_first_name',
                        'users.last_name as user_last_name',
                        'users.email as user_email',
                        'vendors.name as vendor_name',
                        'vendors.public_id as vendor_public_id',
                        'vendor_contacts.id as contact',
                        'vendor_contacts.first_name as first_name',
                        'vendor_contacts.last_name as last_name',
                        'vendor_contacts.email as email'
                        
                    );
    }

}