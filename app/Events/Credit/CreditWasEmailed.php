<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Credit;

use App\Models\Company;
use App\Models\CreditInvitation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditWasEmailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $invitation;

    public $company;

    public $event_vars;

    public $template;

    /**
     * Create a new event instance.
     *
     * @param Credit $credit
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(CreditInvitation $invitation, Company $company, array $event_vars, string $template)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
        $this->template = $template;
    }
}
