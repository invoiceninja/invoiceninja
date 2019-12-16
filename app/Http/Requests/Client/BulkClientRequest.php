<?php

namespace App\Http\Requests\Client;

use App\Utils\Traits\BulkOptions;
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Client;

class BulkClientRequest extends FormRequest
{
    use BulkOptions;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!$this->has('action')) {
            return false;
        }

        if (!in_array($this->action, $this->getBulkOptions(), true)) {
            return false;
        }

        return auth()->user()->can($this->action, Client::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getGlobalRules();

        /** We don't require IDs on bulk storing. */
        if ($this->action !== 'create') {
            $rules['ids'] = ['required'];
        }

        return $rules;
    }
}
