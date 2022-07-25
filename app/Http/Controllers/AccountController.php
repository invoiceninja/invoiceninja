<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Account\CreateAccountRequest;
use App\Http\Requests\Account\UpdateAccountRequest;
use App\Jobs\Account\CreateAccount;
use App\Models\Account;
use App\Models\CompanyUser;
use App\Transformers\AccountTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Utils\TruthSource;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;

class AccountController extends BaseController
{
    use DispatchesJobs;

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
        // return view('signup.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateAccountRequest $request
     * @return Response
     *
     * @OA\Post(
     *      path="/api/v1/signup",
     *      operationId="postSignup",
     *      tags={"signup"},
     *      summary="Attempts a new account signup",
     *      description="Attempts a new account signup and returns a CompanyUser object on success",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="token_name",
     *          in="query",
     *          description="A custom name for the user company token",
     *          example="Daves iOS Device",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\RequestBody(
     *         description="Signup credentials",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     description="The user email address",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="first_name",
     *                     description="The signup users first name",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="last_name",
     *                     description="The signup users last name",
     *                     type="string",
     *                 ),
     *                 @OA\Property(
     *                     property="terms_of_service",
     *                     description="The user accepted the terms of service",
     *                     type="boolean",
     *                 ),
     *                 @OA\Property(
     *                     property="privacy_policy",
     *                     description="The user accepted the privacy policy",
     *                     type="boolean",
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     example="1234567",
     *                     description="The user password must meet minimum criteria ~ >6 characters",
     *                     type="string"
     *                 )
     *             )
     *         )
     *     ),
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
     */
    public function store(CreateAccountRequest $request)
    {
        $account = (new CreateAccount($request->all(), $request->getClientIp()))->handle();
        if (! ($account instanceof Account)) {
            return $account;
        }

        $ct = CompanyUser::whereUserId(auth()->user()->id);

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($ct->first());
        $truth->setUser(auth()->user());
        $truth->setCompany($ct->first()->company);

        return $this->listResponse($ct);
    }

    public function update(UpdateAccountRequest $request, Account $account)
    {
        $fi = new \FilesystemIterator(public_path('react'), \FilesystemIterator::SKIP_DOTS);

        if (iterator_count($fi) < 30) {
            return response()->json(['message' => 'React App Not Installed, Please install the React app before attempting to switch.'], 400);
        }

        $account->fill($request->all());
        $account->save();

        $this->entity_type = Account::class;

        $this->entity_transformer = AccountTransformer::class;

        return $this->itemResponse($account);
    }
}
