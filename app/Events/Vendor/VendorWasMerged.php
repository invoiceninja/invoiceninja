<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Vendor;

use App\Models\Vendor;
use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class ClientWasMerged.
 */
class VendorWasMerged
{
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param string $mergeable_vendor
     * @param Vendor $vendor
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(public string $mergeable_vendor, public Vendor $vendor, public Company $company, public array $event_vars) {}
}
