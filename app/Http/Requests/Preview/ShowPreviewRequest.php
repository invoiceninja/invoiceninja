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

namespace App\Http\Requests\Preview;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Design\TwigLint;
use App\Utils\Traits\MakesHash;

class ShowPreviewRequest extends Request
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
        $rules = [
            'design.design.body' => ['sometimes', 'required_if:design.design.is_template,true',  new TwigLint()],
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }
}
