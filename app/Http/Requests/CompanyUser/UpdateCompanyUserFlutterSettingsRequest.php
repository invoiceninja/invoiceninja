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

namespace App\Http\Requests\CompanyUser;

use App\Http\Requests\Request;
use App\Http\ValidationRules\User\ValidFlutterSettingsPatch;
use App\Utils\Traits\MakesHash;

class UpdateCompanyUserFlutterSettingsRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->id == $this->user->id;
    }

    public function rules()
    {
        return [
            'flutter_settings' => ['present', 'array', new ValidFlutterSettingsPatch()],
        ];
    }
}
