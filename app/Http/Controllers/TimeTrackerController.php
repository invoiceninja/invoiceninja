<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Task;

class TimeTrackerController extends Controller
{
    public function index()
    {
        $data = [
            'tasks' => Task::scope()->get(),
        ];

        return view('time_tracker', $data);
    }
}
