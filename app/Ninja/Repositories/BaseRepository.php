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

    private function dispatchEvent($entity, $type)
    {
        $className = 'App\Events\\' . ucfirst($entity->getEntityType()) . 'Was' . $type;
        event(new $className($entity));
    }

    public function archive($entity)
    {
        $entity->delete();

        $this->dispatchEvent($entity, 'Archived');
    }

    public function restore($entity)
    {
        $entity->restore();

        $entity->is_deleted = false;
        $entity->save();

        $this->dispatchEvent($entity, 'Restored');
    }

    public function delete($entity)
    {
        $entity->is_deleted = true;
        $entity->save();

        $entity->delete();

        $this->dispatchEvent($entity, 'Deleted');
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
