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

namespace App\Events\Invoice;

use App\Models\Company;
use App\Models\InvoiceInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasEmailed.
 */
class InvoiceWasEmailed
{
    use SerializesModels;

    /**
     * @var InvoiceInvitation
     */
    public $invitation;

    public $company;

    public $event_vars;

    public $template;

    /**
     * Create a new event instance.
     *
     * @param InvoiceInvitation $invitation
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(InvoiceInvitation $invitation, Company $company, array $event_vars, string $template)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
        $this->template = $template;
    }
}
