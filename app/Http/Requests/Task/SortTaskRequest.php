<?php
/**
 * Quote Ninja (https://paymentninja.com).
 *
 * @link https://github.com/paymentninja/paymentninja source repository
 *
 * @copyright Copyright (c) 2021. Quote Ninja LLC (https://paymentninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
//        return auth()->user()->can('edit', $this->task);
    }

    public function rules()
    {

        return [];

    }

}
