<?php namespace App\Ninja\Transformers;

use App\Models\Account;
use App\Models\Vendor;
use App\Models\Contact;
use League\Fractal;

// vendor
/**
 * @SWG\Definition(definition="Vendor", @SWG\Xml(name="Vendor"))
 */

class VendorTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="balance", type="float", example=10, readOnly=true)
    * @SWG\Property(property="paid_to_date", type="float", example=10, readOnly=true)
    * @SWG\Property(property="user_id", type="integer", example=1)
    * @SWG\Property(property="account_key", type="string", example="123456")
    * @SWG\Property(property="updated_at", type="timestamp", example="")
    * @SWG\Property(property="archived_at", type="timestamp", example="1451160233")
    * @SWG\Property(property="address1", type="string", example="10 Main St.")
    * @SWG\Property(property="address2", type="string", example="1st Floor")
    * @SWG\Property(property="city", type="string", example="New York")
    * @SWG\Property(property="state", type="string", example="NY")
    * @SWG\Property(property="postal_code", type="string", example=10010)
    * @SWG\Property(property="country_id", type="integer", example=840)
    * @SWG\Property(property="work_phone", type="string", example="(212) 555-1212")
    * @SWG\Property(property="private_notes", type="string", example="Notes...")
    * @SWG\Property(property="last_login", type="date-time", example="2016-01-01 12:10:00")
    * @SWG\Property(property="website", type="string", example="http://www.example.com")
    * @SWG\Property(property="industry_id", type="integer", example=1)
    * @SWG\Property(property="size_id", type="integer", example=1)
    * @SWG\Property(property="is_deleted", type="boolean", example=false)
    * @SWG\Property(property="payment_terms", type="", example=30)
    * @SWG\Property(property="custom_value1", type="string", example="Value")
    * @SWG\Property(property="custom_value2", type="string", example="Value")
    * @SWG\Property(property="vat_number", type="string", example="123456")
    * @SWG\Property(property="id_number", type="string", example="123456")
    * @SWG\Property(property="language_id", type="integer", example=1)
    */

    protected $availableIncludes = [
        'contacts',
        'invoices',
    ];
    
    public function includeContacts(Vendor $vendor)
    {
        $transformer = new ContactTransformer($this->account, $this->serializer);
        return $this->includeCollection($vendor->contacts, $transformer, ENTITY_CONTACT);
    }

    public function includeInvoices(Vendor $vendor)
    {
        $transformer = new InvoiceTransformer($this->account, $this->serializer);
        return $this->includeCollection($vendor->invoices, $transformer, ENTITY_INVOICE);
    }

    public function transform(Vendor $vendor)
    {
        return [
            'id' => (int) $vendor->public_id,
            'name' => $vendor->name,
            'balance' => (float) $vendor->balance,
            'paid_to_date' => (float) $vendor->paid_to_date,
            'user_id' => (int) $vendor->user->public_id + 1,
            'account_key' => $this->account->account_key,
            'updated_at' => $this->getTimestamp($vendor->updated_at),
            'archived_at' => $this->getTimestamp($vendor->deleted_at),
            'address1' => $vendor->address1,
            'address2' => $vendor->address2,
            'city' => $vendor->city,
            'state' => $vendor->state,
            'postal_code' => $vendor->postal_code,
            'country_id' => (int) $vendor->country_id,
            'work_phone' => $vendor->work_phone,
            'private_notes' => $vendor->private_notes,
            'last_login' => $vendor->last_login,
            'website' => $vendor->website,
            'industry_id' => (int) $vendor->industry_id,
            'size_id' => (int) $vendor->size_id,
            'is_deleted' => (bool) $vendor->is_deleted,
            'payment_terms' => (int) $vendor->payment_terms,
            'vat_number' => $vendor->vat_number,
            'id_number' => $vendor->id_number,
            'language_id' => (int) $vendor->language_id,
            'currency_id' => (int) $vendor->currency_id
        ];
    }
}