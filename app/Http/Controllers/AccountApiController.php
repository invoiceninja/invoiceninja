<?php

namespace App\Http\Controllers;

use App\Events\UserSignedUp;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Ninja\OAuth\OAuth;
use App\Ninja\Repositories\AccountRepository;
use App\Ninja\Transformers\AccountTransformer;
use App\Ninja\Transformers\UserAccountTransformer;
use App\Services\AuthService;
use Auth;
use Cache;
use Exception;
use Illuminate\Http\Request;
use Response;
use Socialite;
use Utils;

class AccountApiController extends BaseAPIController
{
    protected $accountRepo;

    public function __construct(AccountRepository $accountRepo)
    {
        parent::__construct();

        $this->accountRepo = $accountRepo;
    }

    public function ping(Request $request)
    {
        $headers = Utils::getApiHeaders();

        return Response::make(RESULT_SUCCESS, 200, $headers);
    }

    public function register(RegisterRequest $request)
    {
        if (! \App\Models\LookupUser::validateField('email', $request->email)) {
            return $this->errorResponse(['message' => trans('texts.email_taken')], 500);
        }

        $account = $this->accountRepo->create($request->first_name, $request->last_name, $request->email, $request->password);
        $user = $account->users()->first();

        Auth::login($user, true);
        event(new UserSignedUp());

        return $this->processLogin($request);
    }

    public function login(Request $request)
    {
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return $this->processLogin($request);
        } else {
            sleep(ERROR_DELAY);

            return $this->errorResponse(['message' => 'Invalid credentials'], 401);
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

        $transformer = new AccountTransformer(null, $request->serializer);
        $account->load(array_merge($transformer->getDefaultIncludes(), ['projects.client']));
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

    public function addDeviceToken(Request $request)
    {
        $account = Auth::user()->account;

        //scan if this user has a token already registered (tokens can change, so we need to use the users email as key)
        $devices = json_decode($account->devices, true);

        for ($x = 0; $x < count($devices); $x++) {
            if ($devices[$x]['email'] == Auth::user()->username) {
                $devices[$x]['token'] = $request->token; //update
                $devices[$x]['device'] = $request->device;
                    $account->devices = json_encode($devices);
                $account->save();
                $devices[$x]['account_key'] = $account->account_key;

                return $this->response($devices[$x]);
            }
        }

        //User does not have a device, create new record

        $newDevice = [
            'token' => $request->token,
            'email' => $request->email,
            'device' => $request->device,
            'account_key' => $account->account_key,
            'notify_sent' => true,
            'notify_viewed' => true,
            'notify_approved' => true,
            'notify_paid' => true,
        ];

        $devices[] = $newDevice;
        $account->devices = json_encode($devices);
        $account->save();

        return $this->response($newDevice);
    }

    public function updatePushNotifications(Request $request)
    {
        $account = Auth::user()->account;

        $devices = json_decode($account->devices, true);

        if (count($devices) < 1) {
            return $this->errorResponse(['message' => 'No registered devices.'], 400);
        }

        for ($x = 0; $x < count($devices); $x++) {
            if ($devices[$x]['email'] == Auth::user()->username) {
                $newDevice = [
                    'token' => $devices[$x]['token'],
                    'email' => $devices[$x]['email'],
                    'device' => $devices[$x]['device'],
                    'account_key' => $account->account_key,
                    'notify_sent' => $request->notify_sent,
                    'notify_viewed' => $request->notify_viewed,
                    'notify_approved' => $request->notify_approved,
                    'notify_paid' => $request->notify_paid,
                ];

                $devices[$x] = $newDevice;
                $account->devices = json_encode($devices);
                $account->save();

                return $this->response($newDevice);
            }
        }
    }

    public function oauthLogin(Request $request)
    {
        $user = false;
        $token = $request->input('token');
        $provider = $request->input('provider');

        $oAuth = new OAuth();
        $user = $oAuth->getProvider($provider)->getTokenResponse($token);

        if ($user) {
            Auth::login($user);
            return $this->processLogin($request);
        }
        else
            return $this->errorResponse(['message' => 'Invalid credentials'], 401);

    }
}
