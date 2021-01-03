<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */


namespace App\Http\Requests\Setup;

use App\Http\Requests\Request;

class CheckDatabaseRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; /* Return something that will check if setup has been completed, like Ninja::hasCompletedSetup() */
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'db_host' => ['required'],
            'db_database' => ['required'],
            'db_username' => ['required'],
        ];
    }
}
