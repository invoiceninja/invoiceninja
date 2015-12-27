<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use Cache;
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
        $transformer = new UserAccountTransformer($user->account, $request->serializer, $request->token_name);
        $data = $this->createCollection($users, $transformer, 'user_account');

        return $this->response($data);
    }

    public function show(Request $request)
    {
        $account = Auth::user()->account;
        $updatedAt = $request->updated_at ? date('Y-m-d H:i:s', $request->updated_at) : false;

        if ($updatedAt) {
            $account->load(['users' => function($query) use ($updatedAt) {
                $query->where('updated_at', '>=', $updatedAt);
            }])
            ->load(['clients' => function($query) use ($updatedAt) {
                $query->where('updated_at', '>=', $updatedAt)->with('contacts');
            }])
            ->load(['invoices' => function($query) use ($updatedAt) {
                $query->where('updated_at', '>=', $updatedAt)->with('invoice_items', 'user', 'client');
            }])
            ->load(['products' => function($query) use ($updatedAt) {
                $query->where('updated_at', '>=', $updatedAt);
            }])
            ->load(['tax_rates' => function($query) use ($updatedAt) {
                $query->where('updated_at', '>=', $updatedAt);
            }]);
        } else {
            $account->load(
                'users',
                'clients.contacts',
                'invoices.invoice_items',
                'invoices.user',
                'invoices.client',
                'products',
                'tax_rates'
            );
        }

        $transformer = new AccountTransformer(null, $request->serializer);
        $account = $this->createItem($account, $transformer, 'account');

        return $this->response($account);
    }

    public function getStaticData()
    {
        $data = [];

        $cachedTables = unserialize(CACHED_TABLES);
        foreach ($cachedTables as $name => $class) {
            $data[$name] = Cache::get($name);
        }

        return $this->response($data);
    }
}
