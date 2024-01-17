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
use App\Models\Account;

class StoreWebhookRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin() && auth()->user()->account->hasFeature(Account::FEATURE_API);
    }

    public function rules()
    {
        return [
            'target_url' => 'bail|required|url',
            'event_id' => 'bail|required',
            // 'headers' => 'bail|sometimes|json',
            'rest_method' => 'required|in:post,put'
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (!isset($input['rest_method'])) {
            $input['rest_method'] = 'post';
        }
        // if(isset($input['headers']) && count($input['headers']) == 0)
        // $input['headers'] = null;

        $this->replace($input);
    }
}
