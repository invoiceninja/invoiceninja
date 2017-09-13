<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCalendarEvents;

/**
 * Class ReportController.
 */
class CalendarController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showCalendar()
    {
        $data = [
            'account' => auth()->user()->account,
        ];

        return view('calendar', $data);
    }

    public function loadEvents()
    {
        if (auth()->user()->account->hasFeature(FEATURE_REPORTS)) {
            $events = dispatch(new GenerateCalendarEvents());
        } else {
            $events = [];
        }

        return response()->json($events);
    }

}
