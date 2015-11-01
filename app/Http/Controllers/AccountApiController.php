<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use Response;
use Input;
use App\Models\Client;
use App\Models\Account;
use App\Ninja\Repositories\AccountRepository;

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
}
