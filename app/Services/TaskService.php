<?php

namespace App\Services;

use App\Ninja\Datatables\ProjectTaskDatatable;
use App\Ninja\Datatables\TaskDatatable;
use App\Ninja\Repositories\TaskRepository;
use Auth;
use Utils;

/**
 * Class TaskService.
 */
class TaskService extends BaseService
{
    protected $datatableService;
    protected $taskRepo;

    /**
     * TaskService constructor.
     *
     * @param TaskRepository   $taskRepo
     * @param DatatableService $datatableService
     */
    public function __construct(TaskRepository $taskRepo, DatatableService $datatableService)
    {
        $this->taskRepo = $taskRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return TaskRepository
     */
    protected function getRepo()
    {
        return $this->taskRepo;
    }

    /**
     * @param $clientPublicId
     * @param $search
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($clientPublicId, $projectPublicId, $search)
    {
        if ($projectPublicId) {
            $datatable = new ProjectTaskDatatable(true, true);
        } else {
            $datatable = new TaskDatatable(true, $clientPublicId);
        }

        $query = $this->taskRepo->find($clientPublicId, $projectPublicId, $search);

        if (! Utils::hasPermission('view_task')) {
            $query->where('tasks.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
