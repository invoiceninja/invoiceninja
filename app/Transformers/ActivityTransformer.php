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
            'client_id' => $activity->client ? (string) $this->encodePrimaryKey($activity->client->id) : '',
            'company_id' => $activity->company ? (string) $this->encodePrimaryKey($activity->company->id) : '',
            'user_id' => (string) $this->encodePrimaryKey($activity->user_id),
            'invoice_id' => $activity->invoice ? (string) $this->encodePrimaryKey($activity->invoice->id) : '',
            'payment_id' => $activity->payment ? (string) $this->encodePrimaryKey($activity->payment->id) : '',
            'credit_id' => $activity->credit ? (string) $this->encodePrimaryKey($activity->credit->id) : '',
            'updated_at' => $activity->updated_at,
            'expense_id' => $activity->expense_id ? (string) $this->encodePrimaryKey($activity->expense->id) : '',
            'is_system' => (bool) $activity->is_system,
            'contact_id' => $activity->contact_id ? (string) $this->encodePrimaryKey($activity->contact->id) : '',
            'task_id' => $activity->task_id ? (string) $this->encodePrimaryKey($activity->task->id) : '',
			'notes' => $activity->notes ? (string) $activity->notes : '',
			'ip' => (string) $activity->ip,

        ];
    }
}
