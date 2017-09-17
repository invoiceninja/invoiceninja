<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Task;

class TimeTrackerController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $account = $user->account;

        $data = [
            'title' => trans('texts.time_tracker'),
            'tasks' => Task::scope()->get(),
            'account' => $account,
        ];

        return view('time_tracker', $data);
    }
}
