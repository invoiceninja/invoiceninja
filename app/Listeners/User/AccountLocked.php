<?php

namespace App\Listeners\User;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Password;

class AccountLocked
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param object $event
     * @return void
     */
    public function handle($event)
    {
        $user = \App\Models\User::where('email', $event->request->email)->first();

        if ($user) {
            $user->companies()->updateExistingPivot($user, ['is_locked' => 1], false);

            /** After locking user account, we will send password reset e-mail. */
            $token = Password::getRepository()->create($user);
            $user->sendPasswordResetNotification($token, true);
        }
    }
}
