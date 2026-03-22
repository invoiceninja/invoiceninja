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

        $rules['documents.*'] = 'required|file|max:1000000';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ($this->file('documents') instanceof \Illuminate\Http\UploadedFile) {
            $this->files->set('documents', [$this->file('documents')]);
        }

        $this->replace($input);

    }

}
