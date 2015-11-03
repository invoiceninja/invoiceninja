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
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use League\Fractal\Manager;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;
use App\Ninja\Transformers\UserAccountTransformer;
use App\Http\Controllers\BaseAPIController;

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
        $this->accountRepo->createTokens(Auth::user(), $request->token_name);

        return $this->index();
    }

    public function index()
    {
        $users = $this->accountRepo->findUsers(Auth::user(), 'account.account_tokens');

        $resource = new Collection($users, new UserAccountTransformer);

        return $this->returnData($resource);
    }

    public function show($accountKey)
    {
        $account = $this->accountRepo->findByKey($accountKey);

        $resource = new Item($account, new AccountTransformer);

        return $this->returnData($resource);
    }

}
