<?php

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Models\RecurringQuote;

class CreateRecurringQuoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', RecurringQuote::class);
    }

}