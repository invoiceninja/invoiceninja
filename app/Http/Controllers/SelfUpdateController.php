<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers;

use App\Utils\Ninja;
use Codedge\Updater\UpdaterManager;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Installer;
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
     *
     *
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
     *
     */
    public function update(UpdaterManager $updater)
    {
        if (Ninja::isNinja()) {
            return response()->json(['message' => 'Self update not available on this system.'], 403);
        }

        $res = $updater->update();

        try {
            Artisan::call('migrate');
        } catch (\Exception $e) {
            \Log::error("I wasn't able to migrate the data.");
        }

        try {
            Artisan::call('optimize');
        } catch (\Exception $e) {
            \Log::error("I wasn't able to optimize.");
        }

        $composer = Factory::create(new NullIO(), base_path('composer.json'), false);

        $output = Installer::create(new NullIO, $composer)
            ->setVerbose()
            ->setUpdate(true)
            ->run();
        
        \Log::error(print_r($output,1));

        return response()->json(['message'=>$res], 200);
    }
}
