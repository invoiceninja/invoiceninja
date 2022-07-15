<?php
/**
 * Invoice Ninja (https://paymentninja.com).
 *
 * @link https://github.com/paymentninja/paymentninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;

class SortTaskRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {

        return true;

    }

    public function rules()
    {

        return [];

    }

}
