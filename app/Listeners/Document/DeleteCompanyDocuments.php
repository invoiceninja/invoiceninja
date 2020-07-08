<?php

namespace App\Listeners\Document;

use App\Libraries\MultiDB;
use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class DeleteCompanyDocuments
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param CompanyWasDeleted $event
     * @return void
     */
    public function handle($event)
    {
 
        MultiDB::setDb($event->company->db);

        $path = sprintf('%s/%s', storage_path('app/public'), $event->company->company_key);

        // Remove all files & folders, under company's path.
        // This will delete directory itself, as well.
        // In case we want to remove the content of folder, we should use $fs->cleanDirectory();
        //$filesystem = new Filesystem();
        Storage::deleteDirectory($event->company->company_key);

        Document::whereCompanyId($event->company->id)->delete();
        
    }
}
