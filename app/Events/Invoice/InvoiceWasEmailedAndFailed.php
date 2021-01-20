<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\InvoiceInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasEmailedAndFailed.
 */
class InvoiceWasEmailedAndFailed
{
    use SerializesModels;

    public $invitation;

    public $message;

    public $company;

    public $event_vars;

    public $template;

    /**
     * Create a new event instance.
     *
     * @param InvoiceInvitation $invitation
     * @param Company $company
     * @param string $errors
     * @param array $event_vars
     */
    public function __construct(InvoiceInvitation $invitation, Company $company, string $message, string $template, array $event_vars)
    {
        $this->invitation = $invitation;

        $this->company = $company;

        $this->message = $message;

        $this->event_vars = $event_vars;

        $this->template = $template;
    }
}
