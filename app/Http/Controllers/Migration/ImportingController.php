<?php

namespace App\Http\Controllers\Migration;

use App\Http\Controllers\BaseController;
use App\Services\Migration\PurgeService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ImportingController extends BaseController
{
    public function index()
    {
        $this->purge();
    }

    private function purge()
    {
        $option = session('migration_type');
        $companies = session('companies');

        $purgeService = new PurgeService($option, $companies);
        $purgeService->handle();
    }
}
