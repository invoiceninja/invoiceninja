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

namespace App\Http\Requests\Tag;

use App\Http\Requests\Request;

class ShowTagRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->can('view', $this->tag);
    }
}
