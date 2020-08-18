<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Uploads\StoreUploadRequest;
use App\Utils\Traits\SavesDocuments;

class UploadController extends Controller
{
    use SavesDocuments;

    /**
     * Main logic behind uploading the files.
     * 
     * @param \App\Http\Requests\ClientPortal\Uploads\StoreUploadRequest $request 
     * @return \Illuminate\Http\Response|\Illuminate\Contracts\Routing\ResponseFactory 
     */
    public function __invoke(StoreUploadRequest $request)
    {
        $this->saveDocuments($request->getFile(), auth()->user()->client);

        return response([], 200);
    }
}
