<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Ninja\Repositories\UserRepository;
use App\Ninja\Transformers\UserTransformer;
use App\Services\UserService;
use Auth;

class UserApiController extends BaseAPIController
{
    protected $userService;
    protected $userRepo;

    protected $entityType = ENTITY_USER;

    public function __construct(UserService $userService, UserRepository $userRepo)
    {
        parent::__construct();

        $this->userService = $userService;
        $this->userRepo = $userRepo;
    }

    /**
     * @SWG\Get(
     *   path="/users",
     *   summary="List users",
     *   operationId="listUsers",
     *   tags={"user"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list of users",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/User"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $users = User::whereAccountId(Auth::user()->account_id)
                        ->withTrashed()
                        ->orderBy('created_at', 'desc');

        return $this->listResponse($users);
    }

    /**
     * @SWG\Get(
     *   path="/users/{user_id}",
     *   summary="Retrieve a user",
     *   operationId="getUser",
     *   tags={"client"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="user_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="A single user",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/User"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function show(UserRequest $request)
    {
        return $this->itemResponse($request->entity());
    }

    /**
     * @SWG\Post(
     *   path="/users",
     *   summary="Create a user",
     *   operationId="createUser",
     *   tags={"user"},
     *   @SWG\Parameter(
     *     in="body",
     *     name="user",
     *     @SWG\Schema(ref="#/definitions/User")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New user",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/User"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateUserRequest $request)
    {
        return $this->save($request);
    }

    /**
     * @SWG\Put(
     *   path="/users/{user_id}",
     *   summary="Update a user",
     *   operationId="updateUser",
     *   tags={"user"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="user_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Parameter(
     *     in="body",
     *     name="user",
     *     @SWG\Schema(ref="#/definitions/User")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Updated user",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/User"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     *
     * @param mixed $userPublicId
     */
    public function update(UpdateUserRequest $request, $userPublicId)
    {
        $user = Auth::user();

        if ($request->action == ACTION_ARCHIVE) {
            $this->userRepo->archive($user);

            $transformer = new UserTransformer(Auth::user()->account, $request->serializer);
            $data = $this->createItem($user, $transformer, 'users');

            return $this->response($data);
        } else {
            return $this->save($request, $user);
        }
    }

    private function save($request, $user = false)
    {
        $user = $this->userRepo->save($request->input(), $user);

        $transformer = new UserTransformer(\Auth::user()->account, $request->serializer);
        $data = $this->createItem($user, $transformer, 'users');

        return $this->response($data);
    }

    /**
     * @SWG\Delete(
     *   path="/users/{user_id}",
     *   summary="Delete a user",
     *   operationId="deleteUser",
     *   tags={"user"},
     *   @SWG\Parameter(
     *     in="path",
     *     name="user_id",
     *     type="integer",
     *     required=true
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Deleted user",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/User"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function destroy(UpdateUserRequest $request)
    {
        $entity = $request->entity();

        $this->userRepo->delete($entity);

        return $this->itemResponse($entity);
    }
}
