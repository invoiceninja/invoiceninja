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

namespace App\Repositories;

use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * VendorContactRepository.
 */
class VendorContactRepository extends BaseRepository
{
    public $is_primary;

    public function save(array $data, Vendor $vendor): void
    {

        if (isset($data['contacts']) && (count($data['contacts']) !== count($data['contacts'], COUNT_RECURSIVE))) {
            $contacts = collect($data['contacts']);
        } elseif (isset($data['contacts'])) {
            $temp_array[] = $data['contacts'];
            $contacts = collect($temp_array);
        } else {
            $contacts = collect();
        }

        /* Get array of IDs which have been removed from the contacts array and soft delete each contact */
        $vendor->contacts->pluck('id')->diff($contacts->pluck('id'))->each(function ($contact) {
            VendorContact::destroy($contact);
        });

        $this->is_primary = true;
        /* Set first record to primary - always */
        $contacts = $contacts->sortByDesc('is_primary')->map(function ($contact) {
            $contact['is_primary'] = $this->is_primary;
            $this->is_primary = false;

            return $contact;
        });

        //loop and update/create contacts
        $contacts->each(function ($contact) use ($vendor) {
            $update_contact = null;

            if (isset($contact['id'])) {
                $update_contact = VendorContact::find($contact['id']);
            }

            if (! $update_contact) {
                $update_contact = new VendorContact();
                $update_contact->vendor_id = $vendor->id;
                $update_contact->company_id = $vendor->company_id;
                $update_contact->user_id = $vendor->user_id;
                $update_contact->contact_key = Str::random(40);
            }

            if (array_key_exists('email', $contact) && is_null($contact['email'])) {
                $contact['email'] = '';
            }

            $update_contact->fill($contact);

            if (array_key_exists('password', $contact) && strlen($contact['password']) > 1) {
                $update_contact->password = Hash::make($contact['password']);
                $vendor->company->vendor_contacts()->where('email', $update_contact->email)->update(['password' => $update_contact->password]);
            }

            if (array_key_exists('email', $contact)) {
                $update_contact->email = trim($contact['email']);
            }

            $update_contact->saveQuietly();

        });

        $vendor->fresh();

        //always made sure we have one blank contact to maintain state
        if ($vendor->contacts()->count() == 0) {
            $new_contact = new VendorContact();
            $new_contact->vendor_id = $vendor->id;
            $new_contact->company_id = $vendor->company_id;
            $new_contact->user_id = $vendor->user_id;
            $new_contact->contact_key = Str::random(40);
            $new_contact->is_primary = true;
            $new_contact->saveQuietly();
        }
    }
}
