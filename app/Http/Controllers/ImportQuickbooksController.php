<?php

namespace App\Http\Controllers;

use \Closure;
use App\Utils\Ninja;
use App\Models\Company;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Import\QuickbooksIngest;
use Illuminate\Support\Facades\Validator;
use App\Services\Import\Quickbooks\Service as QuickbooksService;

class ImportQuickbooksController extends BaseController
{
    protected QuickbooksService $service; 
    private $import_entities = [
        'client' => 'Customer',
        'invoice' => 'Invoice',
        'product' => 'Item',
        'payment' => 'Payment'
    ];

    public function __construct(QuickbooksService $service) {
        parent::__construct();

        $this->service = $service;
        $this->middleware(
            function (Request $request, Closure $next) {
               
                // Check for the required query parameters
                if (!$request->has(['code', 'state', 'realmId'])) {
                    return abort(400,'Unauthorized');
                }

                $rules = [
                    'state' => [
                        'required',
                        'valid' => function ($attribute, $value, $fail) {
                            if (!Cache::has($value)) {
                                $fail('The state is invalid.');
                            }
                        },
                    ]
                ];
                // Custom error messages
                $messages = [
                    'state.required' => 'The state is required.',
                    'state.valid' => 'state token not valid'
                ];
                // Perform the validation
                $validator = Validator::make($request->all(), $rules, $messages);
                if ($validator->fails()) {
                    // If validation fails, redirect back with errors and input
                    return redirect('/')
                        ->withErrors($validator)
                        ->withInput();
                }

                $token = Cache::pull($request->state);
                $request->merge(['company' => Cache::get($token) ]);

                return $next($request);
            }
        )->only('onAuthorized');
        $this->middleware(
            function ( Request $request, Closure $next) {
                $rules = [
                    'token' => [
                        'required',
                        'valid' => function ($attribute, $value, $fail) {
                            if (!Cache::has($value) || (!Company::where('company_key', (Cache::get($value))['company_key'])->exists() )) {
                                $fail('The company is invalid.');
                            }
                        },
                    ]
                ];
                // Custom error messages
                $messages = [
                    'token.required' => 'The token is required.',
                    'token.valid' => 'Token note valid!'
                ];
                // Perform the validation
                $validator = Validator::make(['token' => $request->token ], $rules, $messages);
                if ($validator->fails()) {
                    // If validation fails, redirect back with errors and input
                    return redirect()->back()
                        ->withErrors($validator)
                        ->withInput();
                }

                //If validation passes, proceed to the next middleware/controller
                return $next($request);
            }
        )->only('authorizeQuickbooks');
    }  

    public function onAuthorized(Request $request) {

        $realmId = $request->query('realmId');
        $tokens = $this->service->getOAuth()->accessToken($request->query('code'), $realmId);
        $company = $request->input('company');
        Cache::put($company['company_key'], $tokens['access_token'], $tokens['access_token_expires']);
        // TODO: save refresh token and realmId in company DB

        return response(200);
    } 

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorizeQuickbooks(Request $request)
    {
        $token = $request->token;
        $auth = $this->service->getOAuth();
        $authorizationUrl = $auth->getAuthorizationUrl();
        $state = $auth->getState();

        Cache::put($state, $token, 90);

        return redirect()->to($authorizationUrl);
    }

    public function preimport(Request $request)
    {
        // Check for authorization otherwise 
        // Create a reference
        $hash = Str::random(32);
        $data = [
            'hash' => $hash,
            'type' => $request->input('import_type', 'client'),
            'max' => $request->input('max', 100)
        ];
        $this->getData($data);

        return $data;
    }

    protected function getData($data) {

        $entity = $this->import_entities[$data['type']];
        $cache_name = "{$data['hash']}-{$data['type']}";
        // TODO: Get or put cache  or DB?
        if(!  Cache::has($cache_name) )
        {
            $contents = call_user_func([$this->service, "fetch{$entity}s"], $data['max']);
            Cache::put($cache_name, base64_encode( $contents->toJson()), 600);
        }
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
        $this->preimport($request);
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if (Ninja::isHosted()) {
            QuickbooksIngest::dispatch($request->all(), $user->company() );
        } else {
            QuickbooksIngest::dispatch($request->all(), $user->company() );
        }

        return response()->json(['message' => 'Processing'], 200);
    }
}
