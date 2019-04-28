<?php

namespace App\Jobs\Utils;

use App\Models\Document;
use Illuminate\Http\Request;

class UploadFile
{

    use MakesHash;

    protected $request;

    protected $user;

    protected $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct(array $request, $user, $company)
    {
        $this->request = $request;
        $this->user = $user;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : ?Document
    {
       
    }
}
