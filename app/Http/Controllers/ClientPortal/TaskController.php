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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Tasks\ShowTasksRequest;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Show the tasks in the client portal.
     *
     * @param ShowTasksRequest $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(ShowTasksRequest $request)
    {
        return render('tasks.index');
    }
}
