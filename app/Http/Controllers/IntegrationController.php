<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Auth;
use Input;
use Response;
use Utils;

/**
 * Class IntegrationController.
 */
class IntegrationController extends BaseAPIController
{
    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe()
    {
        $eventId = Utils::lookupEventId(trim(Input::get('event')));

        if (! $eventId) {
            return Response::json('Event is invalid', 500);
        }

        $subscription = Subscription::createNew();
        $subscription->event_id = $eventId;
        $subscription->target_url = trim(Input::get('target_url'));
        $subscription->save();

        if (! $subscription->id) {
            return Response::json('Failed to create subscription', 500);
        }

        return Response::json(['id' => $subscription->public_id], 201);
    }

    public function unsubscribe($publicId)
    {
        $subscription = Subscription::scope($publicId)->firstOrFail();
        $subscription->delete();

        return $this->response(RESULT_SUCCESS);
    }
}
