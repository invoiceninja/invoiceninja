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

namespace App\Http\Requests\Product;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class BulkProductRequest extends Request
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
            'ids' => ['required','bail','array',Rule::exists('products', 'id')->where('company_id', auth()->user()->company()->id)],
            'action' => 'in:archive,restore,delete,set_tax_id',
            'tax_id' => 'nullable|required_if:action,set_tax_id,in:1,2,3,4,5,6,7,8,9',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        $this->replace($input);
    }
}
