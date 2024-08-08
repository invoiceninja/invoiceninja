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
                    return redirect()
                        ->back()
                        ->withErrors($validator)
                        ->withInput();
                }

                //If validation passes, proceed to the next middleware/controller
                return $next($request);
            }
        )->only('authorizeQuickbooks');
    }  

    public function onAuthorized(Request $request)
    {
        $realm = $request->query('realmId');
        $company_key = $request->input('company.company_key');
        $company_id = $request->input('company.id');
        $tokens = ($auth_service = $this->service->getOAuth())->accessToken($request->query('code'), $realm);
        $auth_service->saveTokens($company_key, ['realm' => $realm] + $tokens);
        
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

        Cache::put($state, $token, 190);

        return redirect()->to($authorizationUrl);
    }

    public function preimport(string $type, string $hash)
    {
        // Check for authorization otherwise 
        // Create a reference
        $data = [
            'hash' => $hash,
            'type' => $type
        ];
        $this->getData($data);
    }

    protected function getData($data) {

        $entity = $this->import_entities[$data['type']];
        $cache_name = "{$data['hash']}-{$data['type']}";
        // TODO: Get or put cache  or DB?
        if(! Cache::has($cache_name) )
        {
            $contents = call_user_func([$this->service, "fetch{$entity}s"]);
            if($contents->isEmpty()) return;
            
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
         $hash = Str::random(32);
        foreach($request->input('import_types') as $type)
        {
            $this->preimport($type, $hash);
        }
        /** @var \App\Models\User $user */
        $user = auth()->user() ?? Auth::loginUsingId(60);
        $data = ['import_types' => $request->input('import_types') ] + compact('hash');
        if (Ninja::isHosted()) {
            QuickbooksIngest::dispatch( $data , $user->company() );
        } else {
            QuickbooksIngest::dispatch($data, $user->company() );
        }

        return response()->json(['message' => 'Processing'], 200);
    }
}
