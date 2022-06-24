<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\Invoice;
use App\Utils\Traits\Invoice\ActionsInvoice;
use App\Utils\Traits\MakesHash;

class ActionInvoiceRequest extends Request
{
    use MakesHash;
    use ActionsInvoice;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    private $error_msg;

    // private $invoice;

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

    public function rules()
    {
        return [
            'action' => 'required',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ($this->action) {
            $input['action'] = $this->action;
        } elseif (! array_key_exists('action', $input)) {
            $this->error_msg = 'Action is a required field';
        } elseif (! $this->invoiceDeletable($this->invoice)) {
            unset($input['action']);
            $this->error_msg = 'This invoice cannot be deleted';
        } elseif (! $this->invoiceCancellable($this->invoice)) {
            unset($input['action']);
            $this->error_msg = 'This invoice cannot be cancelled';
        } elseif (! $this->invoiceReversable($this->invoice)) {
            unset($input['action']);
            $this->error_msg = 'This invoice cannot be reversed';
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'action' => $this->error_msg,
        ];
    }
}
