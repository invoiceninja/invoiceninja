<?php namespace App\Services;

use Carbon;
use App\Models\Invitation;
use App\Ninja\Mailers\UserMailer;

class EmailService {

    protected $userMailer;

    public function __construct(UserMailer $userMailer)
    {
        $this->userMailer = $userMailer;
    }

    public function markOpened($messageId)
    {
        $invitation = Invitation::whereMessageId($messageId)
                        ->first();

        if (!$invitation) {
            return false;
        }

        $invitation->opened_date = Carbon::now()->toDateTimeString();
        $invitation->save();

        return true;
    }

    public function markBounced($messageId, $error)
    {
        $invitation = Invitation::with('user', 'invoice', 'contact')
                        ->whereMessageId($messageId)
                        ->first();

        if (!$invitation) {
            return false;
        }

        $invitation->email_error = $error;
        $invitation->save();

        $this->userMailer->sendEmailBounced($invitation);

        return true;
    }
}