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

namespace App\Http\Requests\Webhook;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;

class UpdateWebhookRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->webhook);
    }

    public function rules()
    {
        return [
            'target_url' => 'bail|required|url',
            'event_id' => 'bail|required',
            'rest_method' => 'required|in:post,put',
            // 'headers' => 'bail|sometimes|json',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (!isset($input['rest_method'])) {
            $input['rest_method'] = 'post';
        }

        // if(isset($input['headers']) && count($input['headers']) == 0)
        //     $input['headers'] = null;

        $this->replace($input);
    }
}
