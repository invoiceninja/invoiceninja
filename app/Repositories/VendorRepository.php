<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Factory\VendorFactory;
use App\Models\Vendor;
use App\Repositories\VSendorContactRepository;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

/**
 * VendorRepository.
 */
class VendorRepository extends BaseRepository
{
    use GeneratesCounter;
    /**
     * @var vendorContactRepository
     */
    protected $contact_repo;

    /**
     * vendorController constructor.
     * @param vendorContactRepository $contact_repo
     */
    public function __construct(VendorContactRepository $contact_repo)
    {
        $this->contact_repo = $contact_repo;
    }

    /**
     * Gets the class name.
     *
     * @return     string The class name.
     */
    public function getClassName()
    {
        return Vendor::class;
    }

    /**
     * Saves the vendor and its contacts.
     *
     * @param array $data The data
     * @param \App\Models\vendor $vendor The vendor
     *
     * @return     vendor|\App\Models\vendor|null  vendor Object
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function save(array $data, Vendor $vendor) : ?Vendor
    {

        $vendor->fill($data);

        $vendor->save();

        if ($vendor->id_number == '' || ! $vendor->id_number) {
            $vendor->id_number = $this->getNextVendorNumber($vendor);
        } //todo write tests for this and make sure that custom vendor numbers also works as expected from here

        $vendor->save();

        if (isset($data['contacts'])) {
            $contacts = $this->contact_repo->save($data, $vendor);
        }

        if (empty($data['name'])) {
            $data['name'] = $vendor->present()->name();
        }

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $vendor);
        }

        return $vendor;
    }

    /**
     * Store vendors in bulk.
     *
     * @param array $vendor
     * @return vendor|null
     */
    public function create($vendor): ?Vendor
    {
        return $this->save(
            $vendor,
            VendorFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }
}
