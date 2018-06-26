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
     * @SWG\Property(property="task_rate", type="number", format="float", example=10)
     * @SWG\Property(property="due_date", type="string", format="date", example="2016-01-01")
     * @SWG\Property(property="private_notes", type="string", format="Sample notes", example=10)
     * @SWG\Property(property="budgeted_hours", type="number", format="float", example=10)
     */
    public function transform(Project $project)
    {
        return array_merge($this->getDefaults($project), [
            'id' => (int) $project->public_id,
            'name' => $project->name ?: '',
            'client_id' => $project->client ? (int) $project->client->public_id : 0,
            'updated_at' => $this->getTimestamp($project->updated_at),
            'archived_at' => $this->getTimestamp($project->deleted_at),
            'is_deleted' => (bool) $project->is_deleted,
            'task_rate' => (float) $project->task_rate,
            'due_date' => $project->due_date ?: '',
            'private_notes' => $project->private_notes ?: '',
            'budgeted_hours' => (float) $project->budgeted_hours,
            'custom_value1' => $project->custom_value1 ?: '',
            'custom_value2' => $project->custom_value2 ?: '',
        ]);
    }
}
