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
