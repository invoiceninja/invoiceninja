<?php namespace App\Ninja\Transformers;

use App\Models\Project;

class ProjectTransformer extends EntityTransformer
{
    public function transform(Project $project)
    {
        return array_merge($this->getDefaults($project), [
            'id' => (int) $project->public_id,
            'name' => $project->name,
            'client_id' => $project->client ? (int) $project->client->public_id : null,
            'updated_at' => $this->getTimestamp($project->updated_at),
            'archived_at' => $this->getTimestamp($project->deleted_at),
            'is_deleted' => (bool) $project->is_deleted,
        ]);
    }
}
