<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() && $user->account->hasFeature(Account::FEATURE_API);
    }

    public function rules()
    {
        return [
            'target_url' => 'bail|required|url',
            'event_id' => 'bail|required',
            'rest_method' => 'required|in:post,put',
        ];
    }


    /**
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }
        
        $validator->after(function ($validator) {
            $this->validateWebhookUrl($validator, 'target_url');
        });
    }

    /**
     * Validate that a URL doesn't point to internal/private IP addresses.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @param string $field
     * @return void
     */
    private function validateWebhookUrl(\Illuminate\Validation\Validator $validator, string $field): void
    {
        $url = $this->input($field);

        if (empty($url)) {
            return;
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $validator->errors()->add($field, ctrans('texts.invalid_url'));
            return;
        }

        $parsed = parse_url($url);

        // Only allow http/https protocols
        $scheme = $parsed['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https'])) {
            $validator->errors()->add($field, ctrans('texts.invalid_url'));
            return;
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            $validator->errors()->add($field, ctrans('texts.invalid_url'));
            return;
        }

        // Resolve hostname to IP and check for private/reserved ranges
        $ip = gethostbyname($host);

        // gethostbyname returns the hostname if resolution fails
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            // DNS resolution failed - allow it (external DNS might resolve differently)
            $validator->errors()->add($field, 'Unable to resolve hostname.');
            return;
        }

        // Block private and reserved IP ranges (SSRF protection)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $validator->errors()->add($field, ctrans('texts.invalid_url'));
            return;
        }
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['rest_method'] = $input['rest_method'] ?? 'post';

        $this->replace($input);
    }
}
