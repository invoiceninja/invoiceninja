<?php

namespace App\Services;

use App\Models\Invitation;
use App\Ninja\Mailers\UserMailer;
use Carbon;

/**
 * Class EmailService.
 */
class EmailService
{
    /**
     * @var UserMailer
     */
    protected $userMailer;

    /**
     * EmailService constructor.
     *
     * @param UserMailer $userMailer
     */
    public function __construct(UserMailer $userMailer)
    {
        $this->userMailer = $userMailer;
    }

    /**
     * @param $messageId
     *
     * @return bool
     */
    public function markOpened($messageId)
    {
        /** @var \App\Models\Invitation $invitation */
        $invitation = Invitation::whereMessageId($messageId)->first();

        if (! $invitation) {
            return false;
        }

        $invitation->opened_date = Carbon::now()->toDateTimeString();
        $invitation->save();

        return true;
    }

    /**
     * @param $messageId
     * @param $error
     *
     * @return bool
     */
    public function markBounced($messageId, $error)
    {
        /** @var \App\Models\Invitation $invitation */
        $invitation = Invitation::with('user', 'invoice', 'contact')
                        ->whereMessageId($messageId)
                        ->first();

        if (! $invitation) {
            return false;
        }

        $invitation->email_error = $error;
        $invitation->save();

        $this->userMailer->sendEmailBounced($invitation);

        return true;
    }
}
