<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\CompanyUser\UpdateCompanyUserFlutterSettingsRequest;
use App\Http\Requests\CompanyUser\UpdateCompanyUserPreferencesRequest;
use App\Http\Requests\CompanyUser\UpdateCompanyUserRequest;
use App\Models\CompanyUser;
use App\Models\User;
use App\Transformers\CompanyUserTransformer;
use App\Transformers\UserTransformer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

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
    public function index() {}

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create() {}

    public function store() {}

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
     * @return Response| \Illuminate\Http\JsonResponse|mixed|void
     */
    public function update(UpdateCompanyUserRequest $request, User $user)
    {
        /** @var \App\Models\User $auth_user */
        $auth_user = auth()->user();
        $company = $auth_user->company();

        $company_user = CompanyUser::query()->where('user_id', $user->id)->where('company_id', $company->id)->first();

        if (! $company_user) {
            throw new ModelNotFoundException(ctrans('texts.company_user_not_found'));
        }

        $company_user_input = $request->input('company_user');

        if ($auth_user->isOwner()) {
            $company_user->fill($company_user_input);
        } elseif ($auth_user->isAdmin()) {
            unset($company_user_input['is_owner']);
            $company_user->fill($company_user_input);
        } else {
            $company_user->settings = $request->input('company_user')['settings'];
            $company_user->notifications = $request->input('company_user')['notifications'];

            if (isset($request->input('company_user')['react_settings'])) {
                $company_user->react_settings = $request->input('company_user')['react_settings'];
            }

        }

        $company_user->save();

        return $this->itemResponse($company_user->fresh());
    }

    public function updatePreferences(UpdateCompanyUserPreferencesRequest $request, User $user)
    {
        /** @var \App\Models\User $auth_user */
        $auth_user = auth()->user();
        $company = $auth_user->company();

        $company = $auth_user->company();

        $company_user = CompanyUser::whereUserId($user->id)->whereCompanyId($company->id)->first();

        if (! $company_user) {
            throw new ModelNotFoundException(ctrans('texts.company_user_not_found'));
        }

        $this->entity_type = User::class;

        $this->entity_transformer = UserTransformer::class;

        $company_user->react_settings = $request->react_settings;
        $company_user->save();

        return $this->itemResponse($user->fresh());
    }

    /**
     * Applies a patch to the flutter_settings of the user in the current company.
     *
     * Each key of flutter_settings is a dot separated path, a null value removes
     * the path along with any parent it leaves empty, and setting a path through
     * a value that is not an object replaces that value. The settings are merged
     * rather than replaced so that one device can not overwrite the changes
     * another device made since it last loaded them.
     */
    public function updateFlutterSettings(UpdateCompanyUserFlutterSettingsRequest $request, User $user)
    {
        /** @var \App\Models\User $auth_user */
        $auth_user = auth()->user();
        $company = $auth_user->company();

        DB::transaction(function () use ($request, $user, $company) {
            $company_user = CompanyUser::query()
                ->where('user_id', $user->id)
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if (! $company_user) {
                throw new ModelNotFoundException(ctrans('texts.company_user_not_found'));
            }

            // Objects stay objects: decoding to arrays turned {} into [] and an
            // object with sequential numeric keys into a list on the way back.
            $flutter_settings = json_decode(json_encode($company_user->flutter_settings ?? new \stdClass()));

            if (! $flutter_settings instanceof \stdClass) {
                $flutter_settings = new \stdClass();
            }

            foreach ($this->flutterSettingsPatch($request) as $path => $value) {
                $segments = explode('.', (string) $path);

                if (is_null($value)) {
                    $this->forgetFlutterSetting($flutter_settings, $segments);
                } else {
                    $this->setFlutterSetting($flutter_settings, $segments, $value);
                }
            }

            $company_user->flutter_settings = $flutter_settings;
            $company_user->save();
        });

        $this->entity_type = User::class;

        $this->entity_transformer = UserTransformer::class;

        return $this->itemResponse($user->fresh());
    }


    /**
     * The patch as the client sent it. The global ConvertEmptyStringsToNull
     * middleware turns {"search":""} into {"search":null}, which here would
     * delete the setting, and decoding to arrays loses the difference between
     * an object and a list, so the patch is read from the raw JSON body. Only
     * an explicit JSON null deletes a path.
     */
    private function flutterSettingsPatch(UpdateCompanyUserFlutterSettingsRequest $request): \stdClass
    {
        $body = json_decode($request->getContent());

        if ($body instanceof \stdClass && ($body->flutter_settings ?? null) instanceof \stdClass) {
            return $body->flutter_settings;
        }

        // Not a JSON body: the validated input, already checked to be an object of paths.
        return json_decode(json_encode((object) $request->input('flutter_settings', [])));
    }

    /**
     * Sets the value at the path, creating objects along the way and replacing
     * any value on the path that is not an object.
     */
    private function setFlutterSetting(\stdClass $settings, array $segments, mixed $value): void
    {
        $node = $settings;
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            if (! (($node->{$segment} ?? null) instanceof \stdClass)) {
                $node->{$segment} = new \stdClass();
            }

            $node = $node->{$segment};
        }

        $node->{$last} = $value;
    }

    /**
     * Removes the value at the path, along with each parent object the removal
     * leaves empty. A path that does not exist is left alone.
     */
    private function forgetFlutterSetting(\stdClass $settings, array $segments): void
    {
        $chain = [$settings];
        $last = array_pop($segments);

        foreach ($segments as $segment) {
            $next = end($chain)->{$segment} ?? null;

            if (! $next instanceof \stdClass) {
                return;
            }

            $chain[] = $next;
        }

        $node = array_pop($chain);

        if (! property_exists($node, $last)) {
            return;
        }

        unset($node->{$last});

        while ($chain !== [] && count(get_object_vars($node)) === 0) {
            $parent = array_pop($chain);
            unset($parent->{array_pop($segments)});
            $node = $parent;
        }
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
