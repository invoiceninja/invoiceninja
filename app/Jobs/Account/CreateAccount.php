<?php
namespace App\Jobs\Account;

use App\Events\Account\AccountCreated;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\User\CreateUser;
use App\Models\Account;
use App\Models\User;
use App\Notifications\Ninja\NewAccountCreated;
use App\Utils\Ninja;
use App\Utils\Traits\UserSessionAttributes;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

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
            return response()->json(array('message' => Ninja::selfHostedMessage()), 400);
        } elseif (!Ninja::boot()) {
            return response()->json(array('message' => Ninja::parse()), 401);
        }

        return $this->create();
    }

    private function create()
    {
        $sp794f3f = Account::create($this->request);
        $sp794f3f->referral_code = Str::random(32);

        if (!$sp794f3f->key) {
            $sp794f3f->key = Str::random(32);
        }

        $sp794f3f->save();

        $sp035a66 = CreateCompany::dispatchNow($this->request, $sp794f3f);
        $sp035a66->load('account');
        $sp794f3f->default_company_id = $sp035a66->id;
        $sp794f3f->save();

        $spaa9f78 = CreateUser::dispatchNow($this->request, $sp794f3f, $sp035a66, true);

        CreateCompanyPaymentTerms::dispatchNow($sp035a66, $spaa9f78);
        
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

        $sp035a66->notification(new NewAccountCreated($spaa9f78, $sp035a66))->ninja();
        
        return $sp794f3f;
    }
}
