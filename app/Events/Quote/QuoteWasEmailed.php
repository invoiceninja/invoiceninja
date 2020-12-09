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

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasEmailed.
 */
class QuoteWasEmailed
{
    use SerializesModels;

    public $invitation;

    public $company;

    public $notes;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Quote $quote
     * @param string $notes
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(QuoteInvitation $invitation, string $notes, Company $company, array $event_vars)
    {
        $this->invitation = $invitation;
        $this->notes = $notes;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
