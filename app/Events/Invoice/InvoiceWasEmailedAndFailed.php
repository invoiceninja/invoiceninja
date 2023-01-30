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
     * @param $invitation
     * @param Company $company
     * @param string $errors
     * @param array $event_vars
     */
    public function __construct($invitation, Company $company, string $message, string $template, array $event_vars)
    {
        $this->invitation = $invitation;

        $this->company = $company;

        $this->message = $message;

        $this->event_vars = $event_vars;

        $this->template = $template;
    }
}
