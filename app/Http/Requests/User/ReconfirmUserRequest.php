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

namespace App\Http\Requests\User;

use App\Http\Requests\Request;
use App\Services\Cloudflare\Turnstile;
use App\Utils\Ninja;

class ReconfirmUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(Turnstile $turnstile): bool
    {
        if (auth()->user()->id == $this->user->id || auth()->user()->isAdmin()) {
            if (Ninja::isHosted()) {
                return $turnstile->authorize();
            }

            return true;
        }

        return false;
    }
}
