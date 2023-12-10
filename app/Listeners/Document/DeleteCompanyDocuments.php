<?php

namespace App\Listeners\Document;

use App\Libraries\MultiDB;
use App\Models\Document;
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
     * @param $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        $path = sprintf('%s/%s', public_path('storage'), $event->company->company_key);

        Storage::deleteDirectory($event->company->company_key);

        Document::whereCompanyId($event->company->id)->delete();
    }
}
