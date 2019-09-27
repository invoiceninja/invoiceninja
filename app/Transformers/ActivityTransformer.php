<?php

namespace App\Transformers;

use App\Models\Activity;
use App\Utils\Traits\MakesHash;


class ActivityTransformer extends EntityTransformer
{
    use MakesHash;

    protected $defaultIncludes = [];

    /**
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * @param Activity $activity
     *
     * @return array
     */
    public function transform(Activity $activity)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($activity->id),
            'activity_type_id' => (string) $activity->activity_type_id,
            'client_id' => $activity->client ? (string) $activity->client->id : '',
            'company_id' => $activity->company ? (string) $activity->company->id : '',
            'user_id' => (string) $activity->user_id,
            'invoice_id' => $activity->invoice ? (string) $activity->invoice->id : '',
            'payment_id' => $activity->payment ? (string) $activity->payment->id : '',
            'credit_id' => $activity->credit ? (string) $activity->credit->id : '',
            'updated_at' => $activity->updated_at,
            'expense_id' => $activity->expense_id ? (string) $activity->expense->id : '',
            'is_system' => (bool) $activity->is_system,
            'contact_id' => $activity->contact_id ? (string) $activity->contact->id : '',
            'task_id' => $activity->task_id ? (string) $activity->task->id : '',
			'notes' => $activity->notes ? (string) $activity->notes : '',
			'ip' => (string) $activity->ip,

        ];
    }
}
