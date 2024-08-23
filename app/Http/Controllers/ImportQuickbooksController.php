<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Quickbooks\AuthorizedQuickbooksRequest;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Quickbooks\AuthQuickbooksRequest;
use App\Services\Import\Quickbooks\QuickbooksService;

class ImportQuickbooksController extends BaseController
{
    // private array $import_entities = [
    //     'client' => 'Customer',
    //     'invoice' => 'Invoice',
    //     'product' => 'Item',
    //     'payment' => 'Payment'
    // ];

    public function onAuthorized(AuthorizedQuickbooksRequest $request)
    {

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);
        $company = $request->getCompany();
        $qb = new QuickbooksService($company);

        $realm = $request->query('realmId');
        $access_token_object = $qb->sdk()->accessTokenFromCode($request->query('code'), $realm);
        $qb->sdk()->saveOAuthToken($access_token_object);

        return redirect(config('ninja.react_url'));

    }

    /**
     * Determine if the user is authorized to make this request.
     *
     */
    public function authorizeQuickbooks(AuthQuickbooksRequest $request, string $token)
    {

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);
        $company = $request->getCompany();
        $qb = new QuickbooksService($company);

        $authorizationUrl = $qb->sdk()->getAuthorizationUrl();
        $state = $qb->sdk()->getState();

        Cache::put($state, $token, 190);

        return redirect()->to($authorizationUrl);
    }

    public function preimport(string $type, string $hash)
    {
        // // Check for authorization otherwise
        // // Create a reference
        // $data = [
        //     'hash' => $hash,
        //     'type' => $type
        // ];
        // $this->getData($data);
    }

    protected function getData($data)
    {

        // $entity = $this->import_entities[$data['type']];
        // $cache_name = "{$data['hash']}-{$data['type']}";
        // // TODO: Get or put cache  or DB?
        // if(! Cache::has($cache_name)) {
        //     $contents = call_user_func([$this->service, "fetch{$entity}s"]);
        //     if($contents->isEmpty()) {
        //         return;
        //     }

        //     Cache::put($cache_name, base64_encode($contents->toJson()), 600);
        // }
    }

    /**
     * @OA\Post(
     *      path="/api/v1/import_json",
     *      operationId="getImportJson",
     *      tags={"import"},
     *      summary="Import data from the system",
     *      description="Import data from the system",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
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
    public function import(Request $request)
    {
        // $hash = Str::random(32);
        // foreach($request->input('import_types') as $type) {
        //     $this->preimport($type, $hash);
        // }
        // /** @var \App\Models\User $user */
        // // $user = auth()->user() ?? Auth::loginUsingId(60);
        // $data = ['import_types' => $request->input('import_types') ] + compact('hash');
        // if (Ninja::isHosted()) {
        //     QuickbooksIngest::dispatch($data, $user->company());
        // } else {
        //     QuickbooksIngest::dispatch($data, $user->company());
        // }

        // return response()->json(['message' => 'Processing'], 200);
    }
}
