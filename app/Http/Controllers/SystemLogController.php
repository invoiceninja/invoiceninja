<?php

namespace App\Http\Controllers;

use App\Filters\SystemLogFilters;
use App\Models\SystemLog;
use App\Transformers\SystemLogTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;

class SystemLogController extends BaseController
{
    use MakesHash;

    protected $entity_type = SystemLog::class;

    protected $entity_transformer = SystemLogTransformer::class;

    /**
     * Show the list of Invoices.
     *
     * @param SystemLogFilters $filters The filters
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/system_logs",
     *      operationId="getSystemLogs",
     *      tags={"system_logs"},
     *      summary="Gets a list of system logs",
     *      description="Lists system logs, search and filters allow fine grained lists to be generated.
     *
     *      Query parameters can be added to performed more fine grained filtering of the system logs, these are handled by the SystemLogFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of system logs",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/SystemLog"),
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
    public function index(SystemLogFilters $filters)
    {
        $system_logs = SystemLog::filter($filters);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->isAdmin()) {
            return $this->listResponse($system_logs);
        }

        return $this->errorResponse('Insufficient permissions', 403);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        $error = [
            'message' => 'Cannot create system log',
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $error = [
            'message' => 'Cannot store system log',
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request The request
     * @param SystemLog $system_log
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/system_logs/{id}",
     *      operationId="showSystemLogs",
     *      tags={"system_logs"},
     *      summary="Shows a system_logs",
     *      description="Displays a system_logs by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The system_logs Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the system_logs object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/SystemLog"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(Request $request, SystemLog $system_log)
    {
        return $this->itemResponse($system_log);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $error = [
            'message' => 'Cannot edit system log',
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param  int  $id
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $error = [
            'message' => 'Cannot update system log',
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $error = [
            'message' => 'Cannot destroy system log',
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }
}
