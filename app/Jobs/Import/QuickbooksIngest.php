<?php

namespace App\Jobs\Import;

use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Import\Providers\Quickbooks;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class QuickbooksIngest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected $engine;
    protected $request;
    protected $company;

    /**
     * Create a new job instance.
     */
    public function __construct(array $request, $company)
    {
        $this->company = $company;
        $this->request = $request;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MultiDB::setDb($this->company->db);
        set_time_limit(0);

        $engine = new Quickbooks(['import_type' => 'client', 'hash' => $this->request['hash'] ], $this->company);
        foreach ($this->request['import_types'] as $entity) {
            $engine->import($entity);
        }

        $engine->finalizeImport();
    }
}
