<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
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
            'color'       => (string) $task_status->color,
            'sort_order'  => (int) $task_status->sort_order, //deprecated
            'is_deleted'  => (bool) $task_status->is_deleted,
            'created_at'  => (int) $task_status->created_at,
            'updated_at'  => (int) $task_status->updated_at,
            'archived_at' => (int) $task_status->deleted_at,
            'status_order' => is_null($task_status->status_order) ? null : (int) $task_status->status_order,
        ];
    }
}
