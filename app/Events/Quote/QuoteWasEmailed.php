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
 * Class QuoteWasEmailed.
 */
class QuoteWasEmailed
{
    use SerializesModels;

    public function __construct(public QuoteInvitation $invitation, public Company $company, public array $event_vars, public string $template)
    {
    }
}
