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
namespace App\Http\Requests\EInvoice;

use Illuminate\Foundation\Http\FormRequest;

class ShowQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (config('ninja.app_env') == 'local') {
            return true;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return \App\Utils\Ninja::isSelfHost() && $user->account->isPaid();
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}
