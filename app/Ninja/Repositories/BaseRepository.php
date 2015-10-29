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
        $entity->delete();

        $className = $this->getEventClass($entity, 'Archived');
        event(new $className($entity));
    }

    public function restore($entity)
    {
        $fromDeleted = false;
        $entity->restore();

        if ($entity->is_deleted) {
            $fromDeleted = true;
            $entity->is_deleted = false;
            $entity->save();
        }

        $className = $this->getEventClass($entity, 'Restored');
        event(new $className($entity, $fromDeleted));
    }

    public function delete($entity)
    {
        $entity->is_deleted = true;
        $entity->save();

        $entity->delete();

        $className = $this->getEventClass($entity, 'Deleted');
        event(new $className($entity));
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
