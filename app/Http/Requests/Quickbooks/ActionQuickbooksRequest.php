<?php

namespace App\Http\Requests\Quickbooks;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use App\Http\Requests\Request;

class ActionQuickbooksRequest extends Request
{
    use MakesHash;
    
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = auth()->user();
        
        return [
            'entity' => ['bail', 'required', 'string', Rule::in(['invoice'])],
            'id' => ['bail', 'required', Rule::exists('invoices', 'id')->where('company_id', $user->company()->id)],
            'action' => ['bail', 'required', 'string', Rule::in(['check', 'force_link', 'force_pull', 'force_push','check_record'])],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $user = auth()->user();

            $invoice = $this->getInvoice();

            if (!$user->company()->quickbooks || !$user->company()->quickbooks->isConfigured()) {
                $validator->errors()->add('id', 'QuickBooks is not configured for this company.');
            }

            if ($invoice && $user->cannot('edit', $invoice)) {
                $validator->errors()->add('id', 'You are not authorized to edit this invoice.');
            }

        });
    }

    public function getInvoice(): ?Invoice
    {
        $user = auth()->user();
     
        return Invoice::withTrashed()->where('company_id', $user->company()->id)->find($this->input('id'));
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['id'])) {
            $input['id'] = $this->decodePrimaryKey($input['id']);
        }
        
        $this->replace($input);
    }
}
