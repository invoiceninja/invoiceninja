<?php namespace App\Http\Controllers;

use App\Services\UserService;
use App\Ninja\Repositories\UserRepository;
use App\Ninja\Transformers\UserTransformer;
use Auth;
use App\Models\User;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;

class UserApiController extends BaseAPIController
{
    protected $userService;
    protected $userRepo;

    public function __construct(UserService $userService, UserRepository $userRepo)
    {
        //parent::__construct();

        $this->userService = $userService;
        $this->userRepo = $userRepo;
    }

    public function index()
    {
        $user = Auth::user();
        $users = User::whereAccountId($user->account_id)->withTrashed();
        $users = $users->paginate();

        $paginator = User::whereAccountId($user->account_id)->withTrashed()->paginate();

        $transformer = new UserTransformer(Auth::user()->account, $this->serializer);
        $data = $this->createCollection($users, $transformer, 'users', $paginator);

        return $this->response($data);
    }

    /*
    public function store(CreateUserRequest $request)
    {
        return $this->save($request);
    }
    */

    public function update(UpdateUserRequest $request, $userPublicId)
    {
        /*
        // temporary fix for ids starting at 0
        $userPublicId -= 1;
        $user = User::scope($userPublicId)->firstOrFail();
        */
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
}