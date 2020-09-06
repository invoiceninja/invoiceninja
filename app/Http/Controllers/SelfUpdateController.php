<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Composer\Factory;
use Composer\Installer;
use Composer\IO\NullIO;
use Cz\Git\GitException;
use Cz\Git\GitRepository;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SelfUpdateController extends BaseController
{
    use DispatchesJobs;

    public function __construct()
    {
    }

    /**
     * @OA\Post(
     *      path="/api/v1/self-update",
     *      operationId="selfUpdate",
     *      tags={"update"},
     *      summary="Performs a system update",
     *      description="Performs a system update",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Secret"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Password"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Success/failure response"
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
    public function update()
    {
        define('STDIN', fopen('php://stdin', 'r'));

        if (Ninja::isNinja()) {
            return response()->json(['message' => 'Self update not available on this system.'], 403);
        }

        /* .git MUST be owned/writable by the webserver user */
        $repo = new GitRepository(base_path());

        info('Are there changes to pull? '.$repo->hasChanges());

        try {
            $res = $repo->pull();
        } catch (GitException $e) {
            info($e->getMessage());

            return response()->json(['message'=>$e->getMessage()], 500);
        }
        info('Are there any changes to pull? '.$repo->hasChanges());

        Artisan::call('ninja:post-update');

        return response()->json(['message'=>$res], 200);
    }
}
