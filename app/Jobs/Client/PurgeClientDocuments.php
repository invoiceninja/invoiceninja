<?php

namespace App\Jobs\Client;

use App\Models\Company;
use App\Models\Document;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class PurgeClientDocuments implements ShouldQueue   
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;
    
    public function __construct(private array $data, public Company $company)
    {
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        foreach($this->data as $key => $value) {
            $this->deleteDocumentsForEntities($key, $value);
        }
    }

    private function deleteDocumentsForEntities(string $class, array $value)
    {
        Document::withTrashed()
                    ->where('documentable_type', $class)
                    ->whereIn('documentable_id', $value)
                    ->cursor()
                    ->each(function ($document){

                        $document->deleteFile();
                        $document->forceDelete();

                    });
    }
}