<?php

namespace App\Ninja\Transformers;

use App\Models\Project;

/**
 * @SWG\Definition(definition="Project", @SWG\Xml(name="Project"))
 */
class ProjectTransformer extends EntityTransformer
{
    /**
     * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
     * @SWG\Property(property="name", type="string", example="Sample")
     * @SWG\Property(property="client_id", type="integer", example=1)
     * @SWG\Property(property="updated_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="archived_at", type="integer", example=1451160233, readOnly=true)
     * @SWG\Property(property="is_deleted", type="boolean", example=false, readOnly=true)
     */
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
