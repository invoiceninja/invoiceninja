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

use App\Http\Requests\CompanyUser\UpdateCompanyUserRequest;
use App\Models\CompanyUser;
use App\Models\User;
use App\Transformers\CompanyUserTransformer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;

class CompanyUserController extends BaseController
{
    protected $entity_type = CompanyUser::class;

    protected $entity_transformer = CompanyUserTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
    }

    public function store()
    {
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     *
     * @OA\Post(
     *      path="/api/v1/company_users",
     *      operationId="updateCompanyUser",
     *      tags={"company_user"},
     *      summary="Update a company user record",
     *      description="Attempts to update a company user record. A company user can modify only their settings fields. Full access for Admin users",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="The Company User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyUser"),
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
     * @param UpdateCompanyUserRequest $request
     * @param User $user
     * @return Response|mixed|void
     */
    public function update(UpdateCompanyUserRequest $request, User $user)
    {
        $company = auth()->user()->company();

        $company_user = CompanyUser::whereUserId($user->id)->whereCompanyId($company->id)->first();

        if (! $company_user) {
            throw new ModelNotFoundException(ctrans('texts.company_user_not_found'));

            return;
        }

        if (auth()->user()->isAdmin()) {
            $company_user->fill($request->input('company_user'));
        } else {
            $company_user->settings = $request->input('company_user')['settings'];
            $company_user->notifications = $request->input('company_user')['notifications'];
        }

        $company_user->save();

        return $this->itemResponse($company_user->fresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
