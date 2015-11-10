<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use App\Models\Client;
use App\Models\Account;
use App\Models\AccountToken;
use App\Ninja\Repositories\AccountRepository;
use Illuminate\Http\Request;
use League\Fractal;
use League\Fractal\Manager;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use App\Ninja\Transformers\UserAccountTransformer;
use App\Http\Controllers\BaseAPIController;
use Swagger\Annotations as SWG;

class AccountApiController extends BaseAPIController
{
    protected $accountRepo;

    public function __construct(AccountRepository $accountRepo)
    {
        parent::__construct();

        $this->accountRepo = $accountRepo;
    }

    public function login(Request $request)
    {
        if ( ! env(API_SECRET) || $request->api_secret !== env(API_SECRET)) {
            sleep(ERROR_DELAY);
            return 'Invalid secret';
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->processLogin($request);
        } else {
            sleep(ERROR_DELAY);
            return 'Invalid credentials';
        }
    }

    private function processLogin(Request $request)
    {
        // Create a new token only if one does not already exist
        $user = Auth::user();
        $this->accountRepo->createTokens($user, $request->token_name);
        
        $users = $this->accountRepo->findUsers($user, 'account.account_tokens');
        $data = $this->createCollection($users, new UserAccountTransformer($user->account, $request->token_name));

        $response = [
            'user_accounts' => $data,
            'default_url' => SITE_URL
        ];

        return $this->response($response);
    }

    public function show()
    {
        $account = Auth::user()->account;
        $account->load('clients.getInvoices.invoice_items', 'users');
        
        $account = $this->createItem($account, new AccountTransformer);
        $response = [
            'account' => $account
        ];

        return $this->response($response);
    }

}
