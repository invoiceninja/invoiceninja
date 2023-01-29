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

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\QuoteInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasViewed.
 */
class QuoteWasViewed
{
    use SerializesModels;

    public $invitation;

    public $company;

    public $event_vars;

    public function __construct(QuoteInvitation $invitation, Company $company, array $event_vars)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
