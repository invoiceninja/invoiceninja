<?php

namespace App\Http\Requests\ClientPortal;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:client_contacts'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    public function company()
    {
        if ($this->subdomain) {
            return Company::where('subdomain', $this->subdomain)->firstOrFail();
        }

        if ($this->company_key) {
            return Company::where('company_key', $this->company_key)->firstOrFail();
        }

        abort(404);
    }
}
