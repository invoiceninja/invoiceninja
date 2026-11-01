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

namespace App\Http\Requests\Support\Messages;

use App\Http\Requests\Request;
use Illuminate\Validation\Validator;

class SendMessageRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['required', 'image'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $attachment_size = collect($this->file('attachments', []))->sum(fn ($file) => $file->getSize());

            if ($attachment_size > 10 * 1024 * 1024) {
                $validator->errors()->add('attachments', 'The total attachment size must not exceed 10 MB.');
            }
        });
    }
}
