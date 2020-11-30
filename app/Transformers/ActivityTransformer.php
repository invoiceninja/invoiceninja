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
            'client_id' => $activity->client_id ? (string) $this->encodePrimaryKey($activity->client_id) : '',
            'company_id' => $activity->company_id ? (string) $this->encodePrimaryKey($activity->company_id) : '',
            'user_id' => (string) $this->encodePrimaryKey($activity->user_id),
            'invoice_id' => $activity->invoice_id ? (string) $this->encodePrimaryKey($activity->invoice_id) : '',
            'quote_id' => $activity->quote_id ? (string) $this->encodePrimaryKey($activity->quote_id) : '',
            'payment_id' => $activity->payment_id ? (string) $this->encodePrimaryKey($activity->payment_id) : '',
            'credit_id' => $activity->credit_id ? (string) $this->encodePrimaryKey($activity->credit_id) : '',
            'updated_at' => (int) $activity->updated_at,
            'created_at' => (int) $activity->created_at,
            'expense_id' => $activity->expense_id ? (string) $this->encodePrimaryKey($activity->expense_id) : '',
            'is_system' => (bool) $activity->is_system,
            'contact_id' => $activity->contact_id ? (string) $this->encodePrimaryKey($activity->contact_id) : '',
            'task_id' => $activity->task_id ? (string) $this->encodePrimaryKey($activity->task_id) : '',
            'token_id' => $activity->token_id ? (string) $this->encodePrimaryKey($activity->token_id) : '',
            'notes' => $activity->notes ? (string) $activity->notes : '',
            'ip' => (string) $activity->ip,

        ];
    }
}
