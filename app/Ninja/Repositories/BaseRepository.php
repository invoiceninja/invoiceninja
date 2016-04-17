<?php namespace App\Ninja\Repositories;

class BaseRepository
{
    public function getClassName() 
    {
        return null;
    }

    private function getInstance()
    {
        $className = $this->getClassName();
        return new $className();
    }

    private function getEventClass($entity, $type)
    {
        return 'App\Events\\' . ucfirst($entity->getEntityType()) . 'Was' . $type;
    }

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

    public function restore($entity)
    {
        if ( ! $entity->trashed()) {
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

    public function findByPublicIds($ids)
    {
        return $this->getInstance()->scope($ids)->get();
    }

    public function findByPublicIdsWithTrashed($ids)
    {
        return $this->getInstance()->scope($ids)->withTrashed()->get();
    }
}
