<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\General;

use App\Models\Company;
use Illuminate\Queue\SerializesModels;

/**
 * Class EntityWasEmailed.
 */
class EntityWasEmailed
{
    use SerializesModels;

    public $invitation;

    public $company;

    public $event_vars;

    public $template;

    public function __construct($invitation, Company $company, array $event_vars, string $template)
    {
        $this->invitation = $invitation;
        $this->company = $company;
        $this->event_vars = $event_vars;
        $this->template = $template;
    }
}
