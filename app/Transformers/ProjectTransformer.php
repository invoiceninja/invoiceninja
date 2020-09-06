<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;

use App\Models\Project;
use App\Utils\Traits\MakesHash;

/**
 * class ProjectTransformer.
 */
class ProjectTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    public function transform(Project $project)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($project->id),
            'name' => $project->name ?: '',
            'client_id' => (string) $this->encodePrimaryKey($project->client_id),
            'created_at' => (int) $project->created_at,
            'updated_at' => (int) $project->updated_at,
            'archived_at' => (int) $project->deleted_at,
            'is_deleted' => (bool) $project->is_deleted,
            'task_rate' => (float) $project->task_rate,
            'due_date' => $project->due_date ?: '',
            'private_notes' => $project->private_notes ?: '',
            'budgeted_hours' => (float) $project->budgeted_hours,
            'custom_value1' => $project->custom_value1 ?: '',
            'custom_value2' => $project->custom_value2 ?: '',
            'custom_value3' => $project->custom_value3 ?: '',
            'custom_value4' => $project->custom_value4 ?: '',
        ];
    }
}
