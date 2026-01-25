<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Activity;

use Illuminate\Support\Str;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class ShowActivityRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'entity' => 'bail|required|in:invoice,quote,credit,purchase_order,payment,client,vendor,expense,task,project,subscription,recurring_invoice,',
            'entity_id' => 'bail|required|exists:'.$this->entity.'s,id,company_id,'.auth()->user()->company()->id,
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['entity_id'])) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        $this->replace($input);

    }

    public function getEntity()
    {
        if (!$this->entity) {
            return false;
        }

        $class = "\\App\\Models\\".ucfirst(Str::camel(rtrim($this->entity, 's')));
        return $class::withTrashed()->company()->where('id', is_string($this->entity_id) ? $this->decodePrimaryKey($this->entity_id) : $this->entity_id)->first();

    }
    
}
