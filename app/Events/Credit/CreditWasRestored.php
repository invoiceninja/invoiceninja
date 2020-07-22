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

namespace App\Events\Credit;

use App\Models\Credit;
use Illuminate\Queue\SerializesModels;

/**
 * Class CreditWasRestored.
 */
class CreditWasRestored
{
    use SerializesModels;

    /**
     * @var Client
     */
    public $credit;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Client $client
     */
    public function __construct(Credit $credit, Company $company, array $event_vars)
    {
        $this->credit = $credit;
        $this->company = $company;
        $this->event_vars = $event_vars;

    }
}
