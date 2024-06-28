<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Email;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EntityEmailHistoryRequest extends Request
{
    use MakesHash;

    private string $entity_plural = '';
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        //handle authorization in controller
        return true;
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
            'entity' => 'bail|required|string|in:invoice,quote,credit,recurring_invoice,purchase_order',
            'entity_id' => ['bail','required',Rule::exists($this->entity_plural, 'id')->where('company_id', $user->company()->id)],
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->entity_plural = Str::plural($input['entity']) ?? '';
        $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);

        $this->replace($input);
    }

}
