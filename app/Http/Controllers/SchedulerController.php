<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SchedulerController extends Controller
{
    public function index()
    {
        if (auth()->user()->company()->account->latest_version == '0.0.0') {
            return response()->json(['message' => 'Scheduler has never run'], 400);
        } else {
            return response()->json(['message' => 'Scheduler has run'], 200);
        }
    }
}
