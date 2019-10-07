<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Factory\UserFactory;
use App\Filters\UserFilters;
use App\Http\Controllers\Traits\VerifiesUserEmail;
use App\Http\Requests\User\CreateUserRequest;
use App\Http\Requests\User\DestroyUserRequest;
use App\Http\Requests\User\EditUserRequest;
use App\Http\Requests\User\ShowUserRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Jobs\Company\CreateCompanyToken;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Transformers\UserTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class UserController
 * @package App\Http\Controllers
 */
class UserController extends BaseController
{
    use VerifiesUserEmail;

	use MakesHash;

    protected $entity_type = User::class;

    protected $entity_transformer = UserTransformer::class;

    protected $user_repo;

	/**
     * Constructor
     *
     * @param      \App\Repositories\UserRepository  $user_repo  The user repo
     */
    public function __construct(UserRepository $user_repo)
    {
    
        parent::__construct();

        $this->user_repo = $user_repo;

    }
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     * @return \Illuminate\Http\Response
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     *
     */
    public function create(CreateUserRequest $request)
    {

        $user = UserFactory::create();

        return $this->itemResponse($user);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     *
     */
    public function store(StoreUserRequest $request)
    {

        $company = auth()->user()->company();
        //save user
        
        $user = $this->user_repo->save($request->all(), UserFactory::create($company->id, auth()->user()->id));

        $user->companies()->attach($company->id, [
            'account_id' => $company->account->id,
            'is_owner' => 0,
            'is_admin' => $request->input('is_admin'),
            'is_locked' => 0,
            'permissions' => $request->input('permissions'),
            'settings' => $request->input('settings'),
        ]);

        CreateCompanyToken::dispatchNow($company, $user);

        $user->load('companies');

        return $this->itemResponse($user);
        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     *
     */ 
    public function show(ShowUserRequest $request, User $user)
    {
    
        return $this->itemResponse($user);
    
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     *
     */ 
    public function edit(EditUserRequest $request, User $user)
    {

        return $this->itemResponse($user);

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     *
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     *
     */ 
    public function update(UpdateUserRequest $request, User $user)
    {

        $user = $this->user_repo->save($request->all(), $user);

        return $this->itemResponse($user);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
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
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
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
     *
     */
    public function destroy(DestroyUserRequest $request, User $user)
    {

        $user->delete();
        
        return response()->json([], 200);

    }

    /**
     * Perform bulk actions on the list view
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
     *         description="User credentials",
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
     *          description="The User User response",
     *          @OA\Header(header="X-API-TOKEN", ref="#/components/headers/X-API-TOKEN"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/UserUser"),
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
     *
     */
    public function bulk()
    {

        $action = request()->input('action');
        
        $ids = request()->input('ids');

        $ids = $this->transformKeys($ids);

        $users = User::withTrashed()->find($ids);

        $users->each(function ($user, $key) use($action){

            if(auth()->user()->can('edit', $user))
                $this->user_repo->{$action}($user);

        });

        //todo need to return the updated dataset
        return $this->listResponse(User::withTrashed()->whereIn('id', $ids));
        
    }

}
