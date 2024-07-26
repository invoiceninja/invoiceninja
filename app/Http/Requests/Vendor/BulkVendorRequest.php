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

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Request;
use App\Models\Vendor;
use App\Utils\Traits\BulkOptions;

class BulkVendorRequest extends Request
{
    use BulkOptions;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (! $this->has('action')) {
            return false;
        }

        if (! in_array($this->action, $this->getBulkOptions(), true)) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', Vendor::class);

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = $this->getGlobalRules();

        /* We don't require IDs on bulk storing. */
        if ($this->action !== self::$STORE_METHOD) {
            $rules['ids'] = ['required'];
        }

        return $rules;
    }
}
