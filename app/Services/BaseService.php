<?php

namespace App\Services;

use Auth;
use Illuminate\Foundation\Bus\DispatchesJobs;

/**
 * Class BaseService.
 */
class BaseService
{
    use DispatchesJobs;

    /**
     * @return null
     */
    protected function getRepo()
    {
        return null;
    }

    /**
     * @param $ids
     * @param $action
     *
     * @return int
     */
    public function bulk($ids, $action)
    {
        if (! $ids) {
            return 0;
        }

        $entities = $this->getRepo()->findByPublicIdsWithTrashed($ids);

        foreach ($entities as $entity) {
            if (Auth::user()->can('edit', $entity)) {
                $this->getRepo()->$action($entity);
            }
        }

        return count($entities);
    }
}
