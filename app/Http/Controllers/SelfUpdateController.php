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

use App\Exceptions\FilePermissionsFailure;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\ClientGroupSettingsSaver;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SelfUpdateController extends BaseController
{
    use DispatchesJobs;
    use ClientGroupSettingsSaver;
    use AppSetup;

    // private bool $use_zip = false;

    private string $filename = 'invoiceninja.tar';

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

        $file_headers = @get_headers($this->getDownloadUrl());

        if (stripos($file_headers[0], "404 Not Found") >0  || (stripos($file_headers[0], "302 Found") > 0 && stripos($file_headers[7], "404 Not Found") > 0)) {
            return response()->json(['message' => 'Download not yet available. Please try again shortly.'], 410);
        }

        try {
            if (copy($this->getDownloadUrl(), storage_path("app/{$this->filename}"))) {
                nlog('Copied file from URL');
            }
        }
        catch(\Exception $e) {
            nlog($e->getMessage());
            return response()->json(['message' => 'File exists on the server, however there was a problem downloading and copying to the local filesystem'], 500);
        }

        nlog('Finished copying');

        $file = Storage::disk('local')->path($this->filename);

        nlog('Extracting tar');

        $phar = new \PharData($file);
        $phar->extractTo(base_path(), null, true);

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
        Artisan::call('config:clear');

        $this->buildCache(true);

        nlog('Called Artisan commands');

        return response()->json(['message' => 'Update completed'], 200);
    }

    private function clearCacheDir()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path('bootstrap/cache'), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            unlink(base_path('bootstrap/cache/').$file->getFileName());
            $file = null;
        }

        $directoryIterator = null;
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

            }

            $file = null;
        }

        $directoryIterator = null;
        
        return true;
    }

    public function checkVersion()
    {
        return trim(file_get_contents(config('ninja.version_url')));
    }

    private function getDownloadUrl()
    {

        $version = $this->checkVersion();

        return "https://github.com/invoiceninja/invoiceninja/releases/download/v{$version}/invoiceninja.tar";

    }
}
