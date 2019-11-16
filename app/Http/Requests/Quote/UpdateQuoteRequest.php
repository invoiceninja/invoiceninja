<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends Request
{
    use MakesHash;
    use CleanLineItems;
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('edit', $this->quote);

    }


    public function rules()
    {
        $this->sanitize();

        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }

    public function sanitize()
    {
        $input = $this->all();

        // if(isset($input['client_id']))
        //     $input['client_id'] = $this->decodePrimaryKey($input['client_id']);

        if(isset($input['line_items']))
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        $this->replace($input);

        return $this->all();
    }

}