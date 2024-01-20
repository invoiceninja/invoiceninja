<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Events\User\UserWasCreated;
use App\Events\User\UserWasDeleted;
use App\Events\User\UserWasUpdated;
use App\Factory\UserFactory;
use App\Filters\UserFilters;
use App\Http\Controllers\Traits\VerifiesUserEmail;
use App\Http\Requests\User\BulkUserRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\DestroyUserRequest;
use App\Http\Requests\User\DetachCompanyUserRequest;
use App\Http\Requests\User\DisconnectUserMailerRequest;
use App\Http\Requests\User\EditUserRequest;
use App\Http\Requests\User\ReconfirmUserRequest;
use App\Http\Requests\User\ShowUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\User\UserEmailChanged;
use App\Models\CompanyUser;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Transformers\UserTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

/**
 * Class UserController.
 */
class UserController extends BaseController
{
    use VerifiesUserEmail;
    use MakesHash;

    protected $entity_type = User::class;

    protected $entity_transformer = UserTransformer::class;

    protected $user_repo;

    /**
     * Constructor.
     *
     * @param UserRepository $user_repo  The user repo
     */
    public function __construct(UserRepository $user_repo)
    {
        parent::__construct();

        $this->user_repo = $user_repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @param UserFilters $filters
     * @return Response
     *
     */
    public function index(UserFilters $filters)
    {
        $users = User::filter($filters);

        return $this->listResponse($users);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateUserRequest $request
     * @return Response
     *
     */
    public function create(CreateUserRequest $request)
    {
        $user = UserFactory::create(auth()->user()->account_id);

        return $this->itemResponse($user);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreUserRequest $request
     * @return Response
     *
     */
    public function store(StoreUserRequest $request)
    {
        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        $company = $logged_in_user->company();

        $user = $this->user_repo->save($request->all(), $request->fetchUser());

        $user_agent = request()->input('token_name') ?: request()->server('HTTP_USER_AGENT');

        $is_react = $request->hasHeader('X-React') ?? false;

        $ct = (new CreateCompanyToken($company, $user, $user_agent))->handle();

        event(new UserWasCreated($user, auth()->user(), $company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $is_react));

        $user->setCompany($company);
        $user->company_id = $company->id;

        return $this->itemResponse($user);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowUserRequest $request
     * @param User $user
     * @return Response
     *
     */
    public function show(ShowUserRequest $request, User $user)
    {
        return $this->itemResponse($user);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditUserRequest $request
     * @param User $user
     * @return Response
     *
     */
    public function edit(EditUserRequest $request, User $user)
    {
        return $this->itemResponse($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return Response|mixed
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        $old_company_user = $user->company_users()->where('company_id', $logged_in_user->company()->id)->first();
        $old_user = json_encode($user);
        $old_user_email = $user->getOriginal('email');

        $new_email = $request->input('email');
        $new_user = $this->user_repo->save($request->all(), $user);
        $new_user = $user->fresh();

        /* When changing email address we store the former email in case we need to rollback */
        /* 27-10-2022 we need to wipe the oauth data at this point*/
        if ($old_user_email != $new_email) {
            $user->last_confirmed_email_address = $old_user_email;
            $user->email_verified_at = null;
            $user->oauth_user_id = null;
            $user->oauth_provider_id = null;
            $user->oauth_user_refresh_token = null;
            $user->oauth_user_token = null;
            $user->save();

            UserEmailChanged::dispatch($new_user, json_decode($old_user), $logged_in_user->company(), $request->hasHeader('X-React'));
        }

        event(new UserWasUpdated($user, $logged_in_user, $logged_in_user->company(), Ninja::eventVars($logged_in_user->id)));

        return $this->itemResponse($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyUserRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     *
     */
    public function destroy(DestroyUserRequest $request, User $user)
    {
        if ($user->hasOwnerFlag()) {
            return response()->json(['message', 'Cannot detach owner.'], 401);
        }

        /* If the user passes the company user we archive the company user */
        $user = $this->user_repo->delete($request->all(), $user);

        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        event(new UserWasDeleted($user, $logged_in_user, $logged_in_user->company(), Ninja::eventVars($logged_in_user->id)));

        return $this->itemResponse($user->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response
     *
     */
    public function bulk(BulkUserRequest $request)
    {
        /* Validate restore() here and check if restoring the user will exceed their user quote (hosted only)*/
        $action = request()->input('action');

        $ids = request()->input('ids');

        $users = User::withTrashed()->find($this->transformKeys($ids));

        /*
         * In case a user maliciously sends keys which do not belong to them, we push
         * each user through the Policy sieve and only return users that they
         * have access to
         */

        $return_user_collection = collect();

        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        $users->each(function ($user, $key) use ($logged_in_user, $action, $return_user_collection) {
            if ($logged_in_user->can('edit', $user)) {
                $this->user_repo->{$action}($user);

                $return_user_collection->push($user->id);
            }
        });

        return $this->listResponse(User::withTrashed()->whereIn('id', $return_user_collection));
    }

    /**
     * Detach an existing user to a company.
     *
     * @param DetachCompanyUserRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function detach(DetachCompanyUserRequest $request, User $user)
    {
        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        $company_user = CompanyUser::whereUserId($user->id)
                                    ->whereCompanyId($logged_in_user->companyId())
                                    ->withTrashed()
                                    ->first();

        if ($company_user->is_owner) {
            return response()->json(['message', 'Cannot detach owner.'], 401);
        }

        $token = $company_user->token->where('company_id', $company_user->company_id)->where('user_id', $company_user->user_id)->first();

        if ($token) {
            $token->delete();
        }

        if ($company_user) {
            $company_user->delete();
        }

        return response()->json(['message' => ctrans('texts.user_detached')], 200);
    }

    /**
     * Invite an existing user to a company.
     *
     * @param ReconfirmUserRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function invite(ReconfirmUserRequest $request, User $user)
    {
        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        $user->service()->invite($logged_in_user->company(), $request->hasHeader('X-REACT'));

        return response()->json(['message' => ctrans('texts.confirmation_resent')], 200);
    }


    /**
     * Invite an existing user to a company.
     *
     * @param ReconfirmUserRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function reconfirm(ReconfirmUserRequest $request, User $user)
    {
        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();

        $user->service()->invite($logged_in_user->company(), $request->hasHeader('X-REACT'));

        return response()->json(['message' => ctrans('texts.confirmation_resent')], 200);
    }

    public function disconnectOauthMailer(DisconnectUserMailerRequest $request, User $user)
    {

        $user->oauth_user_token = null;
        $user->oauth_user_refresh_token = null;
        $user->save();


        /** @var \App\Models\User $logged_in_user */
        $logged_in_user = auth()->user();
        $company = $logged_in_user->company();

        $settings = $company->settings;
        $settings->email_sending_method = "default";
        $settings->gmail_sending_user_id = "0";

        $company->settings = $settings;
        $company->save();

        return $this->itemResponse($user->fresh());

    }

    public function disconnectOauth(DisconnectUserMailerRequest $request, User $user)
    {
        $user->oauth_user_id = null;
        $user->oauth_provider_id = null;
        $user->oauth_user_token_expiry = null;
        $user->oauth_user_token = null;
        $user->oauth_user_refresh_token = null;
        $user->save();

        return $this->itemResponse($user->fresh());

    }

}
