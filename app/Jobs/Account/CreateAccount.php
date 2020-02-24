<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\User\CreateUser;
use App\Models\Account;
use App\Models\User;
use App\Notifications\NewAccountCreated;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CreateAccount
{
    use Dispatchable;

    protected $request;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(array $request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?Account
    {
        /*
         * Create account
         */
        $account = Account::create($this->request);
        /*
         * Create company
         */
        $company = CreateCompany::dispatchNow($this->request, $account);
        $company->load('account');
        /*
         * Set default company
         */
        $account->default_company_id = $company->id;
        $account->save();
        /*
         * Create user
         */
        $user = CreateUser::dispatchNow($this->request, $account, $company, true); //make user company_owner
        /*
         * Required dependencies
         */
        if ($user) {
            auth()->login($user, false);
        }

        $user->setCompany($company);

        /*
         * Create token
         */
        $user_agent = isset($this->request['token_name']) ? $this->request['token_name'] : request()->server('HTTP_USER_AGENT');

        $company_token = CreateCompanyToken::dispatchNow($company, $user, $user_agent);

        /*
         * Fire related events
         */
        if ($user) {
            event(new AccountCreated($user));
        }
        
        $user->fresh();

        $company->notification(new NewAccountCreated($user, $company))->run();
        
        // $user->route('slack', $company->settings->system_notifications_slack)
        //      ->route('mail', $company->settings->system_notifications_email)
        //      ->notify(new NewAccountCreated($user, $company));

        // Notification::route('slack', config('ninja.notification.slack'))
        //             ->notify(new NewAccountCreated($user, $company));

        return $account;
    }
}
