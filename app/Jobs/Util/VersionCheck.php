<?php

namespace App\Jobs\Util;

use App\Utils\Traits\BulkOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class VersionCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public function __construct()
    {

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //$local_version = storage_path() . '/app/local_version.txt';
        $local_version = 'local_version.txt';

        $version_file = file_get_contents(config('ninja.version_url'));

        if(Storage::exists($local_version));
            Storage::delete($local_version);

        Storage::disk('local')->put('local_version.txt', $version_file);

    }

}
