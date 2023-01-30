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

namespace App\Events\Misc;

use App\Models\Invoice;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvitationWasViewed.
 */
class InvitationWasViewed
{
    use SerializesModels;

    /**
     * @var Invoice
     */
    public $invitation;

    public $entity;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param $entity
     * @param $invitation
     * @param $company
     * @param $event_vars
     */
    public function __construct($entity, $invitation, $company, $event_vars)
    {
        $this->entity = $entity;
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
