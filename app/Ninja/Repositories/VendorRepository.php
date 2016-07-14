<?php

namespace App\Ninja\Repositories;

use DB;
use App\Models\Vendor;

/**
 * Class VendorRepository
 */
class VendorRepository extends BaseRepository
{
    /**
     * @return string
     */
    public function getClassName()
    {
        return 'App\Models\Vendor';
    }

    /**
     * @return mixed
     */
    public function all()
    {
        return Vendor::scope()
                ->with('user', 'vendor_contacts', 'country')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    /**
     * @param null $filter
     *
     * @return $this
     */
    public function find($filter = null)
    {
        $query = DB::table('vendors')
                    ->join('accounts', 'accounts.id', '=', 'vendors.account_id')
                    ->join('vendor_contacts', 'vendor_contacts.vendor_id', '=', 'vendors.id')
                    ->where('vendors.account_id', '=', \Auth::user()->account_id)
                    ->where('vendor_contacts.is_primary', '=', true)
                    ->where('vendor_contacts.deleted_at', '=', null)
                    ->select(
                        DB::raw('COALESCE(vendors.currency_id, accounts.currency_id) currency_id'),
                        DB::raw('COALESCE(vendors.country_id, accounts.country_id) country_id'),
                        'vendors.public_id',
                        'vendors.name',
                        'vendor_contacts.first_name',
                        'vendor_contacts.last_name',
                        'vendors.created_at',
                        'vendors.work_phone',
                        'vendors.city',
                        'vendor_contacts.email',
                        'vendors.deleted_at',
                        'vendors.is_deleted',
                        'vendors.user_id'
                    );

        if (!\Session::get('show_trash:vendor')) {
            $query->where('vendors.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('vendors.name', 'like', '%'.$filter.'%')
                      ->orWhere('vendor_contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('vendor_contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('vendor_contacts.email', 'like', '%'.$filter.'%');
            });
        }

        return $query;
    }

    /**
     * @param array $data
     * @param Vendor|null $vendor
     *
     * @return Vendor|mixed
     */
    public function save(array $data, Vendor $vendor = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($vendor) {
            // do nothing
        } elseif (!$publicId || $publicId == '-1') {
            $vendor = Vendor::createNew();
        } else {
            $vendor = Vendor::scope($publicId)->with('vendor_contacts')->firstOrFail();
            \Log::warning('Entity not set in vendor repo save');
        }

        $vendor->fill($data);
        $vendor->save();
        
        return $vendor;
    }
}
