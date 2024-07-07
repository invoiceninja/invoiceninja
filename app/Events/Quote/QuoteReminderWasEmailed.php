<?php
/**
 * Quote Ninja (https://Quoteninja.com).
 *
 * @link https://github.com/Quoteninja/Quoteninja source repository
 *
 * @copyright Copyright (c) 2024. Quote Ninja LLC (https://Quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Quote;

use App\Models\Company;
use App\Models\QuoteInvitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteReminderWasEmailed.
 */
class QuoteReminderWasEmailed
{
    use SerializesModels;

    public function __construct(public QuoteInvitation $invitation, public Company $company, public array $event_vars, public string $template)
    {
    }
}
