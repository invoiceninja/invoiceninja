<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Setup;

use App\Http\Requests\Request;
use Illuminate\Support\Facades\Schema;

class CheckMailRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        if (!\App\Utils\Ninja::isSelfHost()) {
            return false;
        }

        try {
            return !Schema::hasTable('accounts') || \App\Models\Account::count() == 0;
        } catch (\Throwable $e) {
            // If database connection fails, allow the request (we're checking the DB)
            return true;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'mail_driver' => 'required',
            'encryption' => 'required_unless:mail_driver,log',
            'mail_host' => 'required_unless:mail_driver,log',
            'mail_username' => 'required_unless:mail_driver,log',
            'mail_name' => 'required_unless:mail_driver,log',
            'mail_address' => 'required_unless:mail_driver,log',
            'mail_password' => 'required_unless:mail_driver,log',
        ];
    }
}
