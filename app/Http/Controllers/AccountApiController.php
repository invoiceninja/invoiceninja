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
use League\Fractal\Manager;
use App\Ninja\Serializers\ArraySerializer;
use App\Ninja\Transformers\AccountTransformer;

class AccountApiController extends Controller
{
    protected $accountRepo;

    public function __construct(AccountRepository $accountRepo)
    {
        $this->accountRepo = $accountRepo;
    }

    public function login(Request $request)
    {
        if ( ! env(API_SECRET) || $request->api_secret !== env(API_SECRET)) {
            return 'Invalid secret';
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->processLogin($request);
            //return $this->accountRepo->createToken($request->token_name);
        } else {
            return 'Invalid credentials';
        }
    }

    public function index()
    {
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        $account = Auth::user()->account->load('users');
        $resource = new Item($account, new AccountTransformer, 'account');

        $response = $manager->createData($resource)->toArray();
        $response = json_encode($response, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }

    private function processLogin(Request $request)
    {
        \Log::info('authed user = '.Auth::user()->email);

        //Create a new token only if one does not already exist
        if(!AccountToken::where('user_id', '=', Auth::user()->id)->firstOrFail())
        {
            $account_token = $this->accountRepo->createToken($request->token_name);
        }

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        $account = Auth::user()->account->load('users','tokens');
        $resource = new Item($account, new AccountTransformer, 'account');

        $response = $manager->createData($resource)->toArray();
        $response = json_encode($response, JSON_PRETTY_PRINT);
        $headers = Utils::getApiHeaders();

        return Response::make($response, 200, $headers);
    }


}
