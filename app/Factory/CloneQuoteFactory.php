<?php
/**
 * quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\Quote;

class CloneQuoteFactory
{
    public static function create($quote, $user_id)
    {
        $clone_quote = $quote->replicate();
        $clone_quote->status_id = Quote::STATUS_DRAFT;
        $clone_quote->number = null;
        $clone_quote->date = null;
        $clone_quote->due_date = null;
        $clone_quote->partial_due_date = null;
        $clone_quote->user_id = $user_id;
        //$clone_quote->balance = $quote->amount;
        $clone_quote->amount = $quote->amount;
        $clone_quote->line_items = $quote->line_items;

        return $clone_quote;
    }
}
