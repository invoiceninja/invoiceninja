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

use App\Models\TaskStatus;
use App\Utils\Traits\MakesHash;

class TaskStatusTransformer extends EntityTransformer
{
    use MakesHash;

    public function transform(TaskStatus $task_status)
    {
        return [
            'id'          => (string) $this->encodePrimaryKey($task_status->id),
            'name'        => (string) $task_status->name,
            'is_deleted'  => (bool) $task_status->is_deleted,
            'created_at'  => (int) $task_status->created_at,
            'updated_at'  => (int) $task_status->updated_at,
            'archived_at' => (int) $task_status->deleted_at,
        ];
    }
}
