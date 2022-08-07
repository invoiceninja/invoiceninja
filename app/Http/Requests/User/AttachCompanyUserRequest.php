<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\User;

use App\DataMapper\DefaultSettings;
use App\Http\Requests\Request;
use App\Models\User;
use App\Utils\Traits\MakesHash;

class AttachCompanyUserRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    public function prepareForValidation()
    {
        $is_admin = request()->has('is_admin') ? request()->input('is_admin') : false;
        $permissions = request()->has('permissions') ? request()->input('permissions') : '';
        $settings = request()->has('settings') ? request()->input('settings') : json_encode(DefaultSettings::userSettings());
        $is_locked = request()->has('is_locked') ? request()->input('is_locked') : false;

        $this->replace([
            'is_admin' => $is_admin,
            'permissions' => $permissions,
            'settings' => $settings,
            'is_locked' => $is_locked,
            'is_owner' => false,
        ]);
    }
}
