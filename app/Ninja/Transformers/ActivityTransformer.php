<?php namespace App\Ninja\Transformers;

use App\Models\Activity;

/**
 * @SWG\Definition(definition="Activity", @SWG\Xml(name="Activity"))
 */

class ActivityTransformer extends EntityTransformer
{
    protected $defaultIncludes = [ ];

    /**
     * @var array
     */
    protected $availableIncludes = [ ];

    /**
     * @param Client $client
     * @return array
     */
    public function transform(Activity $activity)
    {
        return [
            'activity_type_id' => $activity->activity_type_id,
            'client_id' => $activity->client->public_id,
            'user_id' => $activity->user->public_id + 1,
            'invoice_id' => $activity->invoice ? $activity->invoice->public_id : null,
            'payment_id' => $activity->payment ? $activity->payment->public_id : null,
            'credit_id' => $activity->credit ? $activity->credit->public_id : null,
        ];
    }
}
