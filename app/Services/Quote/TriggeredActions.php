<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasEmailed;
use App\Jobs\Entity\EmailEntity;
use App\Models\Quote;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

class TriggeredActions extends AbstractService
{
    use GeneratesCounter;

    private $request;

    private $quote;

    public function __construct(Quote $quote, Request $request)
    {
        $this->request = $request;

        $this->quote = $quote;
    }

    public function run()
    {
        if ($this->request->has('send_email') && $this->request->input('send_email') == 'true') {
            $this->quote = $this->quote->service()->markSent()->save();
            $this->sendEmail();
        }

        if ($this->request->has('mark_sent') && $this->request->input('mark_sent') == 'true') {
            $this->quote = $this->quote->service()->markSent()->save();
        }

        if ($this->request->has('convert') && $this->request->input('convert') == 'true') {
            $this->quote = $this->quote->service()->convert()->save();
        }

        if ($this->request->has('approve') && $this->request->input('approve') == 'true' && in_array($this->quote->status_id, [Quote::STATUS_SENT, Quote::STATUS_DRAFT])) {
            $this->quote = $this->quote->service()->approveWithNoCoversion()->save();
        }

        return $this->quote;
    }

    private function sendEmail()
    {
        $reminder_template = $this->quote->calculateTemplate('quote');
        // $reminder_template = 'email_template_quote';

        $this->quote->invitations->load('contact.client.country', 'quote.client.country', 'quote.company')->each(function ($invitation) use ($reminder_template) {
            EmailEntity::dispatch($invitation, $this->quote->company, $reminder_template);
        });

        if ($this->quote->invitations->count() > 0) {
            event(new QuoteWasEmailed($this->quote->invitations->first(), $this->quote->company, Ninja::eventVars(), 'quote'));
        }
    }
}
