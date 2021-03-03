<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\DataMapper\CompanySettings;
use App\Events\User\UserWasCreated;
use App\Events\User\UserWasDeleted;
use App\Events\User\UserWasUpdated;
use App\Factory\UserFactory;
use App\Filters\UserFilters;
use App\Http\Controllers\Traits\VerifiesUserEmail;
use App\Http\Requests\User\AttachCompanyUserRequest;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\DestroyUserRequest;
use App\Http\Requests\User\DetachCompanyUserRequest;
use App\Http\Requests\User\EditUserRequest;
use App\Http\Requests\User\ReconfirmUserRequest;
use App\Http\Requests\User\ShowUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\User\UserEmailChanged;
use App\Mail\Admin\VerifyUserObject;
use App\Models\CompanyUser;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Transformers\UserTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
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
     *
     * @OA\Get(
     *      path="/api/v1/users",
     *      operationId="getUsers",
     *      tags={"users"},
     *      summary="Gets a list of users",
     *      description="Lists users, search and filters allow fine grained lists to be generated.

    Query parameters can be added to performed more fine grained filtering of the users, these are handled by the UserFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of users",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
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
     *
     *
     * @OA\Get(
     *      path="/api/v1/users/create",
     *      operationId="getUsersCreate",
     *      tags={"users"},
     *      summary="Gets a new blank User object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank User object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateUserRequest $request)
    {
        $user = UserFactory::create(auth()->user()->account->id);

        return $this->itemResponse($user);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreUserRequest $request
     * @return Response
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/users",
     *      operationId="storeUser",
     *      tags={"users"},
     *      summary="Adds a User",
     *      description="Adds an User to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved User object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreUserRequest $request)
    {
        $company = auth()->user()->company();

        $user = $this->user_repo->save($request->all(), $request->fetchUser());

        $user_agent = request()->input('token_name') ?: request()->server('HTTP_USER_AGENT');

        $ct = CreateCompanyToken::dispatchNow($company, $user, $user_agent);

        event(new UserWasCreated($user, auth()->user(), $company, Ninja::eventVars()));

        return $this->itemResponse($user->fresh());
    }

    /**
     * Display the specified resource.
     *
     * @param ShowUserRequest $request
     * @param User $user
     * @return Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/users/{id}",
     *      operationId="showUser",
     *      tags={"users"},
     *      summary="Shows an User",
     *      description="Displays an User by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The User Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the User object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
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
     *
     * @OA\Get(
     *      path="/api/v1/users/{id}/edit",
     *      operationId="editUser",
     *      tags={"users"},
     *      summary="Shows an User for editting",
     *      description="Displays an User by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The User Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the User object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditUserRequest $request, User $user)
    {
        return $this->itemResponse($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *      path="/api/v1/users/{id}",
     *      operationId="updateUser",
     *      tags={"users"},
     *      summary="Updates an User",
     *      description="Handles the updating of an User by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The User Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the User object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param UpdateUserRequest $request
     * @param User $user
     * @return Response|mixed
     */
    public function update(UpdateUserRequest $request, User $user)
    {

        $old_company_user = $user->company_user;
        $old_user = json_encode($user);
        $old_user_email = $user->getOriginal('email');

        $new_email = $request->input('email');
        $new_user = $this->user_repo->save($request->all(), $user);
        $new_user = $user->fresh();

        /* When changing email address we store the former email in case we need to rollback */
        if ($old_user_email != $new_email) {
            $user->last_confirmed_email_address = $old_user_email;
            $user->email_verified_at = null;
            $user->save();
            UserEmailChanged::dispatch($new_user, json_decode($old_user), auth()->user()->company());
        }
        
        
        if(
            strcasecmp($old_company_user->permissions, $user->company_user->permissions) != 0 ||
            $old_company_user->is_admin != $user->company_user->is_admin
          ){
            $user->company_user()->update(["permissions_updated_at" => now()]);
        }

        event(new UserWasUpdated($user, auth()->user(), auth()->user()->company, Ninja::eventVars()));

        return $this->itemResponse($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyUserRequest $request
     * @param User $user
     * @return Response
     *
     *
     * @OA\Delete(
     *      path="/api/v1/users/{id}",
     *      operationId="deleteUser",
     *      tags={"users"},
     *      summary="Deletes a User",
     *      description="Handles the deletion of an User by id",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="token_name",
     *          in="query",
     *          required=false,
     *          description="Customized name for the Users API Token",
     *          example="iOS Device 11 iPad",
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The User Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function destroy(DestroyUserRequest $request, User $user)
    {
        /* If the user passes the company user we archive the company user */
        $user = $this->user_repo->delete($request->all(), $user);

        event(new UserWasDeleted($user, auth()->user(), auth()->user()->company, Ninja::eventVars()));

        return $this->itemResponse($user->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Collection
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/users/bulk",
     *      operationId="bulkUsers",
     *      tags={"users"},
     *      summary="Performs bulk actions on an array of users",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Hashed ids",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/User"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $users = User::withTrashed()->find($this->transformKeys($ids));

        /*
         * In case a user maliciously sends keys which do not belong to them, we push
         * each user through the Policy sieve and only return users that they
         * have access to
         */

        $return_user_collection = collect();

        $users->each(function ($user, $key) use ($action, $return_user_collection) {
            if (auth()->user()->can('edit', $user)) {
                $this->user_repo->{$action}($user);

                $return_user_collection->push($user->id);
            }
        });

        return $this->listResponse(User::withTrashed()->whereIn('id', $return_user_collection));
    }

    /**
     * Attach an existing user to a company.
     *
     * @OA\Post(
     *      path="/api/v1/users/{user}/attach_to_company",
     *      operationId="attachUser",
     *      tags={"users"},
     *      summary="Attach an existing user to a company",
     *      description="Attach an existing user to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="user",
     *          in="path",
     *          description="The user hashed_id",
     *          example="FD767dfd7",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\RequestBody(
     *         description="The company user object",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CompanyUser"),
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved User object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyUser"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param AttachCompanyUserRequest $request
     * @param User $user
     * @return Response|mixed
     */
    public function attach(AttachCompanyUserRequest $request, User $user)
    {
        $company = auth()->user()->company();

        $user->companies()->attach(
            $company->id,
            array_merge(
                $request->all(),
                [
                    'account_id' => $company->account->id,
                    'notifications' => CompanySettings::notificationDefaults(),
            ]
            )
        );

        $ct = CreateCompanyToken::dispatchNow($company, $user, 'User token created by'.auth()->user()->present()->name());

        return $this->itemResponse($user->fresh());
    }

    /**
     * Detach an existing user to a company.
     *
     * @OA\Delete(
     *      path="/api/v1/users/{user}/detach_from_company",
     *      operationId="detachUser",
     *      tags={"users"},
     *      summary="Detach an existing user to a company",
     *      description="Detach an existing user from a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="user",
     *          in="path",
     *          description="The user hashed_id",
     *          example="FD767dfd7",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param DetachCompanyUserRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function detach(DetachCompanyUserRequest $request, User $user)
    {
        $company_user = CompanyUser::whereUserId($user->id)
                                    ->whereCompanyId(auth()->user()->companyId())->first();

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
     * Detach an existing user to a company.
     *
     * @OA\Post(
     *      path="/api/v1/users/{user}/reconfirm",
     *      operationId="reconfirmUser",
     *      tags={"users"},
     *      summary="Reconfirm an existing user to a company",
     *      description="Reconfirm an existing user from a company",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="user",
     *          in="path",
     *          description="The user hashed_id",
     *          example="FD767dfd7",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param ReconfirmUserRequest $request
     * @param User $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function reconfirm(ReconfirmUserRequest $request, User $user)
    {
        $user->confirmation_code = $this->createDbHash($user->company()->db);
        $user->save();

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new VerifyUserObject($user, $user->company()))->build());
        $nmo->company = $user->company();
        $nmo->to_user = $user;
        $nmo->settings = $user->company->settings;

        NinjaMailerJob::dispatch($nmo);

        return response()->json(['message' => ctrans('texts.confirmation_resent')], 200);

    }


}
