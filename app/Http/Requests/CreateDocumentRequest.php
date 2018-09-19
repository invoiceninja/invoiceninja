<?php

namespace App\Http\Requests;

use App\Models\Expense;
use App\Models\Invoice;

class CreateDocumentRequest extends DocumentRequest
{
    protected $autoload = [
        ENTITY_INVOICE,
        ENTITY_EXPENSE,
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if($this->user()->hasFeature(FEATURE_DOCUMENTS))
            return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //'file' => 'mimes:jpg'
        ];
    }
}
