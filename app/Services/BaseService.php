<?php namespace App\Services;

use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Services\DatatableService;

class BaseService
{
    public static $bulk_actions = array('archive', 'restore', 'delete');
    
    use DispatchesJobs;

    protected function getRepo()
    {
        return null;
    }

    public function bulk($ids, $action)
    {
        if ( ! $ids || ! in_array($action, static::$bulk_actions) ) {
            return 0;
        }

        $entities = $this->getRepo()->findByPublicIdsWithTrashed($ids);

        foreach ($entities as $entity) {
            if($entity->canEdit()){
                $this->getRepo()->$action($entity);
            }
        }

        return count($entities);
    }

    public function createDatatable($entityType, $query, $showCheckbox = true, $hideClient = false)
    {
        $columns = $this->getDatatableColumns($entityType, !$showCheckbox);
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
