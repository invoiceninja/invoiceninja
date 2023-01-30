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

namespace App\Events\Credit;

use App\Models\Company;
use App\Models\CreditInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class CreditWasViewed.
 */
class CreditWasViewed
{
    use SerializesModels;

    public $invitation;

    public $company;

    public $event_vars;

    public function __construct(CreditInvitation $invitation, Company $company, array $event_vars)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
