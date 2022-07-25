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

namespace App\Http\Requests\TaskStatus;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $rules = [];

        // 26/10/2021 we disable this as it prevent updating existing task status meta data where the same name already exists
        // if ($this->input('name')) {
        //     $rules['name'] = Rule::unique('task_statuses')->where('company_id', auth()->user()->company()->id)->ignore($this->task_status->id);
        // }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }

        $this->replace($input);
    }
}
