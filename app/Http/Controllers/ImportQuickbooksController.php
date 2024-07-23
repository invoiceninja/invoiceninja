<?php

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Import\QuickbooksIngest;
use App\Http\Controllers\ImportController as BaseController;

class ImportQuickbooksController extends BaseController
{
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
            'hash'     => $hash,
            'type' => $request->input('import_type')
        ];
        $contents = $this->getData();
        Cache::put("$hash-{$data['type']}", base64_encode(json_encode($contents)), 600);

        return response()->json(['message' => 'Data cached for import'] + $data, 200);
    }

    public function authorizeQuickbooks() {

    }

    protected function getData() {

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
