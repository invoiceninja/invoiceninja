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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('view', $this->client());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d',
            'client_id'  => 'bail|required|exists:clients,id,company_id,'.$user->company()->id,
            'show_payments_table' => 'boolean',
            'show_aging_table' => 'boolean',
            'show_credits_table' => 'boolean',
            'status' => 'string',
            'template' => 'sometimes|string|nullable',
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
            'show_credits_table' => $this->has('show_credits_table') ? \boolval($this->show_credits_table) : false,
        ]);
    }

    public function client(): ?Client
    {
        return Client::query()->without('company')->where('id', $this->client_id)->withTrashed()->first();
    }
}
