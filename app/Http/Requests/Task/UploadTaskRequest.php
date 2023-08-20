<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;

class UploadTaskRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->task);
    }

    public function rules()
    {
        $rules = [
            'documents' => 'bail|sometimes|file|mimes:csv,png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:2000000',
            'is_public' => 'sometimes|boolean',
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if(isset($input['is_public'])) 
            $input['is_public'] = $this->toBoolean($input['is_public']);

        $this->replace($input);
    }

}
