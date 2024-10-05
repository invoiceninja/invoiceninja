<?php

namespace App\Http\Requests\Expense;

use App\Http\Requests\Request;
use App\Models\User;

class EDocumentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->isAdmin();
    }

    public function rules()
    {
        $rules = [];

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = 'required|file|max:1000000';
        } elseif ($this->file('documents')) {
            $rules['documents'] = 'required|file|max:1000000';
        }
        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);

    }

}
