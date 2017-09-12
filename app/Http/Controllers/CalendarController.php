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
        if (! auth()->user()->hasPermission('view_all')) {
            return redirect('/');
        }

        $data = [
            //'showBreadcrumbs' => false,
        ];

        return view('calendar', $data);
    }

    public function loadEvents()
    {
        $events = dispatch(new GenerateCalendarEvents());
        //dd($events);
        \Log::info(print_r(request()->input(), true));
        //\Log::info(print_r($events, true));
        //echo '[{"title": "Test Event", "start": "2017-09-14T16:00:00", "color": "green"}]';
        //exit;

        return response()->json($events);
    }

}
