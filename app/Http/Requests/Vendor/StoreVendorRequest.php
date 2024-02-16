<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Request;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreVendorRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Vendor::class);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        $rules['contacts'] = 'bail|array';
        $rules['contacts.*.email'] = 'bail|nullable|distinct|sometimes|email';
        $rules['contacts.*.password'] = [
            'bail',
            'nullable',
            'sometimes',
            'string',
            'min:7',             // must be at least 10 characters in length
            'regex:/[a-z]/',      // must contain at least one lowercase letter
            'regex:/[A-Z]/',      // must contain at least one uppercase letter
            'regex:/[0-9]/',      // must contain at least one digit
            //'regex:/[@$!%*#?&.]/', // must contain a special character
        ];


        if (isset($this->number)) {
            $rules['number'] = Rule::unique('vendors')->where('company_id', $user->company()->id);
        }

        $rules['currency_id'] = 'bail|required|exists:currencies,id';

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
        }

        $rules['language_id'] = 'bail|nullable|sometimes|exists:languages,id';
        $rules['classification'] = 'bail|sometimes|nullable|in:individual,business,company,partnership,trust,charity,government,other';

        return $rules;
    }

    public function prepareForValidation()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $input = $this->all();

        if (!array_key_exists('currency_id', $input) || empty($input['currency_id'])) {
            $input['currency_id'] = $user->company()->settings->currency_id;
        }

        if (isset($input['name'])) {
            $input['name'] = strip_tags($input['name']);
        }

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'contacts.*.email.required' => ctrans('validation.email', ['attribute' => 'email']),
        ];
    }
}
