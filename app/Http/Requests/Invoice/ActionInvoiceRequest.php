<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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

    private $invoice;

    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }

    public function rules()
    {
    	return [
    		'action' => 'required'
    	];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

    	$this->invoice = Invoice::find($this->decodePrimary($invoice_id));

		if(!array_key_exists('action', $input) {
        	$this->error_msg = 'Action is a required field';	
        }
        elseif(!$this->invoiceDeletable()){
        	unset($input['action']);	
        	$this->error_msg = 'This invoice cannot be deleted';
        }
        elseif(!$this->invoiceCancellable()) {
        	unset($input['action']);	
        	$this->error_msg = 'This invoice cannot be cancelled';
        }
        else if(!$this->invoiceReversable()) {
        	unset($input['action']);	
        	$this->error_msg = 'This invoice cannot be reversed';
        }

        $this->replace($input);
    }

    public function messages()
    {
    	return [
    		'action' => $this->error_msg;
    	]
    }


    private function invoiceDeletable()
    {

    	if($this->invoice->status_id <= 2 && $this->invoice->is_deleted == false && $this->invoice->deleted_at == NULL)
    		return true;

    	return false;
    }

    private function invoiceCancellable()
    {

		if($this->invoice->status_id == 3 && $this->invoice->is_deleted == false && $this->invoice->deleted_at == NULL)
			return true;

		return false;
    }

    private function invoiceReversable()
    {

		if(($this->invoice->status_id == 3 || $this->invoice->status_id == 4) && $this->invoice->is_deleted == false && $this->invoice->deleted_at == NULL)
			return true;

		return false;
    }

}

