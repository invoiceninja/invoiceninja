<?php

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Models\RecurringQuote;

class ActionRecurringQuoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->recurring_quote);
    }

}