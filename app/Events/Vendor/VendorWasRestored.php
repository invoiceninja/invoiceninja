<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Vendor;

use App\Models\Company;
use App\Models\Vendor;
use Illuminate\Queue\SerializesModels;

/**
 * Class VendorWasRestored.
 */
class VendorWasRestored
{
    use SerializesModels;

    /**
     * @var Vendor
     */
    public $vendor;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Vendor $vendor
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Vendor $vendor, $fromDeleted, Company $company, array $event_vars)
    {
        $this->vendor = $vendor;
        $this->fromDeleted = $fromDeleted;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
