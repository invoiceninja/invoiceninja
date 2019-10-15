<?php

namespace App\Http\Controllers\Support\Messages;

use App\Http\Controllers\Controller;
use App\Mail\SupportMessageSent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SendingController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'message' => ['required'],
        ]);

        Mail::to(config('ninja.contact.ninja_official_contact'))
            ->send(new SupportMessageSent($request->message));

        return response()->json([
            'success' => true
        ]);
    }

}
