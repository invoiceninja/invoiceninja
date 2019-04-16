<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\Invoice;

class DestroyInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

}