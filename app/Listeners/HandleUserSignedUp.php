<?php namespace App\Listeners;

use Utils;
use Auth;
use App\Events\UserSignedUp;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Mailers\UserMailer;

/**
 * Class HandleUserSignedUp
 */
class HandleUserSignedUp
{
    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * @var UserMailer
     */
    protected $userMailer;

    /**
     * Create the event handler.
     * 
     * @param AccountRepository $accountRepo
     * @param UserMailer $userMailer
     */
    public function __construct(AccountRepository $accountRepo, UserMailer $userMailer)
    {
        $this->accountRepo = $accountRepo;
        $this->userMailer = $userMailer;
    }

    /**
     * Handle the event.
     *
     * @param  UserSignedUp $event
     *
     * @return void
     */
    public function handle(UserSignedUp $event)
    {
        $user = Auth::user();

        if (Utils::isNinjaProd()) {
            $this->userMailer->sendConfirmation($user);
        } elseif (Utils::isNinjaDev()) {
            // do nothing
        } else {
            $this->accountRepo->registerNinjaUser($user);
        }

        session([SESSION_COUNTER => -1]);
    }
}
