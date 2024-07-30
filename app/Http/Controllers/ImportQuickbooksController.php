<?php

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Import\QuickbooksIngest;
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
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize($ability, $arguments = []): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() ; 
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

        return response()->json(['message' => 'Data cached for import'] + $data, 200);
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
