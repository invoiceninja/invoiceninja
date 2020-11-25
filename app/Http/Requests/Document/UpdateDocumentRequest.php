<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Document;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;

class UpdateDocumentRequest extends Request
{
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->document);
    }

    public function rules()
    {
        return [];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }
}
