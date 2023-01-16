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

namespace App\Http\Requests\TaskScheduler;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class StoreSchedulerRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {

        $rules = [
            'name' => ['bail', 'required', Rule::unique('schedulers')->where('company_id', auth()->user()->company()->id)],
            'is_paused' => 'bail|sometimes|boolean',
            'frequency_id' => 'bail|required|integer|digits_between:1,12',
            'next_run' => 'bail|required|date:Y-m-d',
            'template' => 'bail|required|string',
            'parameters' => 'bail|array',
        ];

        return $rules;

    }
}
