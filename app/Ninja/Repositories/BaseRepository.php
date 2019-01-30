<?php

namespace App\Ninja\Repositories;

use Auth;
use Utils;

/**
 * Class BaseRepository.
 */
class BaseRepository
{
    /**
     * @return null
     */
    public function getClassName()
    {
        return null;
    }

    /**
     * @return mixed
     */
    private function getInstance()
    {
        $className = $this->getClassName();

        return new $className();
    }

    /**
     * @param $entity
     * @param $type
     *
     * @return string
     */
    private function getEventClass($entity, $type)
    {
        return 'App\Events\\' . ucfirst($entity->getEntityType()) . 'Was' . $type;
    }

    /**
     * @param $entity
     */
    public function archive($entity)
    {
        if ($entity->trashed()) {
            return;
        }

        $entity->delete();

        $className = $this->getEventClass($entity, 'Archived');

        if (class_exists($className)) {
            event(new $className($entity));
        }
    }

    /**
     * @param $entity
     */
    public function restore($entity)
    {
        if (! $entity->trashed()) {
            return;
        }

        $fromDeleted = false;
        $entity->restore();

        if ($entity->is_deleted) {
            $fromDeleted = true;
            $entity->is_deleted = false;
            $entity->save();
        }

        $className = $this->getEventClass($entity, 'Restored');

        if (class_exists($className)) {
            event(new $className($entity, $fromDeleted));
        }
    }

    /**
     * @param $entity
     */
    public function delete($entity)
    {
        if ($entity->is_deleted) {
            return;
        }

        $entity->is_deleted = true;
        $entity->save();

        $entity->delete();

        $className = $this->getEventClass($entity, 'Deleted');

        if (class_exists($className)) {
            event(new $className($entity));
        }
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

        $entities = $this->findByPublicIdsWithTrashed($ids);

        foreach ($entities as $entity) {
            if (Auth::user()->can('edit', $entity)) {
                $this->$action($entity);
            }
        }

        return count($entities);
    }

    /**
     * @param $ids
     *
     * @return mixed
     */
    public function findByPublicIds($ids)
    {
        return $this->getInstance()->scope($ids)->get();
    }

    /**
     * @param $ids
     *
     * @return mixed
     */
    public function findByPublicIdsWithTrashed($ids)
    {
        return $this->getInstance()->scope($ids)->withTrashed()->get();
    }

    protected function applyFilters($query, $entityType, $table = false)
    {
        $table = Utils::pluralizeEntityType($table ?: $entityType);

        if ($filter = session('entity_state_filter:' . $entityType, STATUS_ACTIVE)) {
            $filters = explode(',', $filter);
            $query->where(function ($query) use ($filters, $table) {
                $query->whereNull($table . '.id');

                if (in_array(STATUS_ACTIVE, $filters)) {
                    $query->orWhereNull($table . '.deleted_at');
                }
                if (in_array(STATUS_ARCHIVED, $filters)) {
                    $query->orWhere(function ($query) use ($table) {
                        $query->whereNotNull($table . '.deleted_at');

                        if (! in_array($table, ['users'])) {
                            $query->where($table . '.is_deleted', '=', 0);
                        }
                    });
                }
                if (in_array(STATUS_DELETED, $filters)) {
                    $query->orWhere(function ($query) use ($table) {
                        $query->whereNotNull($table . '.deleted_at')
                              ->where($table . '.is_deleted', '=', 1);
                    });
                }
            });
        }

        return $query;
    }
}
