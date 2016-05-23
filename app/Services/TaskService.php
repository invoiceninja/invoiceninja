<?php namespace App\Services;

use Auth;
use URL;
use Utils;
use App\Models\Task;
use App\Models\Invoice;
use App\Models\Client;
use App\Ninja\Repositories\TaskRepository;
use App\Services\BaseService;
use App\Ninja\Datatables\TaskDatatable;

class TaskService extends BaseService
{
    protected $datatableService;
    protected $taskRepo;

    public function __construct(TaskRepository $taskRepo, DatatableService $datatableService)
    {
        $this->taskRepo = $taskRepo;
        $this->datatableService = $datatableService;
    }

    protected function getRepo()
    {
        return $this->taskRepo;
    }

    /*
    public function save()
    {
        return null;
    }
    */

    public function getDatatable($clientPublicId, $search)
    {
        $datatable = new TaskDatatable( ! $clientPublicId, $clientPublicId);
        $query = $this->taskRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('tasks.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }

}
