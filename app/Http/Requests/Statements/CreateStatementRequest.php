<?php

namespace App\Http\Requests\Statements;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Utils\Traits\MakesHash;

class CreateStatementRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // return auth()->user()->isAdmin();

        return auth()->user()->can('view', $this->client());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d',
            'client_id'  => 'bail|required|exists:clients,id,company_id,'.auth()->user()->company()->id,
            'show_payments_table' => 'boolean',
            'show_aging_table' => 'boolean',
            'status' => 'string',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);

        $this->merge([
            'show_payments_table' => $this->has('show_payments_table') ? \boolval($this->show_payments_table) : false,
            'show_aging_table' => $this->has('show_aging_table') ? \boolval($this->show_aging_table) : false,
        ]);
    }

    public function client(): ?Client
    {
        return Client::without('company')->where('id', $this->client_id)->withTrashed()->first();
    }
}
