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

class CheckDatabaseRequest extends Request
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
        if (config('ninja.preconfigured_install')) {
            return [];
        }

        return [
            'db_host'     => ['required'],
            'db_port'     => ['required'],
            'db_database' => ['required'],
            'db_username' => ['required'],
        ];
    }
}
