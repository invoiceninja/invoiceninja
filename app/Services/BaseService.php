<?php namespace App\Services;

use Illuminate\Foundation\Bus\DispatchesCommands;
use App\Services\DatatableService;

class BaseService
{
    use DispatchesCommands;

    protected function getRepo()
    {
        return null;
    }

    public function bulk($ids, $action)
    {
        if ( ! $ids) {
            return 0;
        }

        $entities = $this->getRepo()->findByPublicIdsWithTrashed($ids);

        foreach ($entities as $entity) {
            $this->getRepo()->$action($entity);
        }

        return count($entities);
    }

    public function createDatatable($entityType, $query, $showCheckbox = true, $hideClient = false)
    {
        $columns = $this->getDatatableColumns($entityType, $hideClient);
        $actions = $this->getDatatableActions($entityType);

        return $this->datatableService->createDatatable($entityType, $query, $columns, $actions, $showCheckbox);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [];
    }

    protected function getDatatableActions($entityType)
    {
        return [];
    }
}
