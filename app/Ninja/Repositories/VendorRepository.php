<?php namespace App\Ninja\Repositories;

use DB;
use App\Ninja\Repositories\BaseRepository;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\Activity;

class VendorRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Vendor';
    }

    public function all()
    {
        return Vendor::scope()
                ->with('user', 'vendorcontacts', 'country')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

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
                        'vendors.balance',
                        //'vendors.last_login',
                        'vendors.created_at',
                        'vendors.work_phone',
                        'vendor_contacts.email',
                        'vendors.deleted_at',
                        'vendors.is_deleted'
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
    
    public function save($data)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (!$publicId || $publicId == '-1') {
            $vendor = Vendor::createNew();
        } else {
            $vendor = Vendor::scope($publicId)->with('vendorcontacts')->firstOrFail();
        }

        $vendor->fill($data);
        $vendor->save();

        
        if ( ! isset($data['vendor_contact']) && ! isset($data['vendor_contacts'])) {
            return $vendor;
        }
        
        
        $first              = true;
        $vendorcontacts     = isset($data['vendor_contact']) ? [$data['vendor_contact']] : $data['vendor_contacts'];
        $vendorcontactIds   = [];

        foreach ($vendorcontacts as $vendorcontact) {
            $vendorcontact      = $vendor->addVendorContact($vendorcontact, $first);
            $vendorcontactIds[] = $vendorcontact->public_id;
            $first              = false;
        }

        foreach ($vendor->vendorcontacts as $vendorcontact) {
            if (!in_array($vendorcontact->public_id, $vendorcontactIds)) {
                $vendorcontact->delete();
            }
        }

        return $vendor;
    }
}
