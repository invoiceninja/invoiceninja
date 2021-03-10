<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Account;

use App\DataMapper\Analytics\AccountCreated as AnalyticsAccountCreated;
use App\Events\Account\AccountCreated;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\User\CreateUser;
use App\Jobs\Util\VersionCheck;
use App\Mail\Admin\AccountCreatedObject;
use App\Mail\Admin\VerifyUserObject;
use App\Models\Account;
use App\Notifications\Ninja\NewAccountCreated;
use App\Utils\Ninja;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Turbo124\Beacon\Facades\LightLogs;

class CreateAccount
{
    use Dispatchable;

    protected $request;

    public function __construct(array $sp660339)
    {
        $this->request = $sp660339;
    }

    public function handle()
    {
        if (config('ninja.environment') == 'selfhost' && Account::all()->count() == 0) {
            return $this->create();
        } elseif (config('ninja.environment') == 'selfhost' && Account::all()->count() > 1) {
            return response()->json(['message' => Ninja::selfHostedMessage()], 400);
        } elseif (! Ninja::boot()) {
            return response()->json(['message' => Ninja::parse()], 401);
        }

        return $this->create();
    }

    private function create()
    {
        $sp794f3f = Account::create($this->request);
        $sp794f3f->referral_code = Str::random(32);

        if (! $sp794f3f->key) {
            $sp794f3f->key = Str::random(32);
        }

        $sp794f3f->save();

        $sp035a66 = CreateCompany::dispatchNow($this->request, $sp794f3f);
        $sp035a66->load('account');
        $sp794f3f->default_company_id = $sp035a66->id;
        $sp794f3f->save();

        $spaa9f78 = CreateUser::dispatchNow($this->request, $sp794f3f, $sp035a66, true);

        CreateCompanyPaymentTerms::dispatchNow($sp035a66, $spaa9f78);
        CreateCompanyTaskStatuses::dispatchNow($sp035a66, $spaa9f78);

        if ($spaa9f78) {
            auth()->login($spaa9f78, false);
        }

        $spaa9f78->setCompany($sp035a66);
        $spafe62e = isset($this->request['token_name']) ? $this->request['token_name'] : request()->server('HTTP_USER_AGENT');
        $sp2d97e8 = CreateCompanyToken::dispatchNow($sp035a66, $spaa9f78, $spafe62e);

        if ($spaa9f78) {
            event(new AccountCreated($spaa9f78, $sp035a66, Ninja::eventVars()));
        }

        $spaa9f78->fresh();

        //todo implement SLACK notifications
        //$sp035a66->notification(new NewAccountCreated($spaa9f78, $sp035a66))->ninja();

        VersionCheck::dispatchNow();

        LightLogs::create(new AnalyticsAccountCreated())
                 ->increment()
                 ->batch();

        return $sp794f3f;
    }
}
