<?php

namespace App\Events;

use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteWasEmailed.
 */
class QuoteWasEmailed extends Event
{
    use SerializesModels;
    public $quote;

    /**
     * @var string
     */
    public $notes;

    /**
     * Create a new event instance.
     *
     * @param $quote
     */
    public function __construct($quote, $notes)
    {
        $this->quote = $quote;
        $this->notes = $notes;
    }
}
