<?php

namespace App\Events\Quote;

use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasDeleted.
 */
class QuoteWasDeleted 
{
    use SerializesModels;
    public $quote;

    /**
     * Create a new event instance.
     *
     * @param $quote
     */
    public function __construct($quote)
    {
        $this->quote = $quote;
    }
}
