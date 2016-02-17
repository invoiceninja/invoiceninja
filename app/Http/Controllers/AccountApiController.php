<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use Validator;
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

use App\Http\Requests\UpdateAccountRequest;

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
            return $this->errorResponse(['message'=>'Invalid secret'],401);
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->processLogin($request);
        } else {
            sleep(ERROR_DELAY);
            return $this->errorResponse(['message'=>'Invalid credentials'],401);
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

        $map = [
            'users' => [],
            'clients' => ['contacts'],
            'invoices' => ['invoice_items', 'user', 'client', 'payments'],
            'products' => [],
            'tax_rates' => [],
            'expenses' => ['client', 'invoice', 'vendor']
        ];

        foreach ($map as $key => $values) {
            $account->load([$key => function($query) use ($values, $updatedAt) {
                $query->withTrashed()->with($values);
                if ($updatedAt) {
                    $query->where('updated_at', '>=', $updatedAt);
                }
            }]);
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

    public function getUserAccounts(Request $request)
    {
        return $this->processLogin($request);
    }

    public function update(UpdateAccountRequest $request)
    {
        $account = Auth::user()->account;
        $this->accountRepo->save($request->input(), $account);

        $transformer = new AccountTransformer(null, $request->serializer);
        $account = $this->createItem($account, $transformer, 'account');

        return $this->response($account);
    }
}
