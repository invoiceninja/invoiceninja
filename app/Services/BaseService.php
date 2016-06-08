<?php namespace App\Services;

use Auth;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Services\DatatableService;

class BaseService
{
    use DispatchesJobs;

    protected function getRepo()
    {
        return null;
    }

    public function bulk($ids, $action)
    {
        if ( ! $ids ) {
            return 0;
        }

        $entities = $this->getRepo()->findByPublicIdsWithTrashed($ids);

        foreach ($entities as $entity) {
            if(Auth::user()->can('edit', $entity)){
                $this->getRepo()->$action($entity);
            }
        }

        return count($entities);
    }

}
