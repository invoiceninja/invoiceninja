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

use App\Libraries\MultiDB;
use App\Libraries\OAuth\Providers\Google;
use App\Models\CompanyUser;
use App\Transformers\CompanyUserTransformer;
use Google_Client;
use Illuminate\Http\Request;

class ConnectedAccountController extends BaseController
{

    protected $entity_type = CompanyUser::class;

    protected $entity_transformer = CompanyUserTransformer::class;
    
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Connect an OAuth account to a regular email/password combination account
     *
     * @param Request $request
     * @return User Refresh Feed.
     *
     *
     * @OA\Post(
     *      path="/api/v1/connected_account",
     *      operationId="connected_account",
     *      tags={"connected_account"},
     *      summary="Connect an oauth user to an existing user",
     *      description="Refreshes the dataset",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/include_static"),
     *      @OA\Parameter(ref="#/components/parameters/clear_cache"),
     *      @OA\Response(
     *          response=200,
     *          description="The Company User response",
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
    public function index(Request $request)
    {
        if ($request->input('provider') == 'google') {
            return $this->handleGoogleOauth();
        }

        return response()
        ->json(['message' => 'Provider not supported'], 400)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function handleGoogleOauth()
    {
        $user = false;

        $google = new Google();

        if($request->header('X-API-OAUTH-PASSWORD') && strlen($request->header('X-API-OAUTH-PASSWORD')) >=1){
            $user = $google->getTokenResponse($request->header('X-API-OAUTH-PASSWORD'));
        }
        else {
            return response()
                ->json(['message' => 'No valid oauth parameter sent.'], 401)
                ->header('X-App-Version', config('ninja.app_version'))
                ->header('X-Api-Version', config('ninja.minimum_client_version'));
        }


        if (is_array($user)) {
            
            $query = [
                'oauth_user_id' => $google->harvestSubField($user),
                'oauth_provider_id'=> 'google',
            ];

            /* Cannot allow duplicates! */
            if ($existing_user = MultiDB::hasUser($query)) {
                return response()
                ->json(['message' => 'User already exists in system.'], 401)
                ->header('X-App-Version', config('ninja.app_version'))
                ->header('X-Api-Version', config('ninja.minimum_client_version'));
            }
        }

        if ($user) {
            $client = new Google_Client();
            $client->setClientId(config('ninja.auth.google.client_id'));
            $client->setClientSecret(config('ninja.auth.google.client_secret'));
            $client->setRedirectUri(config('ninja.app_url'));
            $token = $client->authenticate(request()->input('server_auth_code'));

            $refresh_token = '';

            if (array_key_exists('refresh_token', $token)) {
                $refresh_token = $token['refresh_token'];
            }


            $connected_account = [
                'password' => '',
                'email' => $google->harvestEmail($user),
                'oauth_user_id' => $google->harvestSubField($user),
                'oauth_user_token' => $token,
                'oauth_user_refresh_token' => $refresh_token,
                'oauth_provider_id' => 'google',
                'email_verified_at' =>now()
            ];

            auth()->user()->update($connected_account);
            auth()->user()->email_verified_at = now();
            auth()->user()->save();

            //$ct = CompanyUser::whereUserId(auth()->user()->id);
            //return $this->listResponse($ct);
            
            return $this->itemResponse(auth()->user());
            // return $this->listResponse(auth()->user());
        }

        return response()
        ->json(['message' => ctrans('texts.invalid_credentials')], 401)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }
}
