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

use App\Libraries\MultiDB;
use App\Libraries\OAuth\Providers\Google;
use App\Models\User;
use App\Transformers\UserTransformer;
use App\Utils\Traits\User\LoginCache;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Microsoft\Graph\Model;

class ConnectedAccountController extends BaseController
{
    use LoginCache;

    protected $entity_type = User::class;

    protected $entity_transformer = UserTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Connect an OAuth account to a regular email/password combination account
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/connected_account",
     *      operationId="connected_account",
     *      tags={"connected_account"},
     *      summary="Connect an oauth user to an existing user",
     *      description="Refreshes the dataset",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
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

        if ($request->input('provider') == 'microsoft') {
            return $this->handleMicrosoftOauth($request);
        }

        return response()
        ->json(['message' => 'Provider not supported'], 400)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function handleMicrosoftOauth($request)
    {
        $access_token = false;
        $access_token = $request->has('access_token') ? $request->input('access_token') : $request->input('accessToken');

        if (!$access_token) {
            return response()->json(['message' => 'No access_token parameter found!'], 400);
        }

        $graph = new \Microsoft\Graph\Graph();
        $graph->setAccessToken($access_token);

        $user = $graph->createRequest("GET", "/me")
                      ->setReturnType(Model\User::class)
                      ->execute();

        if ($user) {
            $email = $user->getUserPrincipalName() ?? false;

            nlog("microsoft");
            nlog($email);

            if (auth()->user()->email != $email && MultiDB::checkUserEmailExists($email)) {
                return response()->json(['message' => ctrans('texts.email_already_register')], 400);
            }

            $connected_account = [
                'email' => $email,
                'oauth_user_id' => $user->getId(),
                'oauth_provider_id' => 'microsoft',
                'email_verified_at' => now()
            ];


            /** @var \App\Models\User $user */
            $user = auth()->user();

            $user->update($connected_account);
            $user->email_verified_at = now();
            $user->save();

            $this->setLoginCache(auth()->user());

            return $this->itemResponse(auth()->user());
        }

        return response()
        ->json(['message' => ctrans('texts.invalid_credentials')], 401)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function handleGoogleOauth()
    {
        $user = false;

        $google = new Google();

        $user = $google->getTokenResponse(request()->input('id_token'));

        if ($user) {
            $client = new Google_Client();
            $client->setClientId(config('ninja.auth.google.client_id'));
            $client->setClientSecret(config('ninja.auth.google.client_secret'));
            $client->setRedirectUri(config('ninja.app_url'));
            $refresh_token = '';
            $token = '';

            $email = $google->harvestEmail($user);

            if (auth()->user()->email != $email && MultiDB::checkUserEmailExists($email)) {
                return response()->json(['message' => ctrans('texts.email_already_register')], 400);
            }

            $connected_account = [
                'email' => $email,
                'oauth_user_id' => $google->harvestSubField($user),
                'oauth_provider_id' => 'google',
                'email_verified_at' => now(),
            ];

            /** @var \App\Models\User $logged_in_user */
            $logged_in_user = auth()->user();

            $logged_in_user->update($connected_account);
            $logged_in_user->email_verified_at = now();
            $logged_in_user->save();

            $this->setLoginCache($logged_in_user);

            return $this->itemResponse($logged_in_user);
        }

        return response()
        ->json(['message' => ctrans('texts.invalid_credentials')], 401)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    public function handleGmailOauth(Request $request)
    {
        $user = false;

        $google = new Google();

        $user = $google->getTokenResponse($request->input('id_token'));

        if ($user) {
            $client = new Google_Client();
            $client->setClientId(config('ninja.auth.google.client_id'));
            $client->setClientSecret(config('ninja.auth.google.client_secret'));
            $client->setRedirectUri(config('ninja.app_url'));
            $token = $client->authenticate($request->input('server_auth_code'));

            $refresh_token = '';

            if (array_key_exists('refresh_token', $token)) {
                $refresh_token = $token['refresh_token'];
            }

            $connected_account = [
                'email' => $google->harvestEmail($user),
                'oauth_user_id' => $google->harvestSubField($user),
                // 'oauth_user_token' => $token,
                // 'oauth_user_refresh_token' => $refresh_token,
                'oauth_provider_id' => 'google',
                // 'email_verified_at' =>now(),
            ];

            /** @var \App\Models\User $logged_in_user */
            $logged_in_user = auth()->user();

            if ($logged_in_user->email != $google->harvestEmail($user)) {
                return response()->json(['message' => 'Primary Email differs to OAuth email. Emails must match.'], 400);
            }

            $logged_in_user->update($connected_account);
            $logged_in_user->email_verified_at = now();
            $logged_in_user->oauth_user_token = $token;
            $logged_in_user->oauth_user_refresh_token = $refresh_token;
            $logged_in_user->save();

            $this->activateGmail($logged_in_user);

            return $this->itemResponse($logged_in_user);
        }

        return response()
        ->json(['message' => ctrans('texts.invalid_credentials')], 401)
        ->header('X-App-Version', config('ninja.app_version'))
        ->header('X-Api-Version', config('ninja.minimum_client_version'));
    }

    private function activateGmail(User $user)
    {
        $company = $user->company();
        $settings = $company->settings;

        if ($settings->email_sending_method == 'default') {
            $settings->email_sending_method = 'gmail';
            $settings->gmail_sending_user_id = (string) $user->hashed_id;

            $company->settings = $settings;
            $company->save();
        }
    }
}
