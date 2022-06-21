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

namespace App\Services\Quote;

use App\Events\Quote\QuoteWasMarkedSent;
use App\Models\Quote;
use App\Utils\Ninja;
use Carbon\Carbon;

class MarkSent
{
    private $client;

    private $quote;

    public function __construct($client, $quote)
    {
        $this->client = $client;
        $this->quote = $quote;
    }

    public function run()
    {

        /* Return immediately if status is not draft */
        if ($this->quote->status_id != Quote::STATUS_DRAFT) {
            return $this->quote;
        }

        $this->quote->markInvitationsSent();

        if ($this->quote->due_date != '' || $this->quote->client->getSetting('valid_until') == '') {
        } else {
            $this->quote->due_date = Carbon::parse($this->quote->date)->addDays($this->quote->client->getSetting('valid_until'));
        }

        $this->quote
             ->service()
             ->setStatus(Quote::STATUS_SENT)
             ->applyNumber()
             ->touchPdf()
             ->save();

        event(new QuoteWasMarkedSent($this->quote, $this->quote->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->quote;
    }
}
