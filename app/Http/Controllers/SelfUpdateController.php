<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Exceptions\FilePermissionsFailure;
use App\Models\Client;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\ClientGroupSettingsSaver;
use Beganovich\Snappdf\Snappdf;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SelfUpdateController extends BaseController
{
    use DispatchesJobs;
    use ClientGroupSettingsSaver;
    use AppSetup;

    private array $purge_file_list = [
        'bootstrap/cache/compiled.php',
        'bootstrap/cache/config.php',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'bootstrap/cache/routes-v7.php',
        'bootstrap/cache/livewire-components.php',
    ];

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
    // public function old_update(\Codedge\Updater\UpdaterManager $updater)
    // {
    //     set_time_limit(0);
    //     define('STDIN', fopen('php://stdin', 'r'));

    //     if (Ninja::isHosted()) {
    //         return response()->json(['message' => ctrans('texts.self_update_not_available')], 403);
    //     }

    //     $this->testWritable();

    //     // Get the new version available
    //     $versionAvailable = $updater->source()->getVersionAvailable();

    //     // Create a release
    //     $release = $updater->source()->fetch($versionAvailable);

    //     $updater->source()->update($release);

    //     $cacheCompiled = base_path('bootstrap/cache/compiled.php');
    //     if (file_exists($cacheCompiled)) { unlink ($cacheCompiled); }
    //     $cacheServices = base_path('bootstrap/cache/services.php');
    //     if (file_exists($cacheServices)) { unlink ($cacheServices); }

    //     Artisan::call('clear-compiled');
    //     Artisan::call('route:clear');
    //     Artisan::call('view:clear');
    //     Artisan::call('optimize');

    //     return response()->json(['message' => 'Update completed'], 200);

    // }

    public function update()
    {
        set_time_limit(0);
        define('STDIN', fopen('php://stdin', 'r'));

        if (Ninja::isHosted()) {
            return response()->json(['message' => ctrans('texts.self_update_not_available')], 403);
        }

        nlog('Test filesystem is writable');

        $this->testWritable();

        nlog('Clear cache directory');

        $this->clearCacheDir();

        nlog('copying release file');

        if (copy($this->getDownloadUrl(), storage_path('app/invoiceninja.zip'))) {
            nlog('Copied file from URL');
        } else {
            return response()->json(['message' => 'Download not yet available. Please try again shortly.'], 410);
        }

        nlog('Finished copying');

        $file = Storage::disk('local')->path('invoiceninja.zip');

        nlog('Extracting zip');

        //clean up old snappdf installations
        $this->cleanOldSnapChromeBinaries();

        // try{
        //     $s = new Snappdf;
        //     $s->getChromiumPath();
        //     chmod($this->generatePlatformExecutable($s->getChromiumPath()), 0755);
        // }
        // catch(\Exception $e){
        //     nlog("I could not set the file permissions for chrome");
        // }

        $zipFile = new \PhpZip\ZipFile();

        $zipFile->openFile($file);

        $zipFile->extractTo(base_path());

        $zipFile->close();

        // $zip = new \ZipArchive;

        // $res = $zip->open($file);
        // if ($res === TRUE) {
        //     $zip->extractTo(base_path());
        //     $zip->close();
        // }

        nlog('Finished extracting files');

        unlink($file);

        nlog('Deleted release zip file');

        foreach ($this->purge_file_list as $purge_file_path) {
            $purge_file = base_path($purge_file_path);
            if (file_exists($purge_file)) {
                unlink($purge_file);
            }
        }

        nlog('Removing cache files');

        Artisan::call('clear-compiled');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('optimize');

        $this->buildCache(true);

        nlog('Called Artisan commands');

        return response()->json(['message' => 'Update completed'], 200);
    }

    private function cleanOldSnapChromeBinaries()
    {
        $current_revision = base_path('vendor/beganovich/snappdf/versions/revision.txt');
        $current_revision_text = file_get_contents($current_revision);

        $iterator = new \DirectoryIterator(base_path('vendor/beganovich/snappdf/versions'));

        foreach ($iterator as $file) {
            if ($file->isDir() && ! $file->isDot() && ($current_revision_text != $file->getFileName())) {
                $directoryIterator = new \RecursiveDirectoryIterator(base_path('vendor/beganovich/snappdf/versions/'.$file->getFileName()), \RecursiveDirectoryIterator::SKIP_DOTS);

                foreach (new \RecursiveIteratorIterator($directoryIterator) as $filex) {
                    unlink($filex->getPathName());
                }

                $this->deleteDirectory(base_path('vendor/beganovich/snappdf/versions/'.$file->getFileName()));
            }
        }
    }

    private function deleteDirectory($dir)
    {
        if (! file_exists($dir)) {
            return true;
        }

        if (! is_dir($dir) || is_link($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (! $this->deleteDirectory($dir.'/'.$item)) {
                if (! $this->deleteDirectory($dir.'/'.$item)) {
                    return false;
                }
            }
        }

        return rmdir($dir);
    }

    private function postHookUpdate()
    {
        if (config('ninja.app_version') == '5.3.82') {
            Client::withTrashed()->cursor()->each(function ($client) {
                $entity_settings = $this->checkSettingType($client->settings);
                $entity_settings->md5 = md5(time());
                $client->settings = $entity_settings;
                $client->save();
            });
        }
    }

    private function clearCacheDir()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path('bootstrap/cache'), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            unlink(base_path('bootstrap/cache/').$file->getFileName());
        }
    }

    private function testWritable()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            if (strpos($file->getPathname(), '.git') !== false) {
                continue;
            }

            if ($file->isFile() && ! $file->isWritable()) {
                nlog("Cannot update system because {$file->getFileName()} is not writable");
                throw new FilePermissionsFailure("Cannot update system because {$file->getFileName()} is not writable");

                return false;
            }
        }

        return true;
    }

    public function checkVersion()
    {
        return trim(file_get_contents(config('ninja.version_url')));
    }

    private function getDownloadUrl()
    {
        $version = $this->checkVersion();

        return "https://github.com/invoiceninja/invoiceninja/releases/download/v{$version}/invoiceninja.zip";
    }
}
