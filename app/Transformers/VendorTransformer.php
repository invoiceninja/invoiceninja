<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Transformers;

use App\Models\Activity;
use App\Models\Document;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;

/**
 * class VendorTransformer.
 */
class VendorTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'contacts',
        'documents',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'activities',
    ];

    /**
     * @param Vendor $vendor
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeActivities(Vendor $vendor)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($vendor->activities, $transformer, Activity::class);
    }

    /**
     * @param Vendor $vendor
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function includeContacts(Vendor $vendor)
    {
        $transformer = new VendorContactTransformer($this->serializer);

        return $this->includeCollection($vendor->contacts, $transformer, VendorContact::class);
    }

    public function includeDocuments(Vendor $vendor)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($vendor->documents, $transformer, Document::class);
    }

    /**
     * @param Vendor $vendor
     *
     * @return array
     */
    public function transform(Vendor $vendor)
    {
        return [
            'id' => $this->encodePrimaryKey($vendor->id),
            'user_id' => $this->encodePrimaryKey($vendor->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($vendor->assigned_user_id),
            'name' => $vendor->name ?: '',
            'website' => $vendor->website ?: '',
            'private_notes' => $vendor->private_notes ?: '',
            'public_notes' => $vendor->public_notes ?: '',
            'last_login' => (int) $vendor->last_login,
            'address1' => $vendor->address1 ?: '',
            'address2' => $vendor->address2 ?: '',
            'phone' => $vendor->phone ?: '',
            'city' => $vendor->city ?: '',
            'state' => $vendor->state ?: '',
            'postal_code' => $vendor->postal_code ?: '',
            'country_id' => (string) $vendor->country_id ?: '',
            'currency_id' => (string) $vendor->currency_id ?: '',
            'custom_value1' => $vendor->custom_value1 ?: '',
            'custom_value2' => $vendor->custom_value2 ?: '',
            'custom_value3' => $vendor->custom_value3 ?: '',
            'custom_value4' => $vendor->custom_value4 ?: '',
            'is_deleted' => (bool) $vendor->is_deleted,
            'vat_number' => (string) $vendor->vat_number ?: '',
            'id_number' => (string) $vendor->id_number ?: '',
            'updated_at' => (int) $vendor->updated_at,
            'archived_at' => (int) $vendor->deleted_at,
            'created_at' => (int) $vendor->created_at,
            'number' => (string) $vendor->number ?: '',
            'language_id' => (string) $vendor->language_id ?: '',
            'classification' => (string) $vendor->classification ?: '',
            'display_name' => (string) $vendor->present()->name(),
            'routing_id' => (string) $vendor->routing_id ?: '',
            'is_tax_exempt' => (bool) $vendor->is_tax_exempt,
        ];
    }
}
