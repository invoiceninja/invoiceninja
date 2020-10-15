<?php

namespace App\Http\Requests\ExpenseCategory;

use App\Models\ExpenseCategory;
use App\Utils\Traits\BulkOptions;
use Illuminate\Foundation\Http\FormRequest;

class BulkExpenseCategoryRequest extends FormRequest
{
    use BulkOptions;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }
}
