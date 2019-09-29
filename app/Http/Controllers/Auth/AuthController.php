<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Ninja\Repositories\AccountRepository;
use App\Services\AuthService;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    /**
     * @var AuthService
     */
    protected $authService;

    /**
     * @var AccountRepository
     */
    protected $accountRepo;

    /**
     * Create a new authentication controller instance.
     *
     * @param AccountRepository $repo
     * @param AuthService       $authService
     *
     * @internal param \Illuminate\Contracts\Auth\Guard $auth
     * @internal param \Illuminate\Contracts\Auth\Registrar $registrar
     */
    public function __construct(AccountRepository $repo, AuthService $authService)
    {
        $this->accountRepo = $repo;
        $this->authService = $authService;
    }

    /**
     * @param $provider
     * @param Request $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function oauthLogin($provider, Request $request)
    {
        return $this->authService->execute($provider, $request->filled('code'));
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function oauthUnlink()
    {
        $this->accountRepo->unlinkUserFromOauth(auth()->user());

        session()->flash('message', trans('texts.updated_settings'));

        return redirect()->to('/settings/' . ACCOUNT_USER_DETAILS);
    }
}
